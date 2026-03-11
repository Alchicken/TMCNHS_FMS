<?php
// ADMIN_SIDE/download_file.php
// -------------------------------------------------------
// BEFORE  (old — no approval): admin downloads directly
// AFTER   (new — with approval gate):
//   Step 1: Admin GETs this page without ?approved_req → creates request → redirect to pending
//   Step 2: Super Admin approves in their dashboard
//   Step 3: Admin returns with ?approved_req=<id>       → token validated → file served
// -------------------------------------------------------
require_once '../Config.php';
require_once '../helpers/ActionRequest.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php'); exit;
}

$ar         = new ActionRequest($conn);
$documentId = (int)($_GET['doc_id'] ?? 0);

if (!$documentId) { die('Invalid document ID.'); }

// Fetch document record
$stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->bind_param('i', $documentId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();
if (!$doc) { die('Document not found.'); }

// ── STEP 3: Admin is returning with an approved request token ──────────────
if (isset($_GET['approved_req'])) {
    $approvedReqId = (int)$_GET['approved_req'];

    $row = $ar->validateAndConsume($approvedReqId, $_SESSION['user_id'], 'download_file');

    if (!$row) {
        // Token invalid, expired, or already used
        die('
        <div style="font-family:sans-serif;max-width:480px;margin:80px auto;text-align:center;padding:40px;border:1.5px solid #ffc9d0;border-radius:16px;background:#fff0f2">
            <div style="font-size:40px;margin-bottom:16px">⏰</div>
            <h2 style="color:#c8102e;margin-bottom:10px">Approval Token Expired or Invalid</h2>
            <p style="color:#7a5a5e;margin-bottom:24px">This approval has expired (15-minute window) or was already used. Please request approval again.</p>
            <a href="download_file.php?doc_id=' . $documentId . '" style="background:#c8102e;color:#fff;padding:12px 28px;border-radius:10px;text-decoration:none;font-weight:600">Request Again</a>
        </div>');
    }

    // ✅ Approved and token valid — serve the file
    $ar->log($_SESSION['user_id'], $_SESSION['role'], 'downloaded_file',
        "Downloaded: {$doc['title']} (ID {$doc['id']}) via request #{$approvedReqId}");

    $filePath = '../' . $doc['file_path'];
    if (!file_exists($filePath)) { die('File not found on server.'); }

    header('Content-Type: ' . ($doc['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-store');
    readfile($filePath);
    exit;
}

// ── STEP 1: Admin clicked download — submit approval request ──────────────
// Super admins bypass approval
if ($_SESSION['role'] === 'super_admin') {
    $ar->log($_SESSION['user_id'], 'super_admin', 'downloaded_file', "Downloaded: {$doc['title']} (ID {$doc['id']})");
    $filePath = '../' . $doc['file_path'];
    if (!file_exists($filePath)) { die('File not found.'); }
    header('Content-Type: ' . ($doc['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . addslashes($doc['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Create the approval request
$requestId = $ar->createRequest(
    $_SESSION['user_id'],
    'download_file',
    'Download "' . $doc['title'] . '"',
    $doc['id'],
    'document',
    ['filename' => $doc['file_name'], 'path' => $doc['file_path']]
);

$ar->log($_SESSION['user_id'], 'admin', 'requested_download',
    "Requested download of: {$doc['title']} (ID {$doc['id']})");

// Redirect to pending page. After approval, super admin's dashboard sends the admin
// back to this URL with ?approved_req=<id>
$returnUrl = urlencode("download_file.php?doc_id=$documentId");
header("Location: approval_pending.php?req=$requestId&return=download_file.php%3Fdoc_id%3D$documentId");
exit;