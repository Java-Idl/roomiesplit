<?php
require_once 'includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filename = $_POST['filename'] ?? '';
    $target = "uploads/" . $filename;
    $output = "uploads/thumb_" . $filename;

    // Execute local conversion utility
    shell_exec("convert " . $target . " -resize 300x300 " . $output);
    header("Location: index.php?msg=optimized");
    exit;
}

header("Location: index.php");
exit;
