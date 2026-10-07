<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$basePrefix = file_exists('style.css') ? '' : '../';
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Roommate');
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - RoomieSplit' : 'RoomieSplit - Expense and Receipt Manager' ?></title>
    <link rel="stylesheet" href="<?= $basePrefix ?>style.css">
</head>
<body>
    <header class="app-header">
        <div class="nav-container">
            <a href="<?= $basePrefix ?>index.php" class="brand-logo">
                <span class="logo-mark">RS</span>
                <span class="logo-text">Roomie<strong>Split</strong></span>
            </a>

            <nav class="main-nav">
                <a href="<?= $basePrefix ?>index.php" class="nav-link <?= $currentScript === 'index.php' ? 'active' : '' ?>">
                    Dashboard
                </a>
                <a href="<?= $basePrefix ?>add_expense.php" class="nav-link <?= $currentScript === 'add_expense.php' ? 'active' : '' ?>">
                    Add Expense
                </a>
                <a href="<?= $basePrefix ?>import_receipt.php" class="nav-link <?= $currentScript === 'import_receipt.php' ? 'active' : '' ?>">
                    Import Receipt
                </a>
                <a href="<?= $basePrefix ?>settle_action.php" class="nav-link <?= $currentScript === 'settle_action.php' ? 'active' : '' ?>">
                    Settlements
                </a>
                <a href="<?= $basePrefix ?>api/balances.php" target="_blank" class="nav-link">
                    API Balances
                </a>
            </nav>

            <div class="user-profile-menu">
                <div class="user-badge">
                    <span class="user-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                    <span class="user-name"><?= $userName ?></span>
                </div>
                <a href="<?= $basePrefix ?>auth/logout.php" class="btn-logout" title="Sign Out">Sign Out</a>
            </div>
        </div>
    </header>
    <main class="main-wrapper">
