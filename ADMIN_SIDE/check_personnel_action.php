<?php
// ADMIN_SIDE/check_personnel_action.php
// Polls status AND executes the action (edit or delete) when approved.
require_once 'admin_shared.php';
require_once '../helpers/ActionRequest.php';

header('Content-Type: application/json');

$ar        = new ActionRequest($conn);
$requestId = (int)($_GET['req'] ?? 0);

if (!$requestId) { echo json_encode(['status'=>'error']); exit; }

$stmt = $conn->prepare("
    SELECT * FROM action_requests
    WHERE id = ? AND admin_user_id = ?
    AND action_type IN ('edit_personnel','delete_record')
");
$stmt->bind_param('ii', $requestId, $_SESSION['user_id']);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) { echo json_encode(['status'=>'not_found']); exit; }

// Auto-expire check using MySQL time
$expChk = $conn->prepare("SELECT expires_at < NOW() AS is_expired FROM action_requests WHERE id = ?");
$expChk->bind_param('i', $requestId); $expChk->execute();
$expRow = $expChk->get_result()->fetch_assoc();
if ($req['status'] === 'approved' && $req['expires_at'] && $expRow['is_expired']) {
    $conn->query("UPDATE action_requests SET status='expired' WHERE id=$requestId");
    echo json_encode(['status' => 'expired']); exit;
}

// ── If approved and token not yet consumed → execute the action ─────────────
if ($req['status'] === 'approved' && !$req['token_used']) {

    $validated = $ar->validateAndConsume($requestId, $_SESSION['user_id'], $req['action_type']);
    if (!$validated) {
        echo json_encode(['status' => 'expired', 'message' => 'Token expired or already used.']); exit;
    }

    $meta = json_decode($req['meta_json'] ?? '{}', true) ?? [];

    // ── EDIT PERSONNEL ──────────────────────────────────────────────────────
    if ($req['action_type'] === 'edit_personnel') {
        $tid = (int)($meta['teacher_id'] ?? $req['target_id']);

        $conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS leave_days_allocation INT DEFAULT 30");

        $full_name         = $meta['full_name']         ?? '';
        $email             = $meta['email']             ?? '';
        $phone             = $meta['phone']             ?? '';
        $subject           = $meta['subject']           ?? '';
        $department        = $meta['department']        ?? '';
        $address           = $meta['address']           ?? '';
        $date_hired        = $meta['date_hired']        ?? '';
        $status            = $meta['status']            ?? 'active';
        $leave_alloc       = (int)($meta['leave_days_allocation'] ?? 30);
        $appt_status       = $meta['appointment_status'] ?? 'Regular';
        $employment_type   = $meta['employment_type']   ?? '';
        $personnel_level   = $meta['personnel_level']   ?? '';
        $tin_number        = $meta['tin_number']        ?? '';
        $philhealth_number = $meta['philhealth_number'] ?? '';
        $pagibig_number    = $meta['pagibig_number']    ?? '';
        $sss_number        = $meta['sss_number']        ?? '';
        $crn_number        = $meta['crn_number']        ?? '';
        $bp_number         = $meta['bp_number']         ?? '';

        $stmt = $conn->prepare("
            UPDATE personnels
            SET full_name=?, email=?, phone=?, subject=?, department=?, address=?,
                date_hired=?, status=?, leave_days_allocation=?,
                appointment_status=?, employment_type=?, personnel_level=?,
                tin_number=?, philhealth_number=?, pagibig_number=?,
                sss_number=?, crn_number=?, bp_number=?
            WHERE id=?
        ");
        $stmt->bind_param(
            'ssssssssissssssssi',
            $full_name, $email, $phone, $subject, $department, $address,
            $date_hired, $status, $leave_alloc,
            $appt_status, $employment_type, $personnel_level,
            $tin_number, $philhealth_number, $pagibig_number,
            $sss_number, $crn_number, $bp_number,
            $tid
        );
        $stmt->execute();

        // Update password if provided
        if (!empty($meta['new_password_hash'])) {
            $uid_row = $conn->query("SELECT user_id FROM personnels WHERE id=$tid")->fetch_assoc();
            if ($uid_row) {
                $pstmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $pstmt->bind_param('si', $meta['new_password_hash'], $uid_row['user_id']);
                $pstmt->execute();
            }
        }

        $ar->log($_SESSION['user_id'], 'admin', 'edited_personnel_approved',
            "Personnel edited after approval: {$full_name} (Request #{$requestId})");

        echo json_encode(['status' => 'approved']); exit;
    }

    // ── DELETE PERSONNEL ────────────────────────────────────────────────────
    if ($req['action_type'] === 'delete_record') {
        $tid = (int)($meta['teacher_id'] ?? $req['target_id']);

        $r = $conn->query("SELECT user_id, full_name FROM personnels WHERE id=$tid")->fetch_assoc();
        if ($r) {
            $conn->query("DELETE FROM users WHERE id={$r['user_id']}");
            // personnels row cascades or clean up manually
            $conn->query("DELETE FROM personnels WHERE id=$tid");
        }

        $ar->log($_SESSION['user_id'], 'admin', 'deleted_personnel_approved',
            "Personnel deleted after approval: " . ($r['full_name'] ?? "ID $tid") . " (Request #{$requestId})");

        echo json_encode(['status' => 'approved']); exit;
    }
}

// ── Still pending or rejected — just return status ───────────────────────────
echo json_encode([
    'status'         => $req['status'],
    'rejection_note' => $req['rejection_note'] ?? null,
]);
exit;