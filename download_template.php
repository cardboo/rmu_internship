<?php
session_start();
if (!isset($_SESSION['user_id'])) exit;

if (isset($_GET['file'])) {
    $file = basename($_GET['file']); 
    $path = 'templates/' . $file;

    if (file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        die("Error: Template file not found on server.");
    }
}