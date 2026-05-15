<?php
/**
 * AJAX endpoint — peek-verify an OTP without consuming it.
 *
 * Used by the OTP-gated supervisor forms on student/logbook.php and
 * student/evaluation.php to decide whether to unlock the disabled
 * fields as the supervisor types. The actual consume happens at
 * submit time via verify_supervisor_otp().
 *
 * POST params:
 *   placement_id  int
 *   purpose       'evaluation' | 'logbook:<id>'
 *   code          6-digit numeric string
 *
 * Response:
 *   { ok: true }                   — code matches the latest unconsumed OTP
 *   { ok: false, error: string }   — bad code / expired / no OTP / unauthorized
 *
 * Locked to the authenticated student that owns the placement, so an
 * attacker can't probe random placement IDs.
 */

require __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

// Only the student who owns the placement may peek.
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$placement_id = (int)($_POST['placement_id'] ?? 0);
$purpose      = trim($_POST['purpose'] ?? '');
$code         = trim($_POST['code']    ?? '');

if ($placement_id <= 0 || $purpose === '' || $code === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters.']);
    exit;
}

// Confirm the placement actually belongs to this student.
$own = $pdo->prepare("SELECT 1 FROM placements WHERE id = ? AND student_id = ?");
$own->execute([$placement_id, (int)$_SESSION['user_id']]);
if (!$own->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$ok = peek_supervisor_otp($pdo, $placement_id, $purpose, $code);
echo json_encode($ok
    ? ['ok' => true]
    : ['ok' => false, 'error' => 'Invalid or expired code.']);
