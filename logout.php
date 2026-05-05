<?php
require __DIR__ . '/includes/db.php';
session_unset();
session_destroy();
header("Location: " . BASE_URL . "index.php");
exit;
