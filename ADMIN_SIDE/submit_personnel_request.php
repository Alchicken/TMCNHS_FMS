<?php
// ADMIN_SIDE/submit_personnel_request.php
require_once 'admin_shared.php';
require_once '../helpers/ActionRequest.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']); exit;
}

$ar = new ActionRequest($conn);

// ── Collect fields ──────────────────────────────────────────────────────────
$last_name          = trim($_POST['last_name']            ?? '');
$first_name         = trim($_POST['first_name']           ?? '');
$middle_name        = trim($_POST['middle_name']          ?? '');
$suffix             = trim($_POST['suffix']               ?? '');
$gender             = trim($_POST['gender']               ?? '');
$civil_status       = trim($_POST['civil_status']         ?? '');
$date_of_birth      = trim($_POST['date_of_birth']        ?? '') ?: null;
$place_of_birth     = trim($_POST['place_of_birth']       ?? '');
$email              = trim($_POST['email']                ?? '');
$phone              = trim($_POST['phone']                ?? '');
$address            = trim($_POST['address']              ?? '');
$username           = trim($_POST['username']             ?? '');
$raw_password       = $_POST['password']                  ?? '';
$personnel_level    = trim($_POST['personnel_level']      ?? '');
$subject            = trim($_POST['subject']              ?? '');
$department         = trim($_POST['department']           ?? '');
$employment_type    = trim($_POST['employment_type']      ?? '');
$date_hired         = trim($_POST['date_hired']           ?? '') ?: null;
$appointment_status = trim($_POST['appointment_status']   ?? 'Newly Hired');
$leave_days_allocation = max(1, intval($_POST['leave_days_allocation'] ?? 30));
$tin_number         = trim($_POST['tin_number']           ?? '');
$philhealth_number  = trim($_POST['philhealth_number']    ?? '');
$pagibig_number     = trim($_POST['pagibig_number']       ?? '');
$sss_number         = trim($_POST['sss_number']           ?? '');
$crn_number         = trim($_POST['crn_number']           ?? '');
$bp_number          = trim($_POST['bp_number']            ?? '');

// ── Validation ──────────────────────────────────────────────────────────────
if (!$last_name || !$first_name)
    exit(json_encode(['success'=>false,'error'=>'First and last name are required.']));
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
    exit(json_encode(['success'=>false,'error'=>'A valid email address is required.']));
if (!preg_match('/^09\d{9}$/', $phone))
    exit(json_encode(['success'=>false,'error'=>'Contact number must be 11 digits starting with 09.']));
if (!$username || !preg_match('/^[a-z0-9._]{3,50}$/', $username))
    exit(json_encode(['success'=>false,'error'=>'Username must be 3–50 chars: lowercase letters, numbers, dots, underscores only.']));
if (strlen($raw_password) < 8)
    exit(json_encode(['success'=>false,'error'=>'Password must be at least 8 characters long.']));
if (!preg_match('/[A-Z]/', $raw_password) || !preg_match('/[0-9]/', $raw_password))
    exit(json_encode(['success'=>false,'error'=>'Password must contain at least one uppercase letter and one number. e.g. Admin@123']));
if (!$subject)
    exit(json_encode(['success'=>false,'error'=>'Position/Subject is required.']));
if (!$date_hired)
    exit(json_encode(['success'=>false,'error'=>'Date hired is required.']));
if (!$personnel_level)
    exit(json_encode(['success'=>false,'error'=>'Field/Level is required.']));

// ── Check duplicates ─────────────────────────────────────────────────────────
$chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
$chk->bind_param('s', $username); $chk->execute();
if ($chk->get_result()->num_rows > 0)
    exit(json_encode(['success'=>false,'error'=>"Username \"{$username}\" is already taken. Please choose a different one."]));

$chkE = $conn->prepare("SELECT id FROM personnels WHERE email = ?");
$chkE->bind_param('s', $email); $chkE->execute();
if ($chkE->get_result()->num_rows > 0)
    exit(json_encode(['success'=>false,'error'=>"Email \"{$email}\" is already registered to another personnel."]));

// ── Check no duplicate pending request ──────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS admin_request_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, request_id INT NOT NULL,
    admin_user_id INT NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_admin (admin_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$chkP = $conn->prepare("
    SELECT id FROM action_requests
    WHERE action_type = 'add_personnel' AND status = 'pending'
    AND JSON_EXTRACT(meta_json, '$.username') = ?
");
$chkP->bind_param('s', $username); $chkP->execute();
if ($chkP->get_result()->num_rows > 0)
    exit(json_encode(['success'=>false,'error'=>"A pending request for username \"{$username}\" already exists. Please wait for Super Admin review."]));

// ── Build meta and create request ────────────────────────────────────────────
$password_hash = password_hash($raw_password, PASSWORD_BCRYPT);
$meta = compact(
    'last_name','first_name','middle_name','suffix','gender','civil_status',
    'date_of_birth','place_of_birth','email','phone','address',
    'username','personnel_level','subject','department','employment_type',
    'date_hired','appointment_status','leave_days_allocation',
    'tin_number','philhealth_number','pagibig_number','sss_number','crn_number','bp_number'
);
$meta['password_hash'] = $password_hash;

$fullName  = trim("$first_name $last_name");
$requestId = $ar->createRequest(
    $_SESSION['user_id'],
    'add_personnel',
    "Add New Personnel: \"{$fullName}\" ({$subject})",
    null, 'personnel', $meta
);

$ar->log($_SESSION['user_id'], 'admin', 'requested_add_personnel',
    "Requested to add: {$fullName} (Request #{$requestId})");

echo json_encode(['success' => true, 'request_id' => $requestId]);
exit;