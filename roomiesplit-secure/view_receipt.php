<?php
require_once 'includes/auth_check.php';
$file = $_GET['file'] ?? '';
$path = 'uploads/' . $file;

if (file_exists($path) && is_file($path)) {
    $mime = function_exists('mime_content_type') ? @mime_content_type($path) : false;
    if (!$mime) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain'
        ];
        $mime = $map[$ext] ?? 'application/octet-stream';
    }
    header("Content-Type: " . $mime);
    readfile($path);
    exit;
}
http_response_code(404);
echo "File not found.";
