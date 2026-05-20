<?php
/**
 * Reminder dispatcher — run by a scheduled task (cron / Windows Task
 * Scheduler) at most once a day.
 *
 *   php tools/send_reminders.php           (live: actually send mail)
 *   php tools/send_reminders.php --dry-run (preview only, no SMTP)
 *
 * Three rules, evaluated independently. Each rule produces a stable
 * dedupe_key so the same event isn't re-emailed on consecutive runs.
 *
 *   1. logbook_overdue       — placement active, last submitted log
 *                              older than 7 days  →  email student
 *                              (CC the department HOD).
 *
 *   2. evaluation_due_soon   — placement end-date within next 7 days
 *                              and no evaluation submitted yet  →
 *                              email student.
 *
 *   3. hod_letter_digest     — any letter request with status=pending
 *                              older than 48h  →  one daily digest
 *                              email per HOD listing their backlog.
 *
 * On the first run, every rule writes a row to reminders_log; the
 * dedupe key (unique index) guarantees the same email isn't sent
 * twice within its window.
 *
 * Exit status: 0 if the script completed (regardless of how many
 * mails were sent), 1 on hard failure (DB unreachable, migration
 * 017 missing, etc.).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("CLI only.\n");
}

$dry_run = in_array('--dry-run', $argv ?? [], true);

// Use the same bootstrap as the web pages — gives us $pdo, BASE_URL,
// and the auth/email helpers in one require.
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email.php';

// Sanity-check the dedupe table.
try {
    $pdo->query("SELECT 1 FROM reminders_log LIMIT 0");
} catch (PDOException $e) {
    fwrite(STDERR, "[reminders] reminders_log table missing — run migrations/017_reminders_log.sql first.\n");
    exit(1);
}

$now      = new DateTime('now');
$today    = $now->format('Y-m-d');
$iso_week = $now->format('o-\WW');     // e.g. 2026-W21
$counters = [
    'logbook_overdue'     => ['considered' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0],
    'evaluation_due_soon' => ['considered' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0],
    'hod_letter_digest'   => ['considered' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0],
];

/**
 * Send one reminder. Returns true if mail went out, false if it was
 * skipped (already sent for this dedupe_key) or failed.
 */
function dispatch_reminder(
    PDO $pdo,
    bool $dry_run,
    string $kind,
    string $target_type,
    ?int $target_id,
    string $recipient,
    string $subject,
    string $body,
    string $dedupe_key,
    ?string $cc = null
): string {
    // Has this exact dedupe_key already been recorded? If so, skip.
    $existsStmt = $pdo->prepare("SELECT 1 FROM reminders_log WHERE dedupe_key = ? LIMIT 1");
    $existsStmt->execute([$dedupe_key]);
    if ($existsStmt->fetchColumn()) return 'skipped';

    if ($dry_run) {
        echo "  [dry-run] {$kind} → {$recipient} :: {$subject}\n";
        return 'sent';
    }

    $ok    = false;
    $error = null;
    try {
        $r = send_email($pdo, $recipient, $subject, $body, false);
        $ok    = $r['ok'];
        $error = $r['ok'] ? null : ($r['error'] ?? 'unknown error');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($ok && $cc !== null && $cc !== '' && $cc !== $recipient) {
        // Best-effort CC as a separate send. PHPMailer addCC would be
        // cleaner but try_send_email() is single-recipient.
        try_send_email($pdo, $cc, "[CC] $subject", $body, false);
    }

    try {
        $pdo->prepare("
            INSERT INTO reminders_log
                (kind, target_type, target_id, recipient, subject, dedupe_key, delivered, error)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $kind, $target_type, $target_id,
            $recipient, $subject, $dedupe_key,
            $ok ? 1 : 0,
            $error !== null ? substr($error, 0, 255) : null,
        ]);
    } catch (PDOException $e) {
        // If the dedupe-key UNIQUE collided in a race, treat as skipped.
        if ($e->getCode() === '23000') return 'skipped';
        throw $e;
    }

    return $ok ? 'sent' : 'failed';
}

// ----------------------------------------------------------------------
// Rule 1: logbook_overdue
//   placement active (today between start and end)
//   AND last submitted logbook is older than 7 days
// ----------------------------------------------------------------------
echo "Rule 1: logbook_overdue\n";
$stmt = $pdo->prepare("
    SELECT
        p.id              AS placement_id,
        p.student_id,
        p.company_name,
        u.full_name       AS student_name,
        u.email           AS student_email,
        u.department      AS student_dept,
        hod.email         AS hod_email,
        hod.full_name     AS hod_name,
        (SELECT MAX(week_end) FROM logbooks l
          WHERE l.placement_id = p.id AND l.is_submitted = 1) AS last_week_end
    FROM placements p
    JOIN users u  ON u.id = p.student_id AND COALESCE(u.is_archived,0) = 0
    LEFT JOIN users hod
           ON hod.role = 'hod'
          AND hod.department = u.department
          AND COALESCE(hod.is_archived,0) = 0
    WHERE CURDATE() BETWEEN p.start_date AND p.end_date
");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counters['logbook_overdue']['considered']++;

    $last  = $row['last_week_end'] ? new DateTime($row['last_week_end']) : null;
    $stale = ($last === null) || ((int)$now->diff($last)->format('%a') > 7);
    if (!$stale) { $counters['logbook_overdue']['skipped']++; continue; }

    $dedupe = "logbook_overdue:placement={$row['placement_id']}:week={$iso_week}";

    $last_str = $last ? $last->format('d M Y') : 'never';
    $body = "Hello {$row['student_name']},\n\n"
          . "Friendly reminder: your weekly logbook on the RMU Internship Portal "
          . "is overdue.\n\n"
          . "  Placement:        {$row['company_name']}\n"
          . "  Last submitted:   {$last_str}\n"
          . "  Today:            {$today}\n\n"
          . "Please log in and complete the current week's entry. Your supervisor "
          . "must also sign off before it counts.\n\n"
          . "  " . BASE_URL . "student/logbook.php\n\n"
          . "If you've already submitted but the system says otherwise, contact your "
          . "department HOD.\n\n"
          . "— RMU Internship Portal";

    $result = dispatch_reminder(
        $pdo, $dry_run,
        'logbook_overdue', 'placement', (int)$row['placement_id'],
        $row['student_email'],
        "Weekly logbook overdue — {$row['company_name']}",
        $body, $dedupe,
        $row['hod_email'] ?? null
    );
    $counters['logbook_overdue'][$result]++;
}

// ----------------------------------------------------------------------
// Rule 2: evaluation_due_soon
//   placement end within next 7 days
//   AND no evaluation submitted for that placement
// ----------------------------------------------------------------------
echo "Rule 2: evaluation_due_soon\n";
$stmt = $pdo->prepare("
    SELECT
        p.id            AS placement_id,
        p.student_id,
        p.company_name,
        p.end_date,
        u.full_name     AS student_name,
        u.email         AS student_email,
        DATEDIFF(p.end_date, CURDATE()) AS days_left
    FROM placements p
    JOIN users u ON u.id = p.student_id AND COALESCE(u.is_archived,0) = 0
    LEFT JOIN evaluations e ON e.placement_id = p.id
    WHERE p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
      AND e.id IS NULL
");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counters['evaluation_due_soon']['considered']++;

    $dedupe = "eval_due_soon:placement={$row['placement_id']}:7d-window";

    $end_str   = (new DateTime($row['end_date']))->format('d M Y');
    $days_left = (int)$row['days_left'];
    $when      = $days_left <= 0 ? "today" : ($days_left === 1 ? "tomorrow" : "in {$days_left} days");

    $body = "Hello {$row['student_name']},\n\n"
          . "Your internship at {$row['company_name']} ends $when ($end_str), but the "
          . "final supervisor evaluation hasn't been submitted yet.\n\n"
          . "Action required: ask your on-the-job supervisor to complete the final "
          . "evaluation. You can request a one-time access code from this page:\n\n"
          . "  " . BASE_URL . "student/evaluation.php\n\n"
          . "Without a completed evaluation your internship score cannot be finalised.\n\n"
          . "— RMU Internship Portal";

    $result = dispatch_reminder(
        $pdo, $dry_run,
        'evaluation_due_soon', 'placement', (int)$row['placement_id'],
        $row['student_email'],
        "Final evaluation pending — {$row['company_name']} (ends $end_str)",
        $body, $dedupe
    );
    $counters['evaluation_due_soon'][$result]++;
}

// ----------------------------------------------------------------------
// Rule 3: hod_letter_digest
//   one daily digest per HOD with all pending letter requests from
//   their department older than 48h.
// ----------------------------------------------------------------------
echo "Rule 3: hod_letter_digest\n";
$stmt = $pdo->prepare("
    SELECT
        hod.id          AS hod_id,
        hod.full_name   AS hod_name,
        hod.email       AS hod_email,
        hod.department  AS hod_dept,
        COUNT(r.id)     AS n_pending,
        MIN(r.id)       AS oldest_req_id,
        MIN(r.created_at) AS oldest_req_at
    FROM users hod
    JOIN requests r ON r.status = 'pending'
                    AND r.created_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    JOIN users   s  ON s.id = r.student_id
                    AND s.department = hod.department
                    AND COALESCE(s.is_archived,0) = 0
    WHERE hod.role = 'hod' AND COALESCE(hod.is_archived,0) = 0
    GROUP BY hod.id, hod.full_name, hod.email, hod.department
");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counters['hod_letter_digest']['considered']++;

    // One digest per HOD per day.
    $dedupe = "hod_letter_digest:hod={$row['hod_id']}:{$today}";

    $oldest_str = (new DateTime($row['oldest_req_at']))->format('d M Y');
    $body = "Hello {$row['hod_name']},\n\n"
          . "There are {$row['n_pending']} letter request(s) from {$row['hod_dept']} "
          . "students that have been pending for more than 48 hours. The oldest "
          . "was submitted on $oldest_str.\n\n"
          . "Please review and approve / reject:\n\n"
          . "  " . BASE_URL . "hod/dashboard.php\n\n"
          . "— RMU Internship Portal";

    $result = dispatch_reminder(
        $pdo, $dry_run,
        'hod_letter_digest', 'user', (int)$row['hod_id'],
        $row['hod_email'],
        "Letter requests awaiting your approval ({$row['n_pending']})",
        $body, $dedupe
    );
    $counters['hod_letter_digest'][$result]++;
}

// ----------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------
echo "\n=== Summary ===\n";
foreach ($counters as $rule => $c) {
    printf("  %-25s  considered=%-3d  sent=%-3d  skipped(dedup)=%-3d  failed=%-3d\n",
        $rule, $c['considered'], $c['sent'], $c['skipped'], $c['failed']);
}
echo $dry_run ? "\n(dry-run — no mail actually sent and no rows written)\n" : "\nDone.\n";
exit(0);
