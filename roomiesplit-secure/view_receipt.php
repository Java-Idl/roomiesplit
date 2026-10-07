<?php
// VULNERABILITY MITIGATION: Path Traversal
// - Vulnerable lines (old): $path = 'uploads/' . $file;
// - Mitigation lines: 
//   - $file = basename($_GET['file'] ?? '');
//   - $realBase = realpath('uploads/'); $realPath = realpath($path);
//   - strpos($realPath, $realBase) === 0
//   - in_array($ext, $allowed_exts)
require_once 'includes/auth_check.php';
$file = basename($_GET['file'] ?? '');
$path = 'uploads/' . $file;
$realBase = realpath('uploads');
$realPath = realpath($path);

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt'];

if (!in_array($ext, $allowed_exts)) {
    http_response_code(403);
    echo "Invalid file extension.";
    exit;
}

if ($realPath && $realBase && strpos($realPath, $realBase) === 0 && file_exists($realPath) && is_file($realPath)) {
    $mime = function_exists('mime_content_type') ? @mime_content_type($realPath) : false;
    if (!$mime) {
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
    readfile($realPath);
    exit;
}
http_response_code(404);
echo "File not found.";
