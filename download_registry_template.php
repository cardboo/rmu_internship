<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="registry_template.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$out = fopen('php://output', 'w');

// header row
fputcsv($out, [
    'index_number',
    'full_name',
    'department',
    'program',
    'level',
    'gender',
    'date_of_birth',
    'year_admitted',
]);

// example rows (admin can delete before re-uploading)
fputcsv($out, [
    'BIT0002001',
    'Kwame Mensah',
    'ICT',
    'BSc. Information Technology',
    '300',
    'Male',
    '2003-05-12',
    '2022',
]);
fputcsv($out, [
    'BME0002006',
    'Ama Serwaa',
    'Marine Engineering',
    'BSc. Marine Engineering',
    '200',
    'Female',
    '2004-09-30',
    '2023',
]);

fclose($out);
exit;
