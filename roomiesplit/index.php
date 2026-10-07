<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$userId = (int)$_SESSION['user_id'];

// Calculate balances
$balanceData = calculateNetBalances($pdo);
$totalExpenses = $balanceData['total_expenses'];
$fairShare = $balanceData['per_person_share'];
$userBalances = $balanceData['users'];
$pairwiseDebts = $balanceData['pairwise_debts'];

// Current user stats
$myStats = null;
foreach ($userBalances as $u) {
    if ($u['id'] == $userId) {
        $myStats = $u;
        break;
    }
}

// Fetch all expenses with payer name
$expStmt = $pdo->query("
    SELECT e.*, u.name as payer_name
    FROM expenses e
    JOIN users u ON e.payer_id = u.id
    ORDER BY e.expense_date DESC, e.id DESC
");
$expenses = $expStmt->fetchAll();

$categories = ['groceries', 'utilities', 'rent', 'food', 'entertainment', 'general'];
$msg = $_GET['msg'] ?? '';
$pageTitle = "Expenses Dashboard";
require_once 'includes/header.php';
?>

<!-- Notification messages -->
<?php if ($msg === 'added'): ?>
    <div class="alert alert-success">
        Expense recorded and split successfully.
    </div>
<?php elseif ($msg === 'settled'): ?>
    <div class="alert alert-success">
        Settlement payment logged.
    </div>
<?php elseif ($msg === 'optimized'): ?>
    <div class="alert alert-success">
        Receipt image thumbnail generated.
    </div>
<?php endif; ?>

<!-- Dashboard Summary Stat Cards -->
<section class="stats-overview">
    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-label">Total Household Cost</span>
            <span class="stat-value">$<?= number_format($totalExpenses, 2) ?></span>
            <span class="stat-sub"><?= count($expenses) ?> expenses logged</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-label">Your Fair Share</span>
            <span class="stat-value">$<?= number_format($fairShare, 2) ?></span>
            <span class="stat-sub">Split equally across <?= $balanceData['roommate_count'] ?> roommates</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-content">
            <span class="stat-label">You Paid</span>
            <span class="stat-value">$<?= number_format($myStats['total_paid'] ?? 0, 2) ?></span>
            <span class="stat-sub">Direct out-of-pocket payments</span>
        </div>
    </div>

    <div class="stat-card <?= ($myStats['net_balance'] ?? 0) >= 0 ? 'card-positive' : 'card-negative' ?>">
        <div class="stat-content">
            <span class="stat-label">Your Net Balance</span>
            <span class="stat-value">
                <?= ($myStats['net_balance'] ?? 0) >= 0 ? '+' : '' ?>$<?= number_format($myStats['net_balance'] ?? 0, 2) ?>
            </span>
            <span class="stat-sub">
                <?php if (($myStats['net_balance'] ?? 0) > 0.01): ?>
                    You are owed money
                <?php elseif (($myStats['net_balance'] ?? 0) < -0.01): ?>
                    You owe roommates
                <?php else: ?>
                    All settled up
                <?php endif; ?>
            </span>
        </div>
    </div>
</section>

<!-- Quick Action & Filter Controls -->
<section class="ledger-header-controls">
    <div class="filter-pills">
        <span class="filter-heading">Category:</span>
        <a href="#" class="chip <?= !isset($_SERVER['QUERY_STRING']) ? 'active' : '' ?>" onclick="location.hash=''; return false;">All</a>
        <a href="#groceries" class="chip">Groceries</a>
        <a href="#utilities" class="chip">Utilities</a>
        <a href="#rent" class="chip">Rent</a>
        <a href="#food" class="chip">Food</a>
        <a href="#entertainment" class="chip">Entertainment</a>
        <a href="#general" class="chip">General</a>
    </div>

    <div class="action-buttons">
        <a href="add_expense.php" class="btn btn-primary">Add Expense</a>
        <a href="import_receipt.php" class="btn btn-secondary">Import Receipt</a>
        <a href="settle_action.php" class="btn btn-secondary">Settle Debts</a>
    </div>
</section>

<!-- Dynamic Hash-Based Search & Filter Banner matching specification -->
<div id="filter-banner"></div>

<!-- Expenses Ledger Table -->
<section class="card ledger-card">
    <div class="card-header">
        <h2>Expense Ledger</h2>
        <span class="text-muted"><?= count($expenses) ?> records</span>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="empty-state">
            <p>No expenses logged yet. Click "Add Expense" above to record one.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="data-table ledger-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Payer</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $row): ?>
                        <tr class="expense-row" data-category="<?= htmlspecialchars($row['category']) ?>">
                            <td class="col-date"><?= htmlspecialchars($row['expense_date']) ?></td>
                            <td class="col-title">
                                <strong><?= htmlspecialchars($row['title']) ?></strong>
                            </td>
                            <td class="col-category">
                                <a href="#<?= htmlspecialchars($row['category']) ?>" class="category-badge category-<?= htmlspecialchars($row['category']) ?>">
                                    <?= htmlspecialchars(ucfirst($row['category'])) ?>
                                </a>
                            </td>
                            <td class="col-payer">
                                <span class="payer-badge <?= $row['payer_id'] == $userId ? 'payer-me' : '' ?>">
                                    <?= htmlspecialchars($row['payer_name']) ?> <?= $row['payer_id'] == $userId ? '(You)' : '' ?>
                                </span>
                            </td>
                            <td class="col-amount">$<?= number_format($row['amount'], 2) ?></td>
                            <td class="col-receipt">
                                <?php if (!empty($row['receipt_filename'])): ?>
                                    <div class="receipt-cell">
                                        <a href="view_receipt.php?file=<?= urlencode($row['receipt_filename']) ?>" target="_blank" class="receipt-link" title="Stream Receipt File">
                                            View Receipt
                                        </a>
                                        <?php if (file_exists('uploads/thumb_' . $row['receipt_filename'])): ?>
                                            <a href="view_receipt.php?file=thumb_<?= urlencode($row['receipt_filename']) ?>" target="_blank" class="thumb-pill">
                                                (Thumb)
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted text-sm">None</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-actions">
                                <?php if (!empty($row['receipt_filename'])): ?>
                                    <!-- Optimize Receipt Form matching spec -->
                                    <form action="optimize_receipt.php" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="filename" value="<?= htmlspecialchars($row['receipt_filename']) ?>">
                                        <button type="submit" class="btn-micro" title="Generate compressed thumbnail">
                                            Optimize
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="add_expense.php" class="btn-micro text-muted">Add</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- Active Roommates Balances Grid -->
<section class="roommates-summary-grid">
    <div class="card">
        <div class="card-header">
            <h2>Roommate Net Balances</h2>
            <a href="api/balances.php" target="_blank" class="btn-link">JSON Feed &rarr;</a>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Roommate</th>
                        <th>Paid Out</th>
                        <th>Fair Share</th>
                        <th>Net Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userBalances as $ub): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ub['name']) ?></strong>
                                <?= $ub['id'] == $userId ? '<span class="tag-you">You</span>' : '' ?>
                            </td>
                            <td>$<?= number_format($ub['total_paid'], 2) ?></td>
                            <td>$<?= number_format($ub['fair_share'], 2) ?></td>
                            <td class="<?= $ub['net_balance'] >= 0 ? 'text-positive' : 'text-negative' ?>">
                                <?= $ub['net_balance'] >= 0 ? '+' : '' ?>$<?= number_format($ub['net_balance'], 2) ?>
                            </td>
                            <td>
                                <?php if ($ub['status'] === 'gets_back'): ?>
                                    <span class="badge badge-success">Gets Back</span>
                                <?php elseif ($ub['status'] === 'owes'): ?>
                                    <span class="badge badge-danger">Owes</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Outstanding Pairwise Debts with Quick Settle -->
    <div class="card">
        <div class="card-header">
            <h2>Open Debts</h2>
            <a href="settle_action.php" class="btn-link">Manage Settlements &rarr;</a>
        </div>
        <?php if (empty($pairwiseDebts)): ?>
            <div class="empty-state">
                <p>All roommates are settled up.</p>
            </div>
        <?php else: ?>
            <div class="debts-compact-list">
                <?php foreach ($pairwiseDebts as $d): ?>
                    <div class="debt-compact-item">
                        <div class="debt-compact-desc">
                            <span class="debtor"><?= htmlspecialchars($d['debtor_name']) ?></span>
                            <span class="arrow">owes</span>
                            <span class="creditor"><?= htmlspecialchars($d['creditor_name']) ?></span>
                        </div>
                        <div class="debt-compact-right">
                            <span class="amount">$<?= number_format($d['amount'], 2) ?></span>
                            <!-- One-Click Settle button matching spec -->
                            <form action="settle_action.php" method="POST" style="margin:0;">
                                <input type="hidden" name="debtor_id" value="<?= (int)$d['debtor_id'] ?>">
                                <input type="hidden" name="receiver_id" value="<?= (int)$d['creditor_id'] ?>">
                                <input type="hidden" name="amount" value="<?= htmlspecialchars($d['amount']) ?>">
                                <button type="submit" class="btn-settle">One-Click Settle</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Client-Side Features & Frontend Logic: Dynamic Hash-Based Search & Filter Banner -->
<script>
  function applyHashFilter() {
    const hashValue = window.location.hash.substring(1);
    const banner = document.getElementById('filter-banner');

    // Highlight active chip
    document.querySelectorAll('.filter-pills .chip').forEach(chip => {
        const href = chip.getAttribute('href');
        if ((!hashValue && href === '#') || (hashValue && href === '#' + hashValue)) {
            chip.classList.add('active');
        } else {
            chip.classList.remove('active');
        }
    });

    if (hashValue) {
      banner.innerHTML = "Active Category Filter: <strong>" + decodeURIComponent(hashValue) + "</strong>";

      // Filter rows matching category
      document.querySelectorAll('.expense-row').forEach(row => {
        row.style.display = row.dataset.category.toLowerCase() === hashValue.toLowerCase() ? '' : 'none';
      });
    } else {
      banner.innerHTML = "";
      document.querySelectorAll('.expense-row').forEach(row => row.style.display = '');
    }
  }

  window.addEventListener('hashchange', applyHashFilter);
  window.addEventListener('DOMContentLoaded', applyHashFilter);
</script>

<?php require_once 'includes/footer.php'; ?>
