<?php
require __DIR__ . '/../includes/db.php';

// The password we want to use
$plain_password = '123456';
// Generate the secure hash
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

try {
    // Clear existing test users to avoid duplicates
    $pdo->exec("DELETE FROM users WHERE email IN ('student@test.com', 'hod@test.com')");

    // Re-insert with the fresh hash
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    
    $stmt->execute(['Selii Student', 'student@test.com', $hashed_password, 'student']);
    $stmt->execute(['Dr. HOD', 'hod@test.com', $hashed_password, 'hod']);

    echo "Success! Users have been reset.<br>";
    echo "Student: student@test.com / 123456<br>";
    echo "HOD: hod@test.com / 123456<br>";
    echo "<a href='" . BASE_URL . "index.php'>Go to Login</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>