<?php
require_once 'includes/auth_check.php';
require_once 'config/db.php';

$error = '';
$prefilledReceipt = $_GET['receipt_name'] ?? '';

// Fetch all roommates for payer dropdown
$usersStmt = $pdo->query("SELECT id, name FROM users ORDER BY name ASC");
$allUsers = $usersStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $payer_id = (int)($_POST['payer_id'] ?? $_SESSION['user_id']);
    $receipt_filename = trim($_POST['prefilled_receipt'] ?? null);

    if (empty($title)) {
        $error = "Please enter an expense title.";
    } elseif ($amount <= 0) {
        $error = "Please enter a valid amount greater than zero.";
    } else {
        // Handle local file upload if present
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK && $_FILES['receipt']['size'] > 0) {
            $origName = basename($_FILES['receipt']['name']);
            $receipt_filename = 'receipt_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $origName);
            $destination = 'uploads/' . $receipt_filename;

            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $destination)) {
                $error = "Failed to save uploaded receipt image.";
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (payer_id, title, category, amount, receipt_filename, expense_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $payer_id,
                $title,
                $category,
                $amount,
                !empty($receipt_filename) ? $receipt_filename : null,
                $expense_date
            ]);

            header("Location: index.php?msg=added");
            exit;
        }
    }
}

$pageTitle = "Add Expense";
require_once 'includes/header.php';
?>

<div class="content-header">
    <div>
        <h1 class="page-title">Add Expense</h1>
        <p class="page-subtitle">Record a shared cost and optional receipt file.</p>
    </div>
    <div class="header-actions">
        <a href="import_receipt.php" class="btn btn-secondary">Import Receipt via URL</a>
    </div>
</div>

<div class="form-container">
    <div class="card form-card">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form action="add_expense.php" method="POST" enctype="multipart/form-data" class="expense-form">
            <div class="form-group">
                <label for="title">Description</label>
                <input type="text" id="title" name="title" placeholder="Groceries, Electricity Bill, Cleaning Supplies" required autofocus>
            </div>

            <div class="form-row">
                <div class="form-group col-half">
                    <label for="amount">Amount ($)</label>
                    <div class="input-with-prefix">
                        <span class="currency-prefix">$</span>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                    </div>
                </div>

                <div class="form-group col-half">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="groceries">Groceries</option>
                        <option value="utilities">Utilities</option>
                        <option value="rent">Rent</option>
                        <option value="food">Food</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="general" selected>General</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-half">
                    <label for="payer_id">Paid By</label>
                    <select id="payer_id" name="payer_id">
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $u['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> <?= $u['id'] == $_SESSION['user_id'] ? '(You)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-half">
                    <label for="expense_date">Date</label>
                    <input type="date" id="expense_date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="receipt">Receipt File (Optional)</label>
                <?php if (!empty($prefilledReceipt)): ?>
                    <div class="attached-pill">
                        <span>Pre-imported receipt: <strong><?= htmlspecialchars($prefilledReceipt) ?></strong></span>
                        <input type="hidden" name="prefilled_receipt" value="<?= htmlspecialchars($prefilledReceipt) ?>">
                    </div>
                <?php endif; ?>
                <div class="file-drop-area">
                    <input type="file" id="receipt" name="receipt" accept="image/*,application/pdf">
                    <p class="file-hint">Select a local receipt file (JPG, PNG, PDF)</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Expense</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
