<?php
// ADMIN_SIDE/download_scan.php
// Approval-gated download for leave report scan files.
// Works identically to download_file.php but targets a raw file path
// instead of a documents table row.
require_once 'admin_shared.php';
require_once '../helpers/ActionRequest.php';

$ar       = new ActionRequest($conn);
$filePath = $_GET['file_path'] ?? '';
$fileName = $_GET['file_name'] ?? basename($filePath);

if (!$filePath) { die('No file specified.'); }

// Sanitize — only allow paths inside uploads/
$filePath = str_replace(['../', '..\\'], '', $filePath);
if (strpos($filePath, 'uploads/') !== 0 && strpos($filePath, '/uploads/') === false) {
    die('Invalid file path.');
}

// ── Super admin: download directly, no approval needed ──────────────────────
if ($_SESSION['role'] === 'super_admin') {
    $ar->log($_SESSION['user_id'], 'super_admin', 'downloaded_scan', "Downloaded scan: $fileName");
    $abs = '../' . ltrim($filePath, '/');
    if (!file_exists($abs)) { die('File not found on server.'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: no-store');
    readfile($abs);
    exit;
}

// ── Admin returning with an approved token ───────────────────────────────────
if (isset($_GET['approved_req'])) {
    $approvedReqId = (int)$_GET['approved_req'];
    $row = $ar->validateAndConsume($approvedReqId, $_SESSION['user_id'], 'download_file');

    if (!$row) {
        die('
        <div style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;padding:40px;border:1.5px solid #ffc9d0;border-radius:16px;background:#fff0f2">
            <div style="font-size:40px;margin-bottom:16px">⏰</div>
            <h2 style="color:#c8102e;margin-bottom:10px">Approval Token Expired or Invalid</h2>
            <p style="color:#7a5a5e;margin-bottom:24px">This approval has expired or was already used. Please request again.</p>
            <a href="documents_library.php" style="background:#c8102e;color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600">Back to Documents</a>
        </div>');
    }

    $ar->log($_SESSION['user_id'], 'admin', 'downloaded_scan',
        "Downloaded scan: $fileName via request #{$approvedReqId}");

    $abs = '../' . ltrim($filePath, '/');
    if (!file_exists($abs)) { die('File not found on server.'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: no-store');
    readfile($abs);
    exit;
}

// ── Step 1: Create approval request and redirect to pending page ─────────────
$requestId = $ar->createRequest(
    $_SESSION['user_id'],
    'download_file',
    'Download leave scan: "' . $fileName . '"',
    null,
    'leave_scan',
    ['file_path' => $filePath, 'file_name' => $fileName]
);

$ar->log($_SESSION['user_id'], 'admin', 'requested_scan_download',
    "Requested download of scan: $fileName");

// Encode the return URL so approval_pending.php knows where to send the admin after approval
$returnUrl = urlencode('download_scan.php?file_path=' . urlencode($filePath) . '&file_name=' . urlencode($fileName));
header("Location: approval_pending.php?req={$requestId}&return=" . $returnUrl);
exit;