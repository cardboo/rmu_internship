<?php
// The plain text password you want to use
$plain_password = 'password123';

// Generate the high-security hash
$hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);

echo "<h3>RMU Hash Generator</h3>";
echo "<strong>Plain Text:</strong> " . $plain_password . "<br>";
echo "<strong>Generated Hash:</strong> <br><textarea rows='3' cols='70' readonly>" . $hashed_password . "</textarea>";
echo "<br><br><em>Copy the hash above and paste it into your SQL query.</em>";
?>