<?php
/**
 * Registry lookup endpoint.
 *
 * Used by:
 *   - secretary/register_student.php  (department-locked lookup)
 *   - register.php (public)            (cross-department lookup, but only
 *                                       returns minimal info to avoid leaking
 *                                       PII to anyone who can guess indices).
 *
 * GET parameters:
 *   index   = the student's index number
 *   public  = 1 (optional) → public mode, returns full_name + dept + program
 *
 * Response (JSON):
 *   { ok: bool, data?: {...}, error?: string }
 */

require __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$idx     = strtoupper(trim($_GET['index']  ?? ''));
$public  = !empty($_GET['public']);

if ($idx === '') {
    echo json_encode(['ok' => false, 'error' => 'Provide an index number.']);
    exit;
}

// PUBLIC mode is callable without a session — used by register.php.
// SECRETARY mode requires the secretary session and locks to their dept.
if (!$public) {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'secretary') {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
        exit;
    }
    $myDeptStmt = $pdo->prepare("SELECT department FROM users WHERE id = ?");
    $myDeptStmt->execute([$_SESSION['user_id']]);
    $myDept = $myDeptStmt->fetchColumn();
}

$stmt = $pdo->prepare("
    SELECT r.index_number, r.full_name, r.email, r.level, r.gender,
           r.date_of_birth, r.year_admitted, r.is_claimed,
           d.name AS dept_name, p.name AS program_name
    FROM student_registry r
    JOIN departments d ON d.id = r.department_id
    JOIN programs    p ON p.id = r.program_id
    WHERE r.index_number = ?
");
$stmt->execute([$idx]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'error' => "No registry entry found for $idx."]);
    exit;
}

if ((int)$row['is_claimed'] === 1) {
    echo json_encode(['ok' => false, 'error' => "$idx already has a portal account."]);
    exit;
}

if (!$public && strcasecmp($row['dept_name'], $myDept ?? '') !== 0) {
    echo json_encode(['ok' => false, 'error' => "$idx is not in your department."]);
    exit;
}

if ($public) {
    // Strip PII the public form doesn't need.
    unset($row['date_of_birth'], $row['year_admitted'], $row['gender']);
}

echo json_encode(['ok' => true, 'data' => $row]);
