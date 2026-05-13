<?php
/**
 * Shared print-only header for reports. Hidden on screen, shown at
 * the top of every printout via @media print rules in reports.css.
 *
 * Pages including this should optionally set $report_title beforehand
 * so it appears in the header.
 *
 * Usage:
 *   $report_title = 'System-wide Internship Report';
 *   include __DIR__ . '/../includes/print_header.php';
 */
$report_title = $report_title ?? 'RMU Internship Portal';
?>
<div class="print-header">
    <img src="<?php echo asset('images/logo.jpg'); ?>" alt="RMU">
    <div class="print-header-text">
        <strong>REGIONAL MARITIME UNIVERSITY</strong>
        <div class="print-header-sub">Internship &amp; Attachment Portal</div>
    </div>
    <div class="print-header-meta">
        <strong><?php echo htmlspecialchars($report_title); ?></strong>
        <div>Printed <?php echo date('d M Y H:i'); ?></div>
    </div>
</div>
