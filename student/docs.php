<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require __DIR__ . '/../includes/db.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: " . BASE_URL . "index.php"); 
    exit;
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Templates | RMU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
    <style>
        .doc-card { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            border: 1px solid var(--border); 
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .template-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin-top: 25px; 
        }
        .download-btn { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 20px; 
            background: #f8fafc; 
            border-radius: 12px; 
            text-decoration: none; 
            color: var(--text-main); 
            border: 2px solid transparent; 
            transition: all 0.3s ease; 
        }
        .download-btn:hover { 
            border-color: var(--primary); 
            background: #f0f4ff; 
            transform: translateY(-2px);
        }
        .download-btn i.fa-file-pdf {
            font-size: 1.5rem;
            color: #ef4444;
        }
    </style>
</head>
<body>

    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="header-panel">
            <h1>Internship Documentation</h1>
            <p>Access and download official RMU internship templates below.</p>
        </div>

        <div class="doc-card">
            <div style="border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 20px;">
                <h3 style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-file-download" style="color: var(--primary);"></i> 
                    Official Templates
                </h3>
                <p style="font-size: 0.9rem; color: #64748b; margin-top: 5px;">
                    Please download, print, and ensure these are signed by your industry supervisor.
                </p>
            </div>
            
            <div class="template-grid">
                <a href="<?php echo BASE_URL; ?>api/download_template.php?file=weekly_log_template.pdf" class="download-btn">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-file-pdf"></i>
                        <div>
                            <span style="display: block; font-weight: 600;">Weekly Logbook Template</span>
                            <small style="color: #64748b;">Format: PDF | Size: 1.2MB</small>
                        </div>
                    </div>
                    <i class="fas fa-download" style="color: var(--primary);"></i>
                </a>

                <a href="<?php echo BASE_URL; ?>api/download_template.php?file=final_evaluation_form.pdf" class="download-btn">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <i class="fas fa-file-pdf"></i>
                        <div>
                            <span style="display: block; font-weight: 600;">Final Performance Evaluation</span>
                            <small style="color: #64748b;">Format: PDF | Size: 850KB</small>
                        </div>
                    </div>
                    <i class="fas fa-download" style="color: var(--primary);"></i>
                </a>
            </div>
        </div>

        <div style="background: #fff9eb; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 8px;">
            <p style="color: #92400e; font-size: 0.9rem; margin: 0;">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> 
                After completing these documents, please head over to the <strong>"Submit Evidence"</strong> section in the sidebar to upload your signed scans.
            </p>
        </div>
    </div>

</body>
</html>