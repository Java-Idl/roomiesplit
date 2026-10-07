<?php
require_once '../config/db.php';
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please provide both email and password.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Authentication successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header("Location: ../index.php");
            exit;
        } else {
            $error = "Invalid email or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - RoomieSplit</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-brand">
                    <span class="logo-mark">RS</span>
                    <span class="logo-text">Roomie<strong>Split</strong></span>
                </div>
                <h1>Sign In</h1>
                <p>Manage shared household expenses and balances.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="auth-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="roommate@example.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="auth-demo-hint">
                <p><strong>Demo Roommate Accounts:</strong></p>
                <div class="demo-buttons">
                    <button type="button" class="btn-demo" onclick="fillLogin('alex@roomiesplit.local', 'password123')">Alex (alex@roomiesplit.local)</button>
                    <button type="button" class="btn-demo" onclick="fillLogin('jordan@roomiesplit.local', 'password123')">Jordan (jordan@roomiesplit.local)</button>
                    <button type="button" class="btn-demo" onclick="fillLogin('taylor@roomiesplit.local', 'password123')">Taylor (taylor@roomiesplit.local)</button>
                </div>
            </div>

            <div class="auth-footer">
                <p>Do not have an account? <a href="register.php">Create an account</a></p>
            </div>
        </div>
    </div>

    <script>
        function fillLogin(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
