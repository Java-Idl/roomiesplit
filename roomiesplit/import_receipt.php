<?php
require_once 'includes/auth_check.php';

// POST Handler: Remote Receipt Downloader via URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['receipt_url'] ?? '';
    $filename = 'remote_' . time() . '.jpg';
    $fileData = @file_get_contents($url);

    if ($fileData !== false) {
        file_put_contents('uploads/' . $filename, $fileData);
        // Return filename for expense attachment
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'filename' => $filename]);
        exit;
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve file from URL']);
        exit;
    }
}

// GET Handler: Web interface
$pageTitle = "Import Receipt";
require_once 'includes/header.php';
?>

<div class="content-header">
    <div>
        <h1 class="page-title">Import Remote Receipt</h1>
        <p class="page-subtitle">Fetch an external receipt or document from a web address.</p>
    </div>
</div>

<div class="form-container">
    <div class="card form-card">
        <div id="import-alert" class="alert" style="display: none;"></div>

        <form id="remoteReceiptForm" onsubmit="handleImport(event)">
            <div class="form-group">
                <label for="receipt_url">Receipt File URL</label>
                <input type="text" id="receipt_url" name="receipt_url" placeholder="https://example.com/receipt.jpg" required>
                <small class="form-help">Enter a public URL to download and save locally.</small>
            </div>

            <div class="quick-examples">
                <span class="text-muted">Preset URLs:</span>
                <button type="button" class="btn-chip" onclick="setUrl('http://127.0.0.1:8000/uploads/sample_receipt_groceries.jpg')">Local Receipt</button>
                <button type="button" class="btn-chip" onclick="setUrl('https://raw.githubusercontent.com/mdn/learning-area/master/html/multimedia-and-embedding/images-in-html/dinosaur.jpg')">External Image</button>
            </div>

            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" id="submitBtn" class="btn btn-primary">
                    <span id="btnText">Download Receipt</span>
                    <span id="btnSpinner" class="spinner" style="display:none;"></span>
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <div id="resultPreview" class="result-box" style="display: none; margin-top: 1.5rem;">
            <h3>Import Complete</h3>
            <p>File saved as: <code id="savedFilename"></code></p>
            <div class="result-actions" style="margin-top: 1rem; display: flex; gap: 0.75rem;">
                <a id="viewLink" href="#" target="_blank" class="btn btn-secondary">View Streamed File</a>
                <a id="attachLink" href="#" class="btn btn-primary">Create Expense with Receipt</a>
            </div>
        </div>
    </div>
</div>

<script>
function setUrl(url) {
    document.getElementById('receipt_url').value = url;
}

async function handleImport(e) {
    e.preventDefault();
    const urlInput = document.getElementById('receipt_url');
    const alertBox = document.getElementById('import-alert');
    const resultBox = document.getElementById('resultPreview');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    alertBox.style.display = 'none';
    resultBox.style.display = 'none';
    submitBtn.disabled = true;
    btnText.textContent = "Downloading...";
    btnSpinner.style.display = 'inline-block';

    const formData = new FormData();
    formData.append('receipt_url', urlInput.value);

    try {
        const res = await fetch('import_receipt.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (res.ok && data.status === 'success') {
            resultBox.style.display = 'block';
            document.getElementById('savedFilename').textContent = data.filename;
            document.getElementById('viewLink').href = 'view_receipt.php?file=' + encodeURIComponent(data.filename);
            document.getElementById('attachLink').href = 'add_expense.php?receipt_name=' + encodeURIComponent(data.filename);
        } else {
            alertBox.className = 'alert alert-error';
            alertBox.textContent = data.message || 'Failed to download receipt.';
            alertBox.style.display = 'block';
        }
    } catch (err) {
        alertBox.className = 'alert alert-error';
        alertBox.textContent = 'Error during import: ' + err.message;
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        btnText.textContent = "Download Receipt";
        btnSpinner.style.display = 'none';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
