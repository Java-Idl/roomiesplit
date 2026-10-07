<?php
// VULNERABILITY MITIGATION: Clickjacking & CSRF
// - Vulnerable lines (old): missing headers and CSRF validation
// - Mitigation lines:
//   - header("X-Frame-Options: DENY");
//   - header("Content-Security-Policy: frame-ancestors 'none'");
//   - CSRF generation and validation
require_once 'includes/auth_check.php';
require_once 'config/db.php';

header("X-Frame-Options: DENY");
header("Content-Security-Policy: frame-ancestors 'none'");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    $debtor_id = (int)($_POST['debtor_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);

    // If receiver_id not passed, default to currently logged-in user or debtor counterpart
    if ($receiver_id === 0) {
        $receiver_id = (int)$_SESSION['user_id'];
    }

    // Safety fallback: if debtor is the current user, ensure receiver is not debtor
    if ($debtor_id === $receiver_id) {
        $otherStmt = $pdo->prepare("SELECT id FROM users WHERE id != ? LIMIT 1");
        $otherStmt->execute([$debtor_id]);
        $receiver_id = (int)$otherStmt->fetchColumn();
    }

    if ($debtor_id > 0 && $receiver_id > 0 && $amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO settlements (payer_id, receiver_id, amount, settled_date)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$debtor_id, $receiver_id, $amount, date('Y-m-d')]);

        header("Location: index.php?msg=settled");
        exit;
    }
}

// Balance calculations for the view
$balances = calculateNetBalances($pdo);
$pairwiseDebts = $balances['pairwise_debts'] ?? [];

// Fetch settlements history
$histStmt = $pdo->query("
    SELECT s.*, u1.name as payer_name, u2.name as receiver_name
    FROM settlements s
    JOIN users u1 ON s.payer_id = u1.id
    JOIN users u2 ON s.receiver_id = u2.id
    ORDER BY s.created_at DESC
    LIMIT 10
");
$settlementHistory = $histStmt->fetchAll();

$pageTitle = "Settle Balances";
require_once 'includes/header.php';
?>

<div class="content-header">
    <div>
        <h1 class="page-title">Settle Debts</h1>
        <p class="page-subtitle">Record payments made between roommates to clear open debts.</p>
    </div>
</div>

<div class="settle-grid">
    <!-- Active Outstanding Debts -->
    <div class="card">
        <div class="card-header">
            <h2>Outstanding Debts</h2>
            <span class="badge badge-info"><?= count($pairwiseDebts) ?> Open</span>
        </div>

        <?php if (empty($pairwiseDebts)): ?>
            <div class="empty-state">
                <p>No outstanding debts between roommates.</p>
            </div>
        <?php else: ?>
            <div class="debts-list">
                <?php foreach ($pairwiseDebts as $debt): ?>
                    <div class="debt-card-item">
                        <div class="debt-info">
                            <div class="debt-avatars">
                                <span class="avatar-small"><?= strtoupper(substr($debt['debtor_name'], 0, 1)) ?></span>
                                <span class="arrow-indicator">&rarr;</span>
                                <span class="avatar-small creditor"><?= strtoupper(substr($debt['creditor_name'], 0, 1)) ?></span>
                            </div>
                            <div class="debt-details">
                                <p class="debt-text">
                                    <strong><?= htmlspecialchars($debt['debtor_name']) ?></strong> owes <strong><?= htmlspecialchars($debt['creditor_name']) ?></strong>
                                </p>
                                <span class="debt-amount">$<?= number_format($debt['amount'], 2) ?></span>
                            </div>
                        </div>

                        <!-- One-Click Debt Settlement Form matching spec -->
                        <form action="settle_action.php" method="POST" class="settle-form-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="debtor_id" value="<?= (int)$debt['debtor_id'] ?>">
                            <input type="hidden" name="receiver_id" value="<?= (int)$debt['creditor_id'] ?>">
                            <input type="hidden" name="amount" value="<?= htmlspecialchars($debt['amount']) ?>">
                            <button type="submit" class="btn-settle">One-Click Settle All</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Settlement History -->
    <div class="card">
        <div class="card-header">
            <h2>Settlement History</h2>
        </div>

        <?php if (empty($settlementHistory)): ?>
            <div class="empty-state">
                <p>No settlement records logged yet.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Payer</th>
                            <th>Receiver</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settlementHistory as $sh): ?>
                            <tr>
                                <td><?= htmlspecialchars($sh['settled_date']) ?></td>
                                <td><?= htmlspecialchars($sh['payer_name']) ?></td>
                                <td><?= htmlspecialchars($sh['receiver_name']) ?></td>
                                <td class="amount-settled">$<?= number_format($sh['amount'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
