    </main>
    <footer class="app-footer">
        <div class="footer-container">
            <p>&copy; <?= date('Y') ?> <strong>RoomieSplit</strong> &bull; Seamless Roommate Expense Sharing &amp; Receipts Ledger</p>
            <div class="footer-links">
                <a href="<?= $basePrefix ?>index.php">Dashboard</a>
                <span>&bull;</span>
                <a href="<?= $basePrefix ?>settle_action.php">Settlements</a>
                <span>&bull;</span>
                <a href="<?= $basePrefix ?>api/balances.php" target="_blank">Balance Feed (JSON)</a>
            </div>
        </div>
    </footer>
</body>
</html>
