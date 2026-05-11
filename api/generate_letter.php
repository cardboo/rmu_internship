<?php
require __DIR__ . '/../includes/db.php';

// 1. FPDF library (vendored at lib/fpdf.php)
$fpdf_path = __DIR__ . '/../lib/fpdf.php';
if (!file_exists($fpdf_path)) {
    die("<b>Library Error:</b> FPDF not found at " . htmlspecialchars($fpdf_path));
}
require $fpdf_path;

// 2. Security Check
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$request_id = $_GET['id'] ?? null;

// 3. Fetch Data
//
// HOD lookup picks the BEST match for the student's department:
//   1. prefer not archived,
//   2. prefer one with an uploaded signature,
//   3. then most recently created.
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name, u.index_number, u.department, u.program,
               h.signature_path, h.full_name AS hod_name, h.job_title
        FROM requests r
        JOIN users u ON r.student_id = u.id
        LEFT JOIN users h
               ON h.department = u.department
              AND h.role       = 'hod'
              AND COALESCE(h.is_archived, 0) = 0
        WHERE r.id = ?
        ORDER BY (h.signature_path IS NOT NULL AND h.signature_path <> '') DESC,
                 h.id DESC
        LIMIT 1
    ");
    $stmt->execute([$request_id]);
    $data = $stmt->fetch();
} catch (PDOException $e) {
    die("<b>Database Error:</b> " . $e->getMessage());
}

if (!$data) {
    die("Request record not found.");
}
if (empty($data['hod_name'])) {
    die("No active HOD assigned to the " . htmlspecialchars($data['department'] ?? '') . " department. Ask the admin to assign one before generating letters.");
}

// 4. Resolve the academic year row (used in placeholder substitution).
$year_row = null;
$sem_row  = null;
if (!empty($data['academic_year_id'])) {
    $yStmt = $pdo->prepare("SELECT * FROM academic_years WHERE id = ?");
    $yStmt->execute([(int)$data['academic_year_id']]);
    $year_row = $yStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!empty($data['start_date'])) {
    $sem_row = semester_for_date($pdo, $data['start_date']);
}

// 5. Hardcoded letter body. (Template-per-department feature was removed
//    per the supervisor's simplification request — admins no longer
//    edit letter wording from the UI.)
$template_body = "We wish to introduce the above-named student who is currently pursuing a program in {student_program} at this University. As part of the requirements for the award of a degree, students are required to undergo a {weeks}-week industrial attachment to gain practical experience.\n\nWe would be grateful if you could offer the student the opportunity to train with your organization from {start_date} to {end_date}.\n\nWe look forward to a favorable response from you.";

// Check if the user specifically chose a generic letter
$is_twimc = (strtolower(trim($data['company_name'] ?? '')) == 'to whom it may concern');

// Compute date / week values used by the placeholders.
$start_fmt = !empty($data['start_date']) ? date('jS M Y', strtotime($data['start_date'])) : '________';
$end_fmt   = !empty($data['end_date'])   ? date('jS M Y', strtotime($data['end_date']))   : '________';
$weeks = 0;
if (!empty($data['start_date']) && !empty($data['end_date'])) {
    $d1 = new DateTime($data['start_date']);
    $d2 = new DateTime($data['end_date']);
    $weeks = floor($d1->diff($d2)->days / 7);
}

// Substitute placeholders.
$replacements = [
    '{student_name}'    => $data['full_name']        ?? '',
    '{student_index}'   => $data['index_number']     ?? '',
    '{student_program}' => $data['program']          ?? '',
    '{department}'      => $data['department']       ?? '',
    '{company_name}'    => $data['company_name']     ?? '',
    '{company_address}' => $data['company_address']  ?? '',
    '{start_date}'      => $start_fmt,
    '{end_date}'        => $end_fmt,
    '{weeks}'           => (string)$weeks,
    '{hod_name}'        => $data['hod_name']         ?? '',
    '{hod_title}'       => $data['job_title']        ?? '',
    '{academic_year}'   => $year_row['name']         ?? '',
    '{semester}'        => $sem_row['label']         ?? '',
    '{date}'            => date('jS F, Y'),
];
$rendered_body = strtr($template_body, $replacements);
// Allow real newlines in the template body (stored as the literal "\n" sequence in seed).
$rendered_body = str_replace(["\\n", "\r\n"], ["\n", "\n"], $rendered_body);

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

// --- Recipient Block ---
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

// --- Body (from template, paragraph-by-paragraph) ---
$pdf->SetFont('Arial', '', 11);
foreach (preg_split('/\n\s*\n/', trim($rendered_body)) as $para) {
    $pdf->MultiCell(0, 7, trim($para));
    $pdf->Ln(4);
}
$pdf->Ln(4);
$pdf->Cell(0, 7, "Yours faithfully,", 0, 1);
$pdf->Ln(10);

// --- Signature Block (Path & Overlap Fixed) ---
// Define the folder where signatures are kept
$sig_directory = __DIR__ . '/../assets/images/signatures/';
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