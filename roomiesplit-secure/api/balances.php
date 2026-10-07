<?php
// VULNERABILITY MITIGATION: CORS Misconfiguration
// - Vulnerable lines (old): header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
// - Mitigation lines:
//   - $allowed_origins = ['http://localhost:8000', 'http://127.0.0.1:8000'];
//   - if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) { header(...); }
require_once '../config/db.php';
session_start();

$allowed_origins = [
    'http://localhost:8000',
    'http://127.0.0.1:8000'
];

if (isset($_SERVER['HTTP_ORIGIN'])) {
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json');

// Compute and output net balances
$balances = calculateNetBalances($pdo);
echo json_encode($balances, JSON_PRETTY_PRINT);
