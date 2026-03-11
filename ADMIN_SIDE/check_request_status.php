<?php
// ADMIN_SIDE/check_request_status.php
// AJAX polling endpoint — returns JSON status for a given request_id
require_once '../Config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$requestId = (int)($_GET['req'] ?? 0);
if (!$requestId) { echo json_encode(['status' => 'error']); exit; }

$stmt = $conn->prepare("
    SELECT status, rejection_note, expires_at
    FROM action_requests
    WHERE id = ? AND admin_user_id = ?
");
$stmt->bind_param('ii', $requestId, $_SESSION['user_id']);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) { echo json_encode(['status' => 'not_found']); exit; }

// Auto-expire check — use MySQL to compare, not PHP time
if ($row['status'] === 'approved' && $row['expires_at']) {
    // Re-check expiry using a pure MySQL query to avoid timezone mismatch
    $expChk = $conn->prepare("SELECT expires_at < NOW() AS is_expired FROM action_requests WHERE id = ?");
    $expChk->bind_param('i', $requestId);
    $expChk->execute();
    $expRow = $expChk->get_result()->fetch_assoc();
    if ($expRow && $expRow['is_expired']) {
        $conn->query("UPDATE action_requests SET status='expired' WHERE id=$requestId");
        echo json_encode(['status' => 'expired']);
        exit;
    }
}

echo json_encode([
    'status'          => $row['status'],
    'rejection_note'  => $row['rejection_note'],
]);
exit;