<?php
// RoomieSplit - Database Configuration and Connection Handler

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'roomiesplit';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connect to server (without DB initially to ensure it exists)
    $serverPdo = new PDO($dsn, $user, $pass, $options);
    
    // Auto-create database if it does not exist
    $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Connect to specific database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass, $options);

    // Initialize schema tables if they don't exist
    initDatabaseSchema($pdo);
    
} catch (PDOException $e) {
    // If running in development without live MySQL, fallback message or error
    die("Database Connection Error: " . $e->getMessage());
}

/**
 * Initializes database schema according to specifications
 */
function initDatabaseSchema($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payer_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            amount DECIMAL(10, 2) NOT NULL,
            receipt_filename VARCHAR(255) NULL,
            expense_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;

        CREATE TABLE IF NOT EXISTS settlements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payer_id INT NOT NULL,
            receiver_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            settled_date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB;
    ");

    // Seed default sample roommates if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $rowCount = $stmt->fetch()['count'] ?? 0;
    if ($rowCount == 0) {
        $samplePassword = password_hash('password123', PASSWORD_BCRYPT);
        $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
        $insert->execute(['Alex Rivera', 'alex@roomiesplit.local', $samplePassword]);
        $insert->execute(['Jordan Lee', 'jordan@roomiesplit.local', $samplePassword]);
        $insert->execute(['Taylor Swift', 'taylor@roomiesplit.local', $samplePassword]);

        // Seed initial expenses
        $u1 = 1; $u2 = 2; $u3 = 3;
        $exp = $pdo->prepare("INSERT INTO expenses (payer_id, title, category, amount, expense_date, receipt_filename) VALUES (?, ?, ?, ?, ?, ?)");
        $exp->execute([$u1, 'Weekly Trader Joe\'s Groceries', 'groceries', 135.00, date('Y-m-d', strtotime('-4 days')), 'sample_receipt_groceries.jpg']);
        $exp->execute([$u2, 'High-Speed Fiber Internet Bill', 'utilities', 75.00, date('Y-m-d', strtotime('-2 days')), null]);
        $exp->execute([$u1, 'Bulk Paper Towels & Cleaning Detergent', 'groceries', 45.00, date('Y-m-d', strtotime('-1 days')), null]);
        $exp->execute([$u3, 'Living Room Couch Cleaning Fee', 'general', 90.00, date('Y-m-d'), null]);
    }
}

/**
 * Calculates net balances and pairwise debts across all roommates
 */
function calculateNetBalances($pdo) {
    // 1. Fetch all users
    $stmt = $pdo->query("SELECT id, name, email FROM users ORDER BY id ASC");
    $users = $stmt->fetchAll();
    $userCount = count($users);
    
    if ($userCount === 0) {
        return [
            'total_expenses' => 0,
            'per_person_share' => 0,
            'users' => [],
            'pairwise_debts' => []
        ];
    }

    $userIds = array_column($users, 'id');
    $userMap = [];
    foreach ($users as $u) {
        $userMap[$u['id']] = $u;
    }

    // 2. Fetch total paid per user in expenses
    $expStmt = $pdo->query("SELECT payer_id, COALESCE(SUM(amount), 0) as total_paid FROM expenses GROUP BY payer_id");
    $paidExpenses = [];
    foreach ($expStmt->fetchAll() as $row) {
        $paidExpenses[$row['payer_id']] = (float)$row['total_paid'];
    }

    // 3. Total expenses in household
    $totalExpStmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as grand_total FROM expenses");
    $grandTotalExpenses = (float)$totalExpStmt->fetch()['grand_total'];
    $perPersonShare = $userCount > 0 ? ($grandTotalExpenses / $userCount) : 0;

    // 4. Fetch settlements: payer paid to receiver
    $settleStmt = $pdo->query("SELECT payer_id, receiver_id, COALESCE(SUM(amount), 0) as settled_amount FROM settlements GROUP BY payer_id, receiver_id");
    $settlementsMap = [];
    $totalSettledPaid = [];
    $totalSettledReceived = [];
    foreach ($settleStmt->fetchAll() as $s) {
        $p = $s['payer_id'];
        $r = $s['receiver_id'];
        $amt = (float)$s['settled_amount'];
        $settlementsMap[$p][$r] = $amt;
        $totalSettledPaid[$p] = ($totalSettledPaid[$p] ?? 0) + $amt;
        $totalSettledReceived[$r] = ($totalSettledReceived[$r] ?? 0) + $amt;
    }

    // 5. Compute net balance for each user
    // Net = (total paid for expenses) - (fair share) + (settled paid to others) - (settled received from others)
    $balances = [];
    foreach ($users as $u) {
        $uid = $u['id'];
        $paid = $paidExpenses[$uid] ?? 0.0;
        $settledOut = $totalSettledPaid[$uid] ?? 0.0;
        $settledIn = $totalSettledReceived[$uid] ?? 0.0;
        
        $net = ($paid - $perPersonShare) + $settledOut - $settledIn;

        $balances[$uid] = [
            'id' => $uid,
            'name' => $u['name'],
            'email' => $u['email'],
            'total_paid' => round($paid, 2),
            'fair_share' => round($perPersonShare, 2),
            'settled_paid' => round($settledOut, 2),
            'settled_received' => round($settledIn, 2),
            'net_balance' => round($net, 2),
            'status' => $net >= 0.005 ? 'gets_back' : ($net <= -0.005 ? 'owes' : 'settled')
        ];
    }

    // 6. Compute pairwise simplified/standard debts
    // For each expense paid by P of amount A: each other roommate owes P: A / userCount.
    // Pairwise debt from X to Y = (X's share of Y's expenses) - (Y's share of X's expenses) - (X settled to Y) + (Y settled to X)
    $pairwise = [];
    for ($i = 0; $i < count($userIds); $i++) {
        for ($j = $i + 1; $j < count($userIds); $j++) {
            $uA = $userIds[$i];
            $uB = $userIds[$j];

            $paidA = $paidExpenses[$uA] ?? 0.0;
            $paidB = $paidExpenses[$uB] ?? 0.0;

            // B owes A for A's expense share
            $bOwesA_raw = $paidA / $userCount;
            // A owes B for B's expense share
            $aOwesB_raw = $paidB / $userCount;

            // Net debt between A and B from expenses
            // settlements paid by B to A reduces B's debt to A
            $settledBtoA = $settlementsMap[$uB][$uA] ?? 0.0;
            $settledAtoB = $settlementsMap[$uA][$uB] ?? 0.0;

            // Net from B -> A
            $netBtoA = ($bOwesA_raw - $aOwesB_raw) - $settledBtoA + $settledAtoB;

            if ($netBtoA > 0.005) {
                $pairwise[] = [
                    'debtor_id' => $uB,
                    'debtor_name' => $userMap[$uB]['name'],
                    'creditor_id' => $uA,
                    'creditor_name' => $userMap[$uA]['name'],
                    'amount' => round($netBtoA, 2)
                ];
            } elseif ($netBtoA < -0.005) {
                $pairwise[] = [
                    'debtor_id' => $uA,
                    'debtor_name' => $userMap[$uA]['name'],
                    'creditor_id' => $uB,
                    'creditor_name' => $userMap[$uB]['name'],
                    'amount' => round(abs($netBtoA), 2)
                ];
            }
        }
    }

    return [
        'total_expenses' => round($grandTotalExpenses, 2),
        'per_person_share' => round($perPersonShare, 2),
        'roommate_count' => $userCount,
        'users' => array_values($balances),
        'pairwise_debts' => $pairwise
    ];
}
