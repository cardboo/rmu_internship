<?php
/**
 * Database connection + global bootstrap.
 *
 * Located at: includes/db.php
 * Project root resolves to dirname(__DIR__).
 *
 * Defines the BASE_URL constant by auto-detecting the deployment path,
 * so that links in views work whether the app lives at /
 * or under a subfolder like /rmu_internship/.
 */

$host = 'localhost';
$db   = 'internship_system';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------
// BASE_URL: auto-detect web path of the project root so links work
// whether the app is deployed at / or under a subfolder.
// ----------------------------------------------------------------------
if (!defined('BASE_URL')) {
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $script_dir = preg_replace('#/(admin|hod|secretary|student|api|tools)$#', '', $script_dir);
    if ($script_dir === '' || $script_dir === '.') $script_dir = '/';
    define('BASE_URL', rtrim($script_dir, '/') . '/');
}

if (!function_exists('asset')) {
    function asset(string $relative): string {
        return BASE_URL . 'assets/' . ltrim($relative, '/');
    }
}
if (!function_exists('url')) {
    function url(string $relative): string {
        return BASE_URL . ltrim($relative, '/');
    }
}
