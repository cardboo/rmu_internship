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
