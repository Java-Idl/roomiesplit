<?php
// VULNERABILITY MITIGATION: Command Injection
// - Vulnerable lines (old): shell_exec("convert " . $target . " -resize 300x300 " . $output);
// - Mitigation lines: 
//   - if (!preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) { ... }
//   - if (!file_exists($target)) { ... }
//   - shell_exec("convert " . escapeshellarg($target) . " -resize 300x300 " . escapeshellarg($output));
require_once 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    
    // MITIGATION: Validate filename strictly against regex
    if (!preg_match('/^[a-zA-Z0-9_\.-]+$/', $filename)) {
        header("Location: index.php?msg=error");
        exit;
    }
    
    $target = "uploads/" . $filename;
    $output = "uploads/thumb_" . $filename;

    // MITIGATION: Verify file exists on disk
    if (!file_exists($target)) {
        header("Location: index.php?msg=error");
        exit;
    }

    // MITIGATION: Execute local conversion utility with escapeshellarg
    shell_exec("convert " . escapeshellarg($target) . " -resize 300x300 " . escapeshellarg($output));
    header("Location: index.php?msg=optimized");
    exit;
}

header("Location: index.php");
exit;
