<?php
/**
 * Auth helpers shared across pages.
 * Located at: includes/auth.php
 *
 * Anything that needs db.php should require it before this file.
 */

// ----------------------------------------------------------------------
// Allowed RMU email domains
// ----------------------------------------------------------------------
const RMU_STAFF_DOMAIN   = 'rmu.edu.gh';
const RMU_STUDENT_DOMAIN = 'st.rmu.edu.gh';

/**
 * Validate an email belongs to RMU. Optionally enforce role-domain pairing:
 *   - students must be @st.edu.rmu.gh
 *   - everyone else must be @rmu.edu.gh
 *
 * @param string      $email
 * @param string|null $role  null = accept either RMU domain
 * @return string|null       null if valid, otherwise an error message
 */
function rmu_email_error(string $email, ?string $role = null): ?string {
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $domain = substr(strrchr($email, '@'), 1);
    if ($role === 'student') {
        if ($domain !== RMU_STUDENT_DOMAIN) {
            return 'Students must use a @' . RMU_STUDENT_DOMAIN . ' email.';
        }
    } elseif ($role !== null) {
        if ($domain !== RMU_STAFF_DOMAIN) {
            return 'Staff must use a @' . RMU_STAFF_DOMAIN . ' email.';
        }
    } else {
        if ($domain !== RMU_STAFF_DOMAIN && $domain !== RMU_STUDENT_DOMAIN) {
            return 'Email must end in @' . RMU_STAFF_DOMAIN . ' or @' . RMU_STUDENT_DOMAIN . '.';
        }
    }
    return null;
}

/**
 * Generate a readable temporary password.
 * 10 chars: 6 alpha + 4 digits, mixed case, no easily-confused symbols.
 */
function generate_temp_password(int $length = 10): string {
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz'; // no I/l/O
    $digits = '23456789'; // no 0/1
    $alpha_len  = strlen($alpha);
    $digit_len  = strlen($digits);
    $out = '';
    // 60% alpha, 40% digits
    for ($i = 0; $i < $length; $i++) {
        $out .= ($i % 5 < 3)
            ? $alpha[random_int(0, $alpha_len - 1)]
            : $digits[random_int(0, $digit_len - 1)];
    }
    return str_shuffle($out);
}

/**
 * Require a specific role to access the current page.
 * Redirects to login if the visitor is unauthenticated or has the wrong role.
 */
function require_role(string ...$roles): void {
    if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $roles, true)) {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}

/**
 * Fetch the current academic year row + its semesters.
 *
 * Returns:
 *   ['id' => int, 'name' => string, 'start_date' => 'Y-m-d',
 *    'end_date' => 'Y-m-d', 'semesters' => [['label' => ..., ...]]]
 *   or null if no year is marked current (e.g. fresh install).
 *
 * Cached for the request lifetime.
 */
function current_academic_year(PDO $pdo): ?array {
    static $cache = false;
    if ($cache !== false) return $cache;

    try {
        $year = $pdo->query("SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1")
                    ->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Migration 007 hasn't been run yet — fail soft.
        $cache = null;
        return null;
    }
    if (!$year) {
        $cache = null;
        return null;
    }
    $sStmt = $pdo->prepare("SELECT * FROM semesters WHERE academic_year_id = ? ORDER BY sort_order, start_date");
    $sStmt->execute([$year['id']]);
    $year['semesters'] = $sStmt->fetchAll(PDO::FETCH_ASSOC);

    $cache = $year;
    return $year;
}

/**
 * Generate a fresh supervisor token (64 hex chars, ~256 bits of entropy).
 * Used to give the on-the-job supervisor access to a placement without
 * an account.
 */
function generate_supervisor_token(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Resolve a supervisor token to its placement row. Returns null if
 * the token is unknown or expired.
 */
function placement_by_supervisor_token(PDO $pdo, string $token): ?array {
    if ($token === '') return null;
    $stmt = $pdo->prepare("
        SELECT p.*,
               u.full_name AS student_name, u.index_number, u.program, u.department,
               y.name AS academic_year_name
        FROM placements p
        JOIN users u ON u.id = p.student_id
        LEFT JOIN academic_years y ON y.id = p.academic_year_id
        WHERE p.supervisor_token = ?
          AND (p.supervisor_token_expires_at IS NULL
               OR p.supervisor_token_expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Issue a fresh supervisor token for a placement. 60-day expiry by default.
 * Safe to call repeatedly (rotates the token).
 */
function issue_supervisor_token(PDO $pdo, int $placement_id, int $days = 60): string {
    $token = generate_supervisor_token();
    $pdo->prepare("
        UPDATE placements
        SET supervisor_token = ?,
            supervisor_token_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
        WHERE id = ?
    ")->execute([$token, $days, $placement_id]);
    return $token;
}

// ----------------------------------------------------------------------
// Supervisor OTPs (one-time codes emailed to the on-the-job supervisor
// so they can sign off on weekly logs / submit the final evaluation
// from the student's machine without an account).
// ----------------------------------------------------------------------

/**
 * Generate, store, and return a 6-digit OTP for a placement + purpose.
 * Default expiry: 15 minutes. The caller is responsible for emailing
 * the returned plaintext code to placement.supervisor_email.
 *
 * Returns null if migration 015 (supervisor_otps table) hasn't been
 * applied yet — caller should surface a friendly "run migration 015"
 * message rather than letting the PDOException surface as a fatal.
 */
function issue_supervisor_otp(PDO $pdo, int $placement_id, string $email, string $purpose, int $ttl_minutes = 15): ?string {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hash = password_hash($code, PASSWORD_DEFAULT);

    try {
        // Invalidate any previous still-valid OTPs for the same purpose, so
        // re-requesting a code doesn't leave several active at once.
        $pdo->prepare("
            UPDATE supervisor_otps
            SET consumed_at = NOW()
            WHERE placement_id = ?
              AND purpose = ?
              AND consumed_at IS NULL
        ")->execute([$placement_id, $purpose]);

        $pdo->prepare("
            INSERT INTO supervisor_otps
                (placement_id, email, purpose, code_hash, expires_at)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
        ")->execute([$placement_id, $email, $purpose, $hash, $ttl_minutes]);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'supervisor_otps')) {
            return null;
        }
        throw $e;
    }

    return $code;
}

/**
 * Verify a supplied code against the latest unconsumed OTP for
 * (placement, purpose). Marks the OTP consumed on success.
 *
 * Returns false (not exception) if the supervisor_otps table is
 * missing, so the caller can show a setup-instruction message.
 *
 * @return bool true if the code matches and isn't expired/consumed
 */
function verify_supervisor_otp(PDO $pdo, int $placement_id, string $purpose, string $code): bool {
    $code = trim($code);
    if (!preg_match('/^\d{6}$/', $code)) return false;

    try {
        $stmt = $pdo->prepare("
            SELECT id, code_hash
            FROM supervisor_otps
            WHERE placement_id = ?
              AND purpose = ?
              AND consumed_at IS NULL
              AND expires_at > NOW()
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$placement_id, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        if (!password_verify($code, $row['code_hash'])) return false;

        $pdo->prepare("UPDATE supervisor_otps SET consumed_at = NOW() WHERE id = ?")
            ->execute([(int)$row['id']]);
        return true;
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'supervisor_otps')) {
            return false;
        }
        throw $e;
    }
}

/**
 * Given a date (Y-m-d), find which semester of the current
 * academic year it falls within. Returns the semester row or null.
 */
function semester_for_date(PDO $pdo, string $date): ?array {
    $year = current_academic_year($pdo);
    if (!$year) return null;
    foreach ($year['semesters'] as $s) {
        if ($date >= $s['start_date'] && $date <= $s['end_date']) {
            return $s;
        }
    }
    return null;
}

/**
 * If the current user is flagged must_change_password=1, force-redirect
 * them to change_password.php until they comply. Pages that need to
 * bypass this (the change_password page itself, logout) can declare
 *   define('SKIP_PASSWORD_GATE', true);
 * BEFORE requiring includes/db.php.
 */
function enforce_password_change(): void {
    if (defined('SKIP_PASSWORD_GATE')) return;
    if (empty($_SESSION['user_id'])) return;
    if (empty($_SESSION['must_change_password'])) return;

    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($current === 'change_password.php' || $current === 'logout.php') return;

    header("Location: " . BASE_URL . "change_password.php");
    exit;
}
