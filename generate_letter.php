<?php
require 'db.php';

// 1. Path Debugger for FPDF
$fpdf_path = 'libs/fpdf.php';
if (!file_exists($fpdf_path)) {
    die("<b>Library Error:</b> FPDF not found at " . htmlspecialchars($fpdf_path));
}
require $fpdf_path;

// 2. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$request_id = $_GET['id'] ?? null;

// 3. Fetch Data
try {
    $stmt = $pdo->prepare("SELECT r.*, u.full_name, u.index_number, u.department, u.program, 
                                  h.signature_path, h.full_name as hod_name, h.job_title
                           FROM requests r
                           JOIN users u ON r.student_id = u.id
                           JOIN users h ON u.department = h.department AND h.role = 'hod'
                           WHERE r.id = ?");
    $stmt->execute([$request_id]);
    $data = $stmt->fetch();
} catch (PDOException $e) {
    die("<b>Database Error:</b> " . $e->getMessage());
}

if (!$data) {
    die("Request record not found or HOD not assigned to this department.");
}

// Check if the user specifically chose a generic letter
$is_twimc = (strtolower(trim($data['company_name'])) == 'to whom it may concern');

$pdf = new FPDF();
$pdf->AddPage();

// --- Letterhead ---
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'REGIONAL MARITIME UNIVERSITY', 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Department of ' . strtoupper($data['department']), 0, 1, 'C');
$pdf->Cell(0, 5, 'Post Office Box GP 1111, Accra, Ghana', 0, 1, 'C');
$pdf->Ln(15);

// --- Date ---
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 10, 'Date: ' . date('jS F, Y'), 0, 1, 'L');
$pdf->Ln(5);

// --- Recipient Block (Manual Dashes) ---
$pdf->SetFont('Arial', '', 11);
if ($is_twimc) {
    $pdf->Cell(0, 7, '________________________________________', 0, 1, 'L');
    $pdf->Cell(0, 7, '________________________________________', 0, 1, 'L');
    $pdf->Cell(0, 7, '________________________________________', 0, 1, 'L');
    $pdf->Cell(0, 7, '________________________________________', 0, 1, 'L');
} else {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, strtoupper($data['company_name']), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 7, $data['company_address']);
}
$pdf->Ln(10);

$pdf->Cell(0, 10, 'Dear Sir/Madam,', 0, 1, 'L');

// --- Subject ---
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'RE: INDUSTRIAL ATTACHMENT FOR ' . strtoupper($data['full_name']), 0, 1, 'L');
$pdf->Ln(5);

// --- Body ---
$pdf->SetFont('Arial', '', 11);
$start = date('jS M Y', strtotime($data['start_date']));
$end = date('jS M Y', strtotime($data['end_date']));

$d1 = new DateTime($data['start_date']);
$d2 = new DateTime($data['end_date']);
$weeks = floor($d1->diff($d2)->days / 7);

$text = "We wish to introduce the above-named student who is currently pursuing a program in " . $data['program'] . " at this University. ";
$text .= "As part of the requirements for the award of a degree, students are required to undergo a " . $weeks . "-week industrial attachment to gain practical experience.";

$pdf->MultiCell(0, 7, $text);
$pdf->Ln(5);

$text2 = "We would be grateful if you could offer the student the opportunity to train with your organization from " . $start . " to " . $end . ".";
$pdf->MultiCell(0, 7, $text2);
$pdf->Ln(10);

$pdf->Cell(0, 7, "We look forward to a favorable response from you.", 0, 1);
$pdf->Ln(5);
$pdf->Cell(0, 7, "Yours faithfully,", 0, 1);
$pdf->Ln(10);

// --- Signature Block (Path & Overlap Fixed) ---
// Define the folder where signatures are kept
$sig_directory = 'images/signatures/'; 
$full_sig_path = $sig_directory . $data['signature_path'];

if (!empty($data['signature_path']) && file_exists($full_sig_path)) {
    $currentY = $pdf->GetY();
    
    // Use the full path for the Image function
    $pdf->Image($full_sig_path, $pdf->GetX(), $currentY, 40);
    
    // Move Y down to clear the signature height (Adjust 25 if needed)
    $pdf->SetY($currentY + 25); 
} else {
    // Debugging note (remains invisible in the final PDF if you remove the next line)
    // $pdf->Cell(0, 5, 'Sig not found at: ' . $full_sig_path, 0, 1); 
    
    $pdf->Ln(25); // Blank space for manual signing
}

// 4. Print HOD Details (CLEANED - Only printed once)
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 5, strtoupper($data['hod_name']), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $data['job_title'], 0, 1);

$pdf->Output('I', 'Attachment_Letter_' . $data['index_number'] . '.pdf');