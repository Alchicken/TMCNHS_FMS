<?php
// manage_personnel.php — MANAGE PERSONNEL
require_once 'admin_shared.php';

$msg = $err = '';

// ── ADD PERSONNEL (4-step wizard) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_personnel') {

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
    $password           = trim($_POST['password']             ?? '');
    $subject            = trim($_POST['subject']              ?? '');
    $department         = trim($_POST['department']           ?? '');
    $employment_type    = trim($_POST['employment_type']      ?? '');
    $date_hired         = trim($_POST['date_hired']           ?? '') ?: null;
    $appointment_status = trim($_POST['appointment_status']   ?? 'Newly Hired');
    $status             = 'active'; // ← ADD THIS — default status
    $leave_days_allocation = max(1, intval($_POST['leave_days_allocation'] ?? 30)); // ← renamed to match bind
    $tin_number         = trim($_POST['tin_number']           ?? '') ?: null; // ← renamed to match bind
    $philhealth_number  = trim($_POST['philhealth_number']    ?? '') ?: null;
    $pagibig_number     = trim($_POST['pagibig_number']       ?? '') ?: null;
    $sss_number         = trim($_POST['sss_number']           ?? '') ?: null;
    $crn_number         = trim($_POST['crn_number']           ?? '') ?: null;
    $bp_number          = trim($_POST['bp_number']            ?? '') ?: null;

    $errors = [];
    if (!$last_name)                                       $errors[] = 'Last name is required.';
    if (!$first_name)                                      $errors[] = 'First name is required.';
    if (!$gender)                                          $errors[] = 'Gender is required.';
    if (!$civil_status)                                    $errors[] = 'Civil status is required.';
    if (!$date_of_birth)                                   $errors[] = 'Date of birth is required.';
    if (!$place_of_birth)                                  $errors[] = 'Place of birth is required.';
    if (!$address)                                         $errors[] = 'Address is required.';
    if (!$email)                                           $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))    $errors[] = 'Invalid email address.';
    if (!$phone)                                           $errors[] = 'Contact number is required.';
    elseif (!preg_match('/^09\d{9}$/', $phone))            $errors[] = 'Contact number must be 11 digits starting with 09.';
    if (!$username)                                        $errors[] = 'Username is required.';
    elseif (!preg_match('/^[a-z0-9._]{3,50}$/', $username)) $errors[] = 'Username: lowercase letters, numbers, dots, underscores only (3–50 chars).';
    if (!$password)                                        $errors[] = 'Password is required.';
    elseif (strlen($password) < 8)                         $errors[] = 'Password must be at least 8 characters.';
    if (!$subject)                                         $errors[] = 'Position/Subject is required.';
    $personnel_level = trim($_POST['personnel_level'] ?? '');
    if (!$department && $personnel_level !== 'Non-Teaching') $errors[] = 'Department is required.';
    if (!$date_hired)                                      $errors[] = 'Date hired is required.';
    if ($date_of_birth) {
        $age = (new DateTime($date_of_birth))->diff(new DateTime())->y;
        if ($age < 18) $errors[] = 'Personnel must be at least 18 years old.';
        if ($age > 80) $errors[] = 'Please check the date of birth.';
    }

    if (!empty($errors)) {
        $e = urlencode(implode(' | ', $errors));
        header("Location: manage_personnel.php?ap_error={$e}");
        exit;
    }

    // Duplicate username check
    $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $chk->bind_param('s', $username);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $e = urlencode("Username '{$username}' is already taken. Please choose another.");
        header("Location: manage_personnel.php?ap_error={$e}");
        exit;
    }

    // Duplicate email check
    $chkE = $conn->prepare("SELECT id FROM personnels WHERE email = ?");
    $chkE->bind_param('s', $email);
    $chkE->execute();
    if ($chkE->get_result()->num_rows > 0) {
        $e = urlencode("Email '{$email}' is already registered.");
        header("Location: manage_personnel.php?ap_error={$e}");
        exit;
    }

    $hashed    = password_hash($password, PASSWORD_BCRYPT);
    $full_name = trim("$first_name $middle_name $last_name" . ($suffix ? " $suffix" : ''));

    // ── INSERT USER ACCOUNT FIRST ─────────────────────────
    $insUser = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'teacher')");
    if (!$insUser) die("User insert prepare failed: " . $conn->error);
    $insUser->bind_param('ss', $username, $hashed);
    $insUser->execute();
    $new_user_id = $conn->insert_id; // ← capture the new user's ID

    // ── INSERT TEACHER RECORD ─────────────────────────────
    $insTeacher = $conn->prepare("
        INSERT INTO personnels (
            user_id,
            full_name, last_name, first_name, middle_name, suffix,
            gender, civil_status, date_of_birth, place_of_birth,
            email, phone, address,
            subject, department, employment_type,
            appointment_status, status, date_hired, leave_days_allocation,
            tin_number, philhealth_number, pagibig_number, sss_number, crn_number, bp_number
        ) VALUES (
            ?,
            ?,?,?,?,?,
            ?,?,?,?,
            ?,?,?,
            ?,?,?,
            ?,?,?,?,
            ?,?,?,?,?,?
        )
    ");

    if (!$insTeacher) die("Teacher insert prepare failed: " . $conn->error);

    // i = user_id (int)
    // then 19 strings, 1 int (leave_days_allocation), 6 strings
    $insTeacher->bind_param(
        'issssssssssssssssssissssss',
        $new_user_id,
        $full_name, $last_name, $first_name, $middle_name, $suffix,
        $gender, $civil_status, $date_of_birth, $place_of_birth,
        $email, $phone, $address,
        $subject, $department, $employment_type,
        $appointment_status, $status, $date_hired, $leave_days_allocation,
        $tin_number, $philhealth_number, $pagibig_number, $sss_number, $crn_number, $bp_number
    );

    if (!$insTeacher->execute()) {
        // If teacher insert fails, clean up the user we just created
        $conn->query("DELETE FROM users WHERE id = $new_user_id");
        $e = urlencode("Database error: " . $insTeacher->error);
        header("Location: manage_personnel.php?ap_error={$e}");
        exit;
    }

    $name_enc = urlencode("$first_name $last_name");
    header("Location: manage_personnel.php?added=1&name={$name_enc}");
    exit;
}

// ── DELETE TEACHER ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_teacher') {
    $tid = (int)$_POST['teacher_id'];
    $r = $conn->query("SELECT user_id FROM personnels WHERE id=$tid")->fetch_assoc();
    if ($r) $conn->query("DELETE FROM users WHERE id={$r['user_id']}");
    $msg = 'Teacher removed.';
    $teachers_arr = [];
    $res = $conn->query("SELECT t.*, u.username, u.created_at as joined FROM personnels t JOIN users u ON t.user_id=u.id ORDER BY t.full_name");
    if ($res) while ($row = $res->fetch_assoc()) $teachers_arr[] = $row;
}

// ── EDIT TEACHER ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_teacher') {
    $tid        = (int)$_POST['teacher_id'];
    $full_name  = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $subject    = trim($_POST['subject']);
    $department = trim($_POST['department']);
    $address    = trim($_POST['address']);
    $date_hired = trim($_POST['date_hired']);
    $status     = in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active';
    $leave_alloc = max(1, (int)$_POST['leave_days_allocation']);
    $appointment_status  = trim($_POST['appointment_status'] ?? 'Regular');
    $personnel_level     = trim($_POST['personnel_level'] ?? '');
    $employment_type     = trim($_POST['employment_type'] ?? '');

    $conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS leave_days_allocation INT DEFAULT 30");

    $stmt = $conn->prepare("UPDATE personnels SET full_name=?, email=?, phone=?, subject=?, department=?, address=?, date_hired=?, status=?, leave_days_allocation=?, appointment_status=?, employment_type=?, personnel_level=? WHERE id=?");
    $stmt->bind_param('ssssssssisssi', $full_name, $email, $phone, $subject, $department, $address, $date_hired, $status, $leave_alloc, $appointment_status, $employment_type, $personnel_level, $tid);
    $stmt->execute();

    if (!empty(trim($_POST['new_password'] ?? ''))) {
        $new_pass = password_hash(trim($_POST['new_password']), PASSWORD_BCRYPT);
        $uid_row  = $conn->query("SELECT user_id FROM personnels WHERE id=$tid")->fetch_assoc();
        if ($uid_row) {
            $pstmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $pstmt->bind_param('si', $new_pass, $uid_row['user_id']);
            $pstmt->execute();
        }
    }

    $msg = 'Teacher information updated successfully!';
    $teachers_arr = [];
    $res = $conn->query("SELECT t.*, u.username, u.created_at as joined FROM personnels t JOIN users u ON t.user_id=u.id ORDER BY t.full_name");
    if ($res) while ($row = $res->fetch_assoc()) $teachers_arr[] = $row;
}

// SweetAlert params from redirect
$swal_success = isset($_GET['added']) && $_GET['added'] === '1';
$swal_name    = htmlspecialchars($_GET['name'] ?? 'Personnel', ENT_QUOTES);
$swal_error   = isset($_GET['ap_error']) ? htmlspecialchars($_GET['ap_error'], ENT_QUOTES) : '';

// Fetch departments and positions for dropdowns
$dept_options = [];
$res = $conn->query("SELECT id, name FROM departments WHERE status='active' ORDER BY name");
if ($res) while ($row = $res->fetch_assoc()) $dept_options[] = $row;

$pos_options = [];
$res = $conn->query("SELECT id, title FROM positions WHERE status='active' ORDER BY title");
if ($res) while ($row = $res->fetch_assoc()) $pos_options[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Personnel — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:      #f7f2f2;
    --surface: #ffffff;
    --card:    #ffffff;
    --border:  #f0d4d4;
    --accent:  #c8102e;
    --accent2: #9b0c22;
    --text:    #1a0608;
    --muted:   #9c7b7e;
    --success: #059669;
    --danger:  #c8102e;
    --warn:    #d97706;
    --purple:  #7b1fa2;
    --sidebar: 260px;
  }
  body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

  /* ── SIDEBAR ─────────────────────────────────────────── */
  .sidebar { width: var(--sidebar); background: var(--accent2); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; box-shadow: 4px 0 20px rgba(155,12,34,0.15); }
  .sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.12); display: flex; flex-direction: column; align-items: center; gap: 12px; background: transparent; }
  .sidebar-logo-img-wrap { width: 76px; height: 76px; border-radius: 50%; background: #fff; padding: 6px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 18px rgba(0,0,0,0.3), 0 0 0 3px rgba(255,255,255,0.18); flex-shrink: 0; }
  .sidebar-logo img { width: 100%; height: 100%; object-fit: contain  ; display: block; }
  .sidebar-logo-text { text-align: center; }
  .sidebar-logo-text h2 { color: #fff; font-size: 12.5px; font-weight: 700; font-family: 'DM Serif Display', serif; line-height: 1.45; letter-spacing: 0.01em; }
  .sidebar-logo-text span { color: rgba(255,255,255,0.45); font-size: 9px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1.8px; display: block; margin-top: 4px; }
  .nav { padding: 16px 12px; flex: 1; }
  .nav-label { color: rgba(255,255,255,0.45); font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 2px; padding: 12px 12px 6px; }
  .nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 10px; cursor: pointer; color: rgba(255,255,255,0.65); font-size: 13px; font-weight: 500; transition: all 0.2s; margin-bottom: 2px; text-decoration: none; }
  .nav-item:hover { background: rgba(255,255,255,0.12); color: #fff; }
  .nav-item.active { background: rgba(255,255,255,0.18); color: #fff; font-weight: 600; }
  .nav-item .ni { font-size: 16px; }
  .sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.15); }
  .user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,0.12); border-radius: 10px; }
  .avatar { width: 34px; height: 34px; background: rgba(255,255,255,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
  .user-info { flex: 1; overflow: hidden; }
  .user-info strong { display: block; font-size: 12px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .user-info small { color: rgba(255,255,255,0.55); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
  .logout-btn { color: rgba(255,255,255,0.55); font-size: 16px; text-decoration: none; }
  .logout-btn:hover { color: #fff; }

  .main { margin-left: var(--sidebar); flex: 1; min-width: 0; }
  .topbar { padding: 20px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--surface); position: sticky; top: 0; z-index: 50; }
  .topbar h1 { font-size: 18px; font-weight: 700; }
  .topbar p { color: var(--muted); font-size: 12px; margin-top: 2px; }
  .page { padding: 28px 32px; }
  .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; }
  .btn-primary { background: var(--accent); color: white; }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(155,12,34,0.35); }
  .btn-success { background: var(--success); color: white; }
  .btn-success:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.35); }
  .btn-sm { padding: 6px 12px; font-size: 11px; }
  .btn-danger { background: rgba(200,16,46,0.1); color: var(--danger); border: 1px solid rgba(200,16,46,0.3); }
  .btn-danger:hover { background: rgba(200,16,46,0.18); }
  .btn-ghost { background: var(--surface); color: var(--text); border: 1px solid var(--border); }
  .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
  .btn-view { background: rgba(200,16,46,0.12); color: var(--accent); border: 1px solid rgba(200,16,46,0.3); }
  .btn-view:hover { background: rgba(200,16,46,0.2); }
  .btn-download { background: rgba(5,150,105,0.15); color: var(--success); border: 1px solid rgba(5,150,105,0.3); }
  .btn-download:hover { background: rgba(5,150,105,0.25); }
  .btn-purple { background: rgba(167,139,250,0.12); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }
  .btn-edit { background: rgba(217,119,6,0.12); color: var(--warn); border: 1px solid rgba(217,119,6,0.3); }
  .btn-edit:hover { background: rgba(217,119,6,0.22); }
  .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
  .edit-section-title { font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin: 20px 0 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
  .edit-section-title:first-child { margin-top: 0; }
  .form-group select { width: 100%; padding: 11px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 9px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; transition: border-color 0.2s; }
  .form-group select:focus { border-color: var(--accent); }
  .leave-alloc-wrap { display: flex; align-items: center; gap: 12px; }
  .leave-alloc-wrap input[type=number] { width: 100px; }
  .leave-alloc-note { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

 /* ── TABLE ───────────────────────────────────────────── */
.table-wrap { 
  background: var(--card); 
  border: 1px solid var(--border); 
  border-radius: 16px; 
  overflow: visible; 
  display: flex; 
  flex-direction: column;
  box-shadow: 0 2px 12px rgba(155,12,34,0.06);
}
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
thead { background: linear-gradient(135deg, #fdf0f0, #fff8f8); }
thead tr { border-bottom: 2px solid var(--border); }
th { 
  padding: 14px 16px; 
  text-align: left; 
  font-size: 10px; 
  font-family: 'JetBrains Mono', monospace; 
  text-transform: uppercase; 
  letter-spacing: 1.5px; 
  color: var(--muted);
  white-space: nowrap; 
}
th:first-child { border-radius: 16px 0 0 0; padding-left: 20px; }
th:last-child  { border-radius: 0 16px 0 0; }

td { 
  padding: 0 16px;
  border-top: 1px solid var(--border); 
  font-size: 13px; 
  overflow: hidden; 
  text-overflow: ellipsis; 
  white-space: nowrap; 
  max-width: 0; 
  vertical-align: middle;
  height: 72px; /* fixed row height — 10 rows = 720px */
}
td:first-child { padding-left: 20px; }
td:last-child  { padding-right: 20px; overflow: visible !important;}

tr { position: relative; }

tr:hover td { background: linear-gradient(135deg, rgba(200,16,46,0.025), rgba(200,16,46,0.015)); }
tr:hover td:first-child { border-left: 3px solid var(--accent); padding-left: 17px; }

/* Column widths */
thead th:nth-child(1), td:nth-child(1) { width: 20%; white-space: normal; }
thead th:nth-child(2), td:nth-child(2) { width: 15%; }
thead th:nth-child(3), td:nth-child(3) { width: 11%; }
thead th:nth-child(4), td:nth-child(4) { width: 10%; white-space: normal; }
thead th:nth-child(5), td:nth-child(5) { width: 6%; text-align: center; }
thead th:nth-child(6), td:nth-child(6) { width: 9%; }
thead th:nth-child(7), td:nth-child(7) { width: 10%; }
thead th:nth-child(8), td:nth-child(8) { width: 10%; }
thead th:nth-child(9), td:nth-child(9) { width: 9%; text-align: right; padding-right: 20px; }

/* Fixed tbody = exactly 10 rows */
#teacherTbody { display: table-row-group; overflow: visible; }

/* Responsive */
@media (max-width: 1300px) { thead th:nth-child(3), td:nth-child(3) { display: none; } }
@media (max-width: 1100px) { thead th:nth-child(2), td:nth-child(2), thead th:nth-child(7), td:nth-child(7) { display: none; } }
@media (max-width: 900px)  { thead th:nth-child(6), td:nth-child(6), thead th:nth-child(8), td:nth-child(8) { display: none; } }

  .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
  .badge-active   { background: rgba(5,150,105,0.15); color: var(--success); }
  .badge-inactive { background: rgba(200,16,46,0.1); color: var(--danger); }
  .badge-Regular  { background: rgba(5,150,105,0.15); color: var(--success); }
  .badge-Newly\ Hired { background: rgba(200,16,46,0.1); color: var(--danger); }
  .badge-Transferee   { background: rgba(217,119,6,0.12); color: var(--warn); }
  .badge-Reinstated   { background: rgba(167,139,250,0.12); color: var(--purple); }
  .teacher-name { display: flex; align-items: center; gap: 10px; }
  .t-avatar   { width: 34px; height: 34px; background: linear-gradient(135deg,#c8102e,#9b0c22); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: white; flex-shrink: 0; }
  .t-avatar-b { background: linear-gradient(135deg,var(--success),#059669); }
  .t-avatar-c { background: linear-gradient(135deg,#d97706,#ef4444); }
  .t-info strong { display: block; font-size: 13px; line-height: 1.3; }
  .t-info span   { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
  .teacher-link { cursor: pointer; }
  .teacher-link:hover .t-info strong { color: var(--accent); text-decoration: underline; text-underline-offset: 3px; }
  .doc-count-badge { display: inline-flex; align-items: center; justify-content: center; background: rgba(200,16,46,0.15); color: var(--accent); border-radius: 20px; font-size: 11px; font-family: 'JetBrains Mono', monospace; padding: 2px 10px; }
  .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
  .alert-success { background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.3); color: var(--success); }
  .alert-danger  { background: rgba(255,107,107,0.1); border: 1px solid rgba(200,16,46,0.3); color: var(--danger); }
  .search-wrap { position: relative; margin-bottom: 18px; }
  .search-wrap .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; opacity: 0.5; }
  .search-input { width: 100%; padding: 12px 16px 12px 42px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 14px; outline: none; transition: border-color 0.2s; }
  .search-input::placeholder { color: var(--muted); }
  .search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,16,46,0.12); }
  .search-clear { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); font-size: 16px; cursor: pointer; display: none; }
  .search-clear:hover { color: var(--danger); }
  .search-results-count { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-bottom: 12px; }
  .search-results-count span { color: var(--accent); font-weight: 600; }
  .no-results-row td { text-align: center; color: var(--muted); padding: 48px 20px !important; white-space: normal; }
  .no-results-row .nr-icon { font-size: 32px; display: block; margin-bottom: 8px; }
  .hl { background: rgba(200,16,46,0.18); color: var(--accent2); border-radius: 2px; padding: 0 2px; }

  /* ── MODALS ──────────────────────────────────────────── */
  .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.78); z-index: 1000; align-items: flex-start; justify-content: center; backdrop-filter: blur(5px); padding: 24px 20px; overflow-y: auto; }
  .modal-overlay.open { display: flex; }
  @keyframes slideIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
  .modal-sm { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 32px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; animation: slideIn 0.3s ease; margin: auto; }
  .modal-lg { background: var(--card); border: 1px solid var(--border); border-radius: 20px; width: 100%; max-width: 980px; animation: slideIn 0.3s ease; display: flex; flex-direction: column; margin: auto; }
  .pm-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 20px; }
  .pm-avatar { width: 72px; height: 72px; border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: white; flex-shrink: 0; }
  .pm-avatar-a { background: linear-gradient(135deg,#c8102e,#9b0c22); }
  .pm-avatar-b { background: linear-gradient(135deg,var(--success),#059669); }
  .pm-avatar-c { background: linear-gradient(135deg,#d97706,#ef4444); }
  .pm-title h2 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .pm-title p  { color: var(--muted); font-size: 13px; }
  .pm-title .tags { display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap; }
  .tag { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-family: 'JetBrains Mono', monospace; }
  .tag-blue  { background: rgba(200,16,46,0.12); color: var(--accent); }
  .tag-green { background: rgba(5,150,105,0.12); color: var(--success); }
  .tag-red   { background: rgba(255,107,107,0.12); color: var(--danger); }
  .pm-close { margin-left: auto; background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; flex-shrink: 0; padding: 4px 8px; }
  .pm-close:hover { color: var(--danger); }
  .pm-tabs { display: flex; gap: 4px; padding: 16px 32px 0; border-bottom: 1px solid var(--border); }
  .pm-tab { padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--muted); border-bottom: 2px solid transparent; transition: all 0.2s; border-radius: 8px 8px 0 0; background: none; border-left: none; border-right: none; border-top: none; display: flex; align-items: center; gap: 6px; }
  .pm-tab:hover { color: var(--text); background: rgba(200,16,46,0.04); }
  .pm-tab.active { color: var(--accent); border-bottom: 2px solid var(--accent); }
  .pm-tab .tab-badge { background: rgba(200,16,46,0.15); color: var(--accent); border-radius: 20px; font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 1px 7px; }
  .pm-tab.tab-purple.active { color: var(--purple); border-bottom-color: var(--purple); }
  .pm-tab.tab-purple .tab-badge { background: rgba(167,139,250,0.15); color: var(--purple); }
  .pm-panel { display: none; padding: 28px 32px; }
  .pm-panel.active { display: block; }
  .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 32px; }
  .info-item label { display: block; color: var(--muted); font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; }
  .info-item span { font-size: 13px; }
  .doc-row { display: flex; align-items: center; gap: 12px; padding: 11px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; margin-bottom: 8px; transition: border-color 0.2s; }
  .doc-row:hover { border-color: var(--accent); }
  .doc-icon { font-size: 22px; flex-shrink: 0; }
  .doc-info { flex: 1; min-width: 0; }
  .doc-info strong { display: block; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .doc-info span { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
  .doc-actions { display: flex; gap: 6px; flex-shrink: 0; }
  .no-content { text-align: center; color: var(--muted); padding: 40px 16px; font-size: 13px; }
  .no-content-icon { font-size: 36px; margin-bottom: 8px; display: block; }
  .sc-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 22px; }
  .sc-stat { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 16px; position: relative; overflow: hidden; }
  .sc-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
  .sc-stat.green::before  { background: linear-gradient(90deg,var(--success),var(--success)); }
  .sc-stat.danger::before { background: linear-gradient(90deg,#dc2626,var(--danger)); }
  .sc-stat.purple::before { background: linear-gradient(90deg,#7c3aed,var(--purple)); }
  .sc-stat.blue::before   { background: linear-gradient(90deg,var(--accent2),var(--accent)); }
  .sc-stat .sc-icon  { font-size: 20px; margin-bottom: 6px; }
  .sc-stat .sc-num   { font-size: 26px; font-weight: 700; font-family: 'JetBrains Mono', monospace; line-height: 1; }
  .sc-stat .sc-label { color: var(--muted); font-size: 10px; margin-top: 5px; }
  .sc-progress-wrap { margin-bottom: 22px; }
  .sc-progress-label { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
  .sc-progress-label span { color: var(--text); }
  .progress-bar-bg { background: #f0d4d4; border-radius: 999px; height: 10px; overflow: hidden; }
  .progress-bar-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg,var(--success),var(--success)); transition: width 0.5s ease; }
  .progress-bar-fill.warn-fill   { background: linear-gradient(90deg,#d97706,#d97706); }
  .progress-bar-fill.danger-fill { background: linear-gradient(90deg,#dc2626,#ef4444); }
  .leave-log-row { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; }
  .leave-log-row:hover { border-color: var(--purple); }
  .leave-log-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; flex-wrap: wrap; gap: 6px; }
  .leave-log-header strong { font-size: 13px; }
  .leave-log-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .leave-type-badge { padding: 2px 10px; border-radius: 20px; font-size: 10px; font-family: 'JetBrains Mono', monospace; }
  .leave-sick      { background: rgba(239,68,68,0.15); color: #f87171; }
  .leave-vacation  { background: rgba(200,16,46,0.15); color: var(--accent); }
  .leave-emergency { background: rgba(245,158,11,0.15); color: var(--warn); }
  .leave-maternity { background: rgba(167,139,250,0.15); color: var(--purple); }
  .leave-other     { background: rgba(136,146,176,0.15); color: var(--muted); }
  .leave-log-dates { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
  .date-chip { padding: 2px 8px; background: rgba(167,139,250,0.12); color: var(--purple); border-radius: 5px; font-size: 10px; font-family: 'JetBrains Mono', monospace; }
  .leave-log-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; flex-wrap: wrap; gap: 6px; }
  .leave-log-footer small { color: var(--muted); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; color: var(--muted); font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
  .form-group input { width: 100%; padding: 11px 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 9px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; transition: border-color 0.2s; }
  .form-group input:focus { border-color: var(--accent); }
  .modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
  .modal-sm h2 { font-size: 18px; margin-bottom: 24px; }

  /* ── ADD PERSONNEL WIZARD ────────────────────────────── */
  .ap-modal { background: var(--card); border: 1px solid var(--border); border-radius: 20px; width: 100%; max-width: 780px; animation: slideIn 0.3s ease; margin: auto; }
  .progress-bar-top { height: 3px; background: var(--border); border-radius: 20px 20px 0 0; overflow: hidden; }
  .progress-fill-top { height: 100%; background: linear-gradient(90deg, var(--accent2), var(--accent)); transition: width 0.4s ease; }
  .ap-modal-header { padding: 24px 32px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
  .ap-modal-header-left { display: flex; align-items: center; gap: 14px; }
  .ap-modal-icon { width: 48px; height: 48px; background: linear-gradient(135deg,#c8102e,#9b0c22); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
  .ap-modal-header h2 { font-size: 18px; font-weight: 700; font-family: 'DM Serif Display', serif; }
  .ap-modal-header p { color: var(--muted); font-size: 12px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
  .ap-modal-close { background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: all 0.2s; }
  .ap-modal-close:hover { background: rgba(200,16,46,0.1); color: var(--danger); }
  .step-tabs { display: flex; padding: 0 32px; border-bottom: 1px solid var(--border); gap: 0; overflow-x: auto; }
  .step-tab { display: flex; align-items: center; gap: 8px; padding: 14px 20px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--muted); border-bottom: 2px solid transparent; transition: all 0.2s; white-space: nowrap; background: none; border-left: none; border-right: none; border-top: none; font-family: 'DM Sans', sans-serif; }
  .step-tab:hover { color: var(--text); }
  .step-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
  .step-tab .step-num { width: 22px; height: 22px; border-radius: 50%; background: rgba(200,16,46,0.12); color: var(--accent); font-size: 11px; font-family: 'JetBrains Mono', monospace; display: flex; align-items: center; justify-content: center; font-weight: 700; transition: all 0.2s; }
  .step-tab.active .step-num { background: var(--accent); color: white; }
  .step-tab.done .step-num { background: var(--success); color: white; }
  .step-tab.done { color: var(--success); }
  .ap-modal-body { padding: 28px 32px; max-height: 60vh; overflow-y: auto; }
  .step-panel { display: none; }
  .step-panel.active { display: block; }
  .section-label-ap { font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 2px; color: var(--accent); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 16px; }
  .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
  .form-grid-1 { display: grid; grid-template-columns: 1fr; gap: 14px; margin-bottom: 16px; }
  .fg { display: flex; flex-direction: column; }
  .fg label { font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 6px; }
  .fg label .req { color: var(--danger); margin-left: 2px; }
  .fg input, .fg select, .fg textarea { width: 100%; padding: 10px 13px; background: var(--surface); border: 1.5px solid var(--border); border-radius: 9px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; transition: all 0.2s; }
  .fg input:focus, .fg select:focus, .fg textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,16,46,0.1); }
  .fg input.ap-error, .fg select.ap-error { border-color: var(--danger); background: rgba(200,16,46,0.04); }
  .fg input.ap-valid, .fg select.ap-valid { border-color: var(--success); }
  .field-hint { font-size: 10px; color: var(--muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
  .field-error { font-size: 10px; color: var(--danger); margin-top: 4px; font-family: 'JetBrains Mono', monospace; display: none; }
  .field-error.show { display: block; }
  .iw { position: relative; }
  .iw .ii { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; pointer-events: none; }
  .ap-modal-footer { padding: 18px 32px 24px; border-top: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .step-dots { display: flex; gap: 6px; }
  .step-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); transition: all 0.3s; }
  .step-dot.active { background: var(--accent); width: 20px; border-radius: 4px; }
  .step-dot.done { background: var(--success); }
  .summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .summary-item { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 11px 14px; }
  .summary-item label { font-size: 9px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); display: block; margin-bottom: 4px; }
  .summary-item span { font-size: 13px; font-weight: 500; }
  .summary-section-title { font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 2px; color: var(--accent); margin: 18px 0 10px; display: flex; align-items: center; gap: 8px; }
  .swal-custom { font-family: 'DM Sans', sans-serif !important; border-radius: 18px !important; }
  .swal2-popup { font-family: 'DM Sans', sans-serif !important; border-radius: 18px !important; }

  /* ── ACTION DROPDOWN ─────────────────────────────────── */
  .action-wrap { position: relative; display: inline-block; }
  .action-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 10px; background: transparent; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--muted); transition: all 0.2s; }
  .action-btn:hover { border-color: var(--accent); color: var(--accent); background: rgba(200,16,46,0.04); }
  .action-btn .chevron { font-size: 9px; transition: transform 0.2s; margin-left: 2px; }
  .action-wrap:hover .chevron { transform: rotate(180deg); }
  .action-dropdown { 
    position: fixed;   /* ← change from absolute to fixed */
    background: var(--card); 
    border: 1.5px solid var(--border); 
    border-radius: 10px; 
    box-shadow: 0 6px 24px rgba(0,0,0,0.10); 
    min-width: 148px;  
    z-index: 9999; 
    opacity: 0; 
    visibility: hidden; 
    transform: translateY(-4px); 
    transition: all 0.18s ease; 
    overflow: hidden; 
  }
  .action-wrap:hover .action-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
  .action-dropdown-item { display: flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 12.5px; font-weight: 500; cursor: pointer; transition: background 0.12s; border: none; background: none; width: 100%; text-align: left; font-family: 'DM Sans', sans-serif; color: var(--text); line-height: 1.3; }
  .action-dropdown-item .item-icon { font-size: 14px; flex-shrink: 0; }
  .action-dropdown-item:hover { background: var(--bg); }
  .action-dropdown-item.item-view:hover { color: var(--accent); }
  .action-dropdown-item.item-edit:hover { color: var(--warn); }
  .action-dropdown-item.item-delete { color: var(--danger); }
  .action-dropdown-item.item-delete:hover { background: rgba(200,16,46,0.06); }
  .action-dropdown-divider { height: 1px; background: var(--border); margin: 3px 10px; }

  /* ── PAGINATION ──────────────────────────────────────── */
  .pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-top: 1px solid var(--border); background: var(--surface); border-radius: 0 0 14px 14px; flex-shrink: 0; }
  .pagination-info { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
  .pagination-info span { color: var(--text); font-weight: 600; }
  .pagination-btns { display: flex; align-items: center; gap: 4px; }
  .page-btn { min-width: 32px; height: 32px; border-radius: 7px; border: 1.5px solid var(--border); background: var(--card); color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: all 0.15s; padding: 0 6px; }
  .page-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: rgba(200,16,46,0.05); }
  .page-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }
  .page-btn:disabled { opacity: 0.35; cursor: not-allowed; }

  /* ── FILTER BAR ──────────────────────────────────────── */
  .filter-bar { display: flex; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; align-items: center; }
  .filter-select { padding: 8px 32px 8px 12px; background: var(--card); border: 1px solid var(--border); border-radius: 9px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 12px; outline: none; transition: border-color 0.2s; cursor: pointer; appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239c7b7e' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; min-width: 150px; }
  .filter-select:focus { border-color: var(--accent); }
  .filter-select.active-filter { border-color: var(--accent); background-color: rgba(200,16,46,0.04); color: var(--accent); }
  .filter-clear-btn { padding: 8px 14px; background: none; border: 1px dashed var(--border); border-radius: 9px; color: var(--muted); font-family: 'DM Sans', sans-serif; font-size: 12px; cursor: pointer; transition: all 0.2s; display: none; white-space: nowrap; }
  .filter-clear-btn:hover { border-color: var(--danger); color: var(--danger); }
  .filter-clear-btn.visible { display: inline-flex; align-items: center; gap: 5px; }
  .filter-count-chip { background: var(--accent); color: white; border-radius: 20px; font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 1px 7px; margin-left: 4px; }


  .level-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 14px 10px;
  border: 2px solid var(--border);
  border-radius: 12px;
  cursor: pointer;
  transition: all .2s;
  text-align: center;
  background: var(--surface);
  user-select: none;
}
.level-card:hover {
  border-color: var(--accent);
  background: rgba(200,16,46,.03);
}
.level-card.selected {
  border-color: var(--accent);
  background: rgba(200,16,46,.06);
  box-shadow: 0 0 0 3px rgba(200,16,46,.12);
}
.level-icon { font-size: 24px; }
.level-title { font-size: 13px; font-weight: 700; color: var(--text); }
.level-desc { font-size: 11px; color: var(--muted); }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-img-wrap">
      <img src="../uploads/logo/TMCNHS-Logo.png" alt="TMCNHS Logo" onerror="this.style.display='none'">
    </div>
    <div class="sidebar-logo-text">
      <h2>Trece Martires City<br>National High School</h2>
      <span>Administrator Portal</span>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-label">Main</div>
    <a class="nav-item" href="admin_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item active" href="manage_personnel.php"><span class="ni">👩‍🏫</span> Manage Personnel</a>
    <a class="nav-item" href="org_management.php"><span class="ni">🏛️</span> Org Management</a>
    <a class="nav-item" href="documents_library.php"><span class="ni">📁</span> Documents</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="avatar">🛡️</div> 
      <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <small>Administrator</small>
      </div>
      <a href="../logout.php" class="logout-btn" title="Logout" style="font-size: 1.5rem;">⏏</a>
    </div>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <h1>Manage Personnel</h1>
      <p>Click a personnel to view full profile</p>
    </div>
    <button class="btn btn-primary" onclick="openAddPersonnel()">+ Add Personnel</button>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" class="search-input" id="teacherSearch" placeholder="Search by name, username, email, subject, or department…" oninput="filterTable('teacherSearch','teacherTbody','teacherCount')">
      <button class="search-clear" id="teacherSearchClear" onclick="clearSearch('teacherSearch','teacherTbody','teacherCount')">✕</button>
    </div>
    <div class="filter-bar" id="filterBar">
      <select class="filter-select" id="filterApptStatus" onchange="applyFilters()">
        <option value="">🏷 All Appt. Status</option>
        <option value="Newly Hired">Newly Hired</option>
        <option value="Regular">Regular</option>
        <option value="Transferee">Transferee</option>
        <option value="Reinstated">Reinstated</option>
      </select>
      <select class="filter-select" id="filterEmpType" onchange="applyFilters()">
        <option value="">💼 All Employment Types</option>
        <option value="Permanent">Permanent</option>
        <option value="Temporary">Temporary</option>
        <option value="Contractual">Contractual</option>
        <option value="Part-time">Part-time</option>
      </select>
      <select class="filter-select" id="filterLevel" onchange="applyFilters()">
        <option value="">🎓 All Levels</option>
        <option value="Teaching">Teaching</option>
        <option value="Non-Teaching">Non-Teaching</option>
      </select>
      <select class="filter-select" id="filterDepartment" onchange="applyFilters()">
        <option value="">🏢 All Departments</option>
        <?php foreach ($dept_options as $d): ?>
          <option value="<?= htmlspecialchars(strtolower($d['name'])) ?>">
            <?= htmlspecialchars($d['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select class="filter-select" id="filterPosition" onchange="applyFilters()">
        <option value="">📌 All Positions</option>
        <?php foreach ($pos_options as $p): ?>
          <option value="<?= htmlspecialchars(strtolower($p['title'])) ?>">
            <?= htmlspecialchars($p['title']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="filter-clear-btn" id="filterClearBtn" onclick="clearAllFilters()">✕ Clear Filters <span class="filter-count-chip" id="filterCountChip">0</span></button>
    </div>
    <div class="search-results-count" id="teacherCount"></div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>👤 Personnel</th>
            <th>✉️ Email</th>
            <th>📱 Phone</th>
            <th>💼 Position</th>
            <th>🏖 Leave Left</th>
            <th>📅 Date Hired</th>
            <th>🏷 Appt. Status</th>
            <th style="text-align:right;padding-right:20px">⚙️ Actions</th>
          </tr>
        </thead>
        <tbody id="teacherTbody">
          <?php foreach ($teachers_arr as $t):
            $colors = ['t-avatar','t-avatar-b','t-avatar-c'];
            $cl = $colors[$t['id'] % 3];
            $t_absent = $leave_total_absent[$t['id']] ?? 0;
            $t_alloc  = !empty($t['leave_days_allocation']) ? (int)$t['leave_days_allocation'] : $total_leave_days;
            $t_leave_left = max(0, $t_alloc - $t_absent);
          ?>
          <tr data-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
            data-subject="<?= htmlspecialchars(strtolower($t['subject'] ?? '')) ?>"
            data-dept="<?= htmlspecialchars(strtolower($t['department'] ?? '')) ?>"
            data-email="<?= htmlspecialchars(strtolower($t['email'])) ?>"
            data-username="<?= htmlspecialchars(strtolower($t['username'])) ?>"
            data-appt="<?= htmlspecialchars($t['appointment_status'] ?? '') ?>"
            data-emptype="<?= htmlspecialchars($t['employment_type'] ?? '') ?>"
            data-level="<?= htmlspecialchars($t['personnel_level'] ?? '') ?>">

          <!-- Personnel -->
          <td>
            <div class="teacher-name teacher-link" onclick='openTeacherProfile(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>
              <div class="t-avatar <?= $cl ?>"><?= mb_strtoupper(mb_substr($t['full_name'],0,1)) ?></div>
              <div class="t-info">
                <strong class="searchable-name"><?= htmlspecialchars($t['full_name']) ?></strong>
                <span>@<?= htmlspecialchars($t['username']) ?> · <?= htmlspecialchars($t['department'] ?: '—') ?></span>
              </div>
            </div>
          </td>

          <!-- Email -->
          <td>
            <div style="display:flex;flex-direction:column;gap:1px">
              <span style="font-size:12px;color:var(--text)"><?= htmlspecialchars(explode('@',$t['email'])[0]) ?></span>
              <span style="font-size:10px;color:var(--muted);font-family:'JetBrains Mono',monospace">@<?= htmlspecialchars(explode('@',$t['email'])[1] ?? '') ?></span>
            </div>
          </td>

          <!-- Phone -->
          <td style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--text)">
            <?= $t['phone'] ? htmlspecialchars($t['phone']) : '<span style="color:var(--muted)">—</span>' ?>
          </td>

          <!-- Position -->
          <td>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:12px;font-weight:600"><?= htmlspecialchars($t['subject'] ?: '—') ?></span>
              <span style="font-size:10px;color:var(--muted)"><?= htmlspecialchars($t['employment_type'] ?: 'Employment N/A') ?></span>
            </div>
          </td>


          <!-- Leave Left -->
          <td>
            <?php
              $pct = $t_alloc > 0 ? round(($t_leave_left / $t_alloc) * 100) : 0;
              $barColor = $t_leave_left <= 5 ? '#c8102e' : ($t_leave_left <= 10 ? '#d97706' : '#059669');
            ?>
            <div style="display:flex;flex-direction:column;gap:4px">
              <div style="display:flex;align-items:baseline;gap:4px">
                <span style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:14px;color:<?= $barColor ?>"><?= $t_leave_left ?></span>
                <span style="color:var(--muted);font-size:10px">/ <?= $t_alloc ?> days</span>
              </div>
              <div style="height:4px;background:var(--border);border-radius:999px;overflow:hidden;width:70px">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:999px;transition:width 0.3s"></div>
              </div>
            </div>
          </td>

          <!-- Date Hired -->
          <td>
            <div style="display:flex;flex-direction:column;gap:1px">
              <span style="font-size:12px;font-family:'JetBrains Mono',monospace"><?= $t['date_hired'] ? date('M d, Y', strtotime($t['date_hired'])) : '—' ?></span>
              <?php if ($t['date_hired']): 
                $years = floor((time() - strtotime($t['date_hired'])) / (365.25 * 86400));
              ?>
                <span style="font-size:10px;color:var(--muted)"><?= $years ?> yr<?= $years != 1 ? 's' : '' ?> of service</span>
              <?php endif; ?>
            </div>
          </td>

          <!-- Appointment Status -->
          <td>
            <?php
              $apptColors = [
                'Regular'    => ['bg'=>'rgba(5,150,105,0.12)',  'color'=>'#059669',  'dot'=>'#059669'],
                'Newly Hired'=> ['bg'=>'rgba(200,16,46,0.1)',   'color'=>'#c8102e',  'dot'=>'#c8102e'],
                'Transferee' => ['bg'=>'rgba(217,119,6,0.12)',  'color'=>'#d97706',  'dot'=>'#d97706'],
                'Reinstated' => ['bg'=>'rgba(123,31,162,0.12)', 'color'=>'#7b1fa2',  'dot'=>'#7b1fa2'],
              ];
              $ac = $apptColors[$t['appointment_status']] ?? ['bg'=>'rgba(156,123,126,0.12)','color'=>'#9c7b7e','dot'=>'#9c7b7e'];
            ?>
            <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;background:<?= $ac['bg'] ?>;color:<?= $ac['color'] ?>;border-radius:20px;font-size:11px;font-weight:600;font-family:'JetBrains Mono',monospace">
              <span style="width:6px;height:6px;border-radius:50%;background:<?= $ac['dot'] ?>;flex-shrink:0"></span>
              <?= htmlspecialchars($t['appointment_status']) ?>
            </span>
          </td>

          <!-- Actions -->
          <td style="text-align:right;padding-right:20px">
            <div class="action-wrap">
              <button class="action-btn">Actions <span class="chevron">▾</span></button>
              <div class="action-dropdown">
                <button class="action-dropdown-item item-view" onclick='openTeacherProfile(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>
                  <span class="item-icon">👁</span> View Profile
                </button>
                <button class="action-dropdown-item item-edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>, <?= (int)($t['leave_days_allocation'] ?? 30) ?>)'>
                  <span class="item-icon">✏️</span> Edit Info
                </button>
                <div class="action-dropdown-divider"></div>
                <button class="action-dropdown-item item-delete" onclick="confirmDelete(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['full_name'])) ?>')">
                  <span class="item-icon">🗑️</span> Remove
                </button>
              </div>
            </div>
          </td>
        </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="pagination-wrap" id="paginationWrap">
        <div class="pagination-info" id="paginationInfo"></div>
        <div class="pagination-btns" id="paginationBtns"></div>
      </div>
    </div>
  </div>
</main>

<!-- ════════════════════════════════════════════════════
     TEACHER PROFILE MODAL
════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="teacherProfileModal">
  <div class="modal-lg">
    <div class="pm-header">
      <div class="pm-avatar" id="pmAvatar"></div>
      <div class="pm-title">
        <h2 id="pmName"></h2>
        <p id="pmEmail"></p>
        <div class="tags" id="pmTags"></div>
      </div>
      <button class="pm-close" onclick="document.getElementById('teacherProfileModal').classList.remove('open')">✕</button>
    </div>
    <div class="pm-tabs">
      <button class="pm-tab active" onclick="switchPmTab('info',this)">👤 Personal Info</button>
      <button class="pm-tab" onclick="switchPmTab('docs',this)">📄 Documents <span class="tab-badge" id="pmDocTabCount">0</span></button>
      <button class="pm-tab tab-purple" onclick="switchPmTab('credit',this)">📋 Service Credit <span class="tab-badge" id="pmCreditTabCount">0</span></button>
    </div>
    <div class="pm-panel active" id="pmPanelInfo">
      <!-- Personal Information -->
      <div class="edit-section-title" style="margin-top:0">🪪 Personal Information</div>
      <div class="info-grid">
        <div class="info-item"><label>Full Name</label><span id="pmFullName"></span></div>
        <div class="info-item"><label>Username</label><span id="pmUsername" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Gender</label><span id="pmGender"></span></div>
        <div class="info-item"><label>Civil Status</label><span id="pmCivilStatus"></span></div>
        <div class="info-item"><label>Date of Birth</label><span id="pmDOB" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Place of Birth</label><span id="pmPOB"></span></div>
        <div class="info-item"><label>Email</label><span id="pmEmailInfo"></span></div>
        <div class="info-item"><label>Phone</label><span id="pmPhone"></span></div>
        <div class="info-item" style="grid-column:1/-1"><label>Address</label><span id="pmAddress"></span></div>
      </div>

      <!-- Employment Details -->
      <div class="edit-section-title">💼 Employment Details</div>
      <div class="info-grid">
        <div class="info-item"><label>Subject / Position</label><span id="pmSubject"></span></div>
        <div class="info-item"><label>Department</label><span id="pmDepartment"></span></div>
        <div class="info-item"><label>Employment Type</label><span id="pmEmploymentType"></span></div>
        <div class="info-item"><label>Date Hired</label><span id="pmDateHired" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Appointment Status</label><span id="pmAppointmentStatus"></span></div>
      </div>

      <!-- Government Numbers -->
      <div class="edit-section-title">🏛️ Government-Issued Numbers</div>
      <div class="info-grid">
        <div class="info-item"><label>TIN Number</label><span id="pmTIN" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>PhilHealth Number</label><span id="pmPhilHealth" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Pag-IBIG / HDMF</label><span id="pmPagIBIG" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>SSS / GSIS Number</label><span id="pmSSS" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>CRN Number</label><span id="pmCRN" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>BP Number</label><span id="pmBP" style="font-family:'JetBrains Mono',monospace"></span></div>
      </div>
    </div>
    <div class="pm-panel" id="pmPanelDocs">
      <div style="color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace;margin-bottom:14px;text-transform:uppercase;letter-spacing:1px">
        Uploaded Documents <span class="doc-count-badge" id="pmDocCount"></span>
      </div>
      <div style="max-height:420px;overflow-y:auto;padding-right:4px" id="pmDocsList"></div>
    </div>
    <div class="pm-panel" id="pmPanelCredit">
      <div class="sc-stats" id="pmScStats"></div>
      <div class="sc-progress-wrap">
        <div class="sc-progress-label"><span>Leave Consumption</span><span id="pmScProgressLabel"></span></div>
        <div class="progress-bar-bg"><div class="progress-bar-fill" id="pmScProgressFill" style="width:0%"></div></div>
      </div>
      <div style="color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px">Absent Logs</div>
      <div style="max-height:360px;overflow-y:auto;padding-right:4px" id="pmLeaveList"></div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════════════
     ADD PERSONNEL MODAL (4-step wizard)
════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addPersonnelModal">
  <div class="ap-modal">

    <div class="progress-bar-top">
      <div class="progress-fill-top" id="apProgressFill" style="width:25%"></div>
    </div>

    <div class="ap-modal-header">
      <div class="ap-modal-header-left">
        <div class="ap-modal-icon">👤</div>
        <div>
          <h2>Add New Personnel</h2>
          <p id="apStepLabel">Step 1 of 4 — Personal Information</p>
        </div>
      </div>
      <button class="ap-modal-close" onclick="closeAddPersonnel()">✕</button>
    </div>

    <div class="step-tabs">
      <button class="step-tab active" id="apTab1" onclick="apGoToStep(1)"><span class="step-num">1</span> Personal Info</button>
      <button class="step-tab" id="apTab2" onclick="apGoToStep(2)"><span class="step-num">2</span> Employment</button>
      <button class="step-tab" id="apTab3" onclick="apGoToStep(3)"><span class="step-num">3</span> Gov't Numbers</button>
      <button class="step-tab" id="apTab4" onclick="apGoToStep(4)"><span class="step-num">4</span> Review &amp; Submit</button>
    </div>

    <form method="POST" action="manage_personnel.php" id="apForm">
      <input type="hidden" name="action" value="add_personnel">
    </form>

    <div class="ap-modal-body">

      <!-- STEP 1 -->
      <div class="step-panel active" id="apPanel1">
        <div class="section-label-ap">🪪 Personal Information</div>
        <div class="form-grid-3">
          <div class="fg">
            <label>Last Name <span class="req">*</span></label>
            <div class="iw"><input type="text" id="apLastName" placeholder="Dela Cruz" oninput="apValName('apLastName','apLastNameErr','Last name')"><span class="ii" id="apLastNameIcon"></span></div>
            <span class="field-error" id="apLastNameErr"></span>
          </div>
          <div class="fg">
            <label>First Name <span class="req">*</span></label>
            <div class="iw"><input type="text" id="apFirstName" placeholder="Juan" oninput="apValName('apFirstName','apFirstNameErr','First name')"><span class="ii" id="apFirstNameIcon"></span></div>
            <span class="field-error" id="apFirstNameErr"></span>
          </div>
          <div class="fg">
            <label>Middle Name</label>
            <input type="text" id="apMiddleName" placeholder="Santos">
          </div>
        </div>
        <div class="form-grid-3">
          <div class="fg">
            <label>Suffix</label>
            <select id="apSuffix">
              <option value="">— None —</option>
              <option value="Jr.">Jr.</option>
              <option value="Sr.">Sr.</option>
              <option value="II">II</option>
              <option value="III">III</option>
              <option value="IV">IV</option>
            </select>
          </div>
          <div class="fg">
            <label>Gender <span class="req">*</span></label>
            <select id="apGender" onchange="apValSelect('apGender','apGenderErr','Gender')">
              <option value="">— Select —</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <span class="field-error" id="apGenderErr"></span>
          </div>
          <div class="fg">
            <label>Civil Status <span class="req">*</span></label>
            <select id="apCivilStatus" onchange="apValSelect('apCivilStatus','apCivilStatusErr','Civil status')">
              <option value="">— Select —</option>
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
              <option value="Separated">Separated</option>
            </select>
            <span class="field-error" id="apCivilStatusErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Date of Birth <span class="req">*</span></label>
            <input type="date" id="apDOB" onchange="apValDOB()">
            <span class="field-error" id="apDOBErr"></span>
          </div>
          <div class="fg">
            <label>Place of Birth <span class="req">*</span></label>
            <input type="text" id="apPOB" placeholder="Manila, Philippines" oninput="apValReq('apPOB','apPOBErr','Place of birth')">
            <span class="field-error" id="apPOBErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Email Address <span class="req">*</span></label>
            <div class="iw"><input type="email" id="apEmail" placeholder="jdelacruz@school.edu" oninput="apValEmail()"><span class="ii" id="apEmailIcon"></span></div>
            <span class="field-error" id="apEmailErr"></span>
          </div>
          <div class="fg">
            <label>Contact Number <span class="req">*</span></label>
            <div class="iw"><input type="text" id="apPhone" placeholder="09XXXXXXXXX" maxlength="11" oninput="apValPhone()"><span class="ii" id="apPhoneIcon"></span></div>
            <span class="field-hint">11 digits only (e.g. 09171234567)</span>
            <span class="field-error" id="apPhoneErr"></span>
          </div>
        </div>
        <div class="form-grid-1">
          <div class="fg">
            <label>Complete Residence Address &amp; ZIP Code <span class="req">*</span></label>
            <input type="text" id="apAddress" placeholder="123 Rizal St., Barangay 1, Manila, Metro Manila, 1000" oninput="apValReq('apAddress','apAddressErr','Address')">
            <span class="field-error" id="apAddressErr"></span>
          </div>
        </div>
      </div>

      <!-- STEP 2 -->
      <div class="step-panel" id="apPanel2">
        <div class="section-label-ap">💼 Account &amp; Employment Details</div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Username <span class="req">*</span></label>
            <div class="iw"><input type="text" id="apUsername" placeholder="jdelacruz" oninput="apValUsername()"><span class="ii" id="apUsernameIcon"></span></div>
            <span class="field-hint">Lowercase letters, numbers, dots, underscores only</span>
            <span class="field-error" id="apUsernameErr"></span>
          </div>
          <div class="fg">
            <label>Password <span class="req">*</span></label>
            <div class="iw"><input type="password" id="apPassword" placeholder="••••••••" oninput="apValPassword()"><span class="ii" id="apPasswordIcon"></span></div>
            <span class="field-hint">Minimum 8 characters</span>
            <span class="field-error" id="apPasswordErr"></span>
          </div>
        </div>
        <!-- Field/Level Selector -->
        <div class="form-grid-1" style="margin-bottom:14px">
          <div class="fg">
            <label>Field / Level <span class="req">*</span></label>
            <div style="display:flex;gap:10px;margin-top:4px">
              <label class="level-card" id="levelCardTeaching" onclick="apSelectLevel('Teaching')">
                <input type="radio" name="apLevel" value="Teaching" style="display:none">
                <span class="level-icon">🎓</span>
                <span class="level-title">Teaching</span>
                <span class="level-desc">Has position & department</span>
              </label>
              <label class="level-card" id="levelCardNonTeaching" onclick="apSelectLevel('Non-Teaching')">
                <input type="radio" name="apLevel" value="Non-Teaching" style="display:none">
                <span class="level-icon">🏢</span>
                <span class="level-title">Non-Teaching</span>
                <span class="level-desc">Has position only</span>
              </label>
            </div>
            <span class="field-error" id="apLevelErr"></span>
          </div>
        </div>

        <!-- Position & Department (shown after level is chosen) -->
        <div class="form-grid-2" id="apPosDeptRow" style="display:none">
          <div class="fg">
            <label>Position / Designation <span class="req">*</span></label>
            <select id="apSubject" onchange="apValSelect('apSubject','apSubjectErr','Position')">
              <option value="">— Select Position —</option>
              <?php foreach ($pos_options as $p): ?>
                <option value="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-error" id="apSubjectErr"></span>
          </div>
          <div class="fg" id="apDeptField">
            <label>Department <span class="req" id="apDeptReq">*</span></label>
            <select id="apDepartment" onchange="apValSelect('apDepartment','apDepartmentErr','Department')">
              <option value="">— Select Department —</option>
              <?php foreach ($dept_options as $d): ?>
                <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="field-error" id="apDepartmentErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Employment Type</label>
            <select id="apEmpType">
              <option value="">— Select —</option>
              <option value="Permanent">Permanent</option>
              <option value="Temporary">Temporary</option>
              <option value="Contractual">Contractual</option>
              <option value="Part-time">Part-time</option>
            </select>
          </div>
          <div class="fg">
            <label>Date Hired <span class="req">*</span></label>
            <input type="date" id="apDateHired" onchange="apValReq('apDateHired','apDateHiredErr','Date hired')">
            <span class="field-error" id="apDateHiredErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Appointment Status <span class="req">*</span></label>
            <select id="apAppointmentStatus">
              <option value="Newly Hired">Newly Hired</option>
              <option value="Regular">Regular</option>
              <option value="Transferee">Transferee</option>
              <option value="Reinstated">Reinstated</option>
            </select>
            <span class="field-hint">Current appointment classification</span>
          </div>
          <div class="fg">
            <label>Leave Days Allocation <span class="req">*</span></label>
            <input type="number" id="apLeaveDays" value="30" min="1" max="365" oninput="apValLeaveDays()">
            <span class="field-error" id="apLeaveDaysErr"></span>
          </div>
        </div>
      </div>

      <!-- STEP 3 -->
      <div class="step-panel" id="apPanel3">
        <div class="section-label-ap">🏛️ Government-Issued Numbers <span style="color:var(--muted);font-weight:400;text-transform:none;letter-spacing:0">(all optional)</span></div>
        <div class="form-grid-2">
          <div class="fg">
            <label>TIN Number</label>
            <div class="iw"><input type="text" id="apTIN" placeholder="000-000-000-000" maxlength="15" oninput="apValTIN()"><span class="ii" id="apTINIcon"></span></div>
            <span class="field-hint">9 or 12 digits</span>
            <span class="field-error" id="apTINErr"></span>
          </div>
          <div class="fg">
            <label>PhilHealth Number</label>
            <div class="iw"><input type="text" id="apPhilHealth" placeholder="00-000000000-0" maxlength="14" oninput="apValPhilHealth()"><span class="ii" id="apPhilHealthIcon"></span></div>
            <span class="field-hint">12-digit PhilHealth ID</span>
            <span class="field-error" id="apPhilHealthErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Pag-IBIG / HDMF Number</label>
            <div class="iw"><input type="text" id="apPagIBIG" placeholder="0000-0000-0000" maxlength="14" oninput="apValPagIBIG()"><span class="ii" id="apPagIBIGIcon"></span></div>
            <span class="field-hint">12-digit MID Number</span>
            <span class="field-error" id="apPagIBIGErr"></span>
          </div>
          <div class="fg">
            <label>SSS / GSIS Number</label>
            <div class="iw"><input type="text" id="apSSS" placeholder="00-0000000-0" maxlength="12" oninput="apValSSS()"><span class="ii" id="apSSSIcon"></span></div>
            <span class="field-hint">SSS: 10 digits | GSIS: 11 digits</span>
            <span class="field-error" id="apSSSErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>CRN Number</label>
            <div class="iw"><input type="text" id="apCRN" placeholder="0000-0000000-0" maxlength="14" oninput="apValCRN()"><span class="ii" id="apCRNIcon"></span></div>
            <span class="field-hint">Common Reference Number</span>
            <span class="field-error" id="apCRNErr"></span>
          </div>
          <div class="fg">
            <label>BP Number</label>
            <div class="iw"><input type="text" id="apBP" placeholder="0000-0000-0000-0" maxlength="16"><span class="ii" id="apBPIcon"></span></div>
            <span class="field-hint">Barangay / Business Permit Number</span>
          </div>
        </div>
      </div>

      <!-- STEP 4 -->
      <div class="step-panel" id="apPanel4">
        <div class="section-label-ap">✅ Review &amp; Confirm</div>
        <p style="color:var(--muted);font-size:13px;margin-bottom:18px">Please review all information before submitting. Go back to any step to make changes.</p>
        <div class="summary-section-title">🪪 Personal Information</div>
        <div class="summary-grid">
          <div class="summary-item"><label>Full Name</label><span id="sr_name">—</span></div>
          <div class="summary-item"><label>Gender</label><span id="sr_gender">—</span></div>
          <div class="summary-item"><label>Civil Status</label><span id="sr_civil">—</span></div>
          <div class="summary-item"><label>Date of Birth</label><span id="sr_dob" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>Place of Birth</label><span id="sr_pob">—</span></div>
          <div class="summary-item"><label>Contact Number</label><span id="sr_phone" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>Email</label><span id="sr_email">—</span></div>
          <div class="summary-item" style="grid-column:span 2"><label>Address</label><span id="sr_address">—</span></div>
        </div>
        <div class="summary-section-title">💼 Employment</div>
        <div class="summary-grid">
          <div class="summary-item"><label>Username</label><span id="sr_username" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>Position / Subject</label><span id="sr_subject">—</span></div>
          <div class="summary-item"><label>Department</label><span id="sr_dept">—</span></div>
          <div class="summary-item"><label>Date Hired</label><span id="sr_hired" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>Employment Type</label><span id="sr_emptype">—</span></div>
          <div class="summary-item"><label>Appointment Status</label><span id="sr_appt">—</span></div>
          <div class="summary-item"><label>Leave Allocation</label><span id="sr_leave" style="font-family:'JetBrains Mono',monospace">—</span></div>
        </div>
        <div class="summary-section-title">🏛️ Government Numbers</div>
        <div class="summary-grid">
          <div class="summary-item"><label>TIN</label><span id="sr_tin" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>PhilHealth</label><span id="sr_philhealth" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>Pag-IBIG / HDMF</label><span id="sr_pagibig" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>SSS / GSIS</label><span id="sr_sss" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>CRN</label><span id="sr_crn" style="font-family:'JetBrains Mono',monospace">—</span></div>
          <div class="summary-item"><label>BP Number</label><span id="sr_bp" style="font-family:'JetBrains Mono',monospace">—</span></div>
        </div>
      </div>

    </div>

    <div class="ap-modal-footer">
      <div class="step-dots">
        <div class="step-dot active" id="apDot1"></div>
        <div class="step-dot" id="apDot2"></div>
        <div class="step-dot" id="apDot3"></div>
        <div class="step-dot" id="apDot4"></div>
      </div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-ghost" id="apBtnBack" onclick="apPrevStep()" style="display:none">← Back</button>
        <button class="btn btn-ghost" onclick="closeAddPersonnel()">Cancel</button>
        <button class="btn btn-primary" id="apBtnNext" onclick="apNextStep()">Next →</button>
        <button class="btn btn-success" id="apBtnSubmit" onclick="apSubmit()" style="display:none">✅ Add Personnel</button>
      </div>
    </div>

  </div>
</div>

<!-- EDIT TEACHER MODAL -->
<div class="modal-overlay" id="editTeacherModal">
  <div class="modal-sm" style="max-width:640px">
    <h2>✏️ Edit Personnel</h2>
    <form method="POST" id="editTeacherForm">
      <input type="hidden" name="action" value="edit_teacher">
      <input type="hidden" name="teacher_id" id="editTeacherId">
      <div class="edit-section-title">👤 Personal Information</div>
      <div class="form-row">
        <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="editFullName" required></div>
        <div class="form-group"><label>Email *</label><input type="email" name="email" id="editEmail" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="editPhone" placeholder="09XXXXXXXXX"></div>
        <div class="form-group"><label>Date Hired</label><input type="date" name="date_hired" id="editDateHired"></div>
      </div>
    
      <div class="form-row">
        <div class="form-group"><label>Address</label><input type="text" name="address" id="editAddress" placeholder="Street, City"></div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="editStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="edit-section-title">💼 Account & Employment Details</div>
      <div class="form-row">
        <div class="form-group"><label>Username</label><input type="text" name="username" id="editUsername" required></div>
        <div class="form-group"><label>Position / Designation</label>
          <select name="subject" id="editSubject">
            <option value="">— Select Position —</option>
            <?php foreach ($pos_options as $p): ?>
              <option value="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
      </div>
      <div class="form-row">
        <div class="form-group"><label>Department</label>
          <select name="department" id="editDepartment">
            <option value="">— Select Department —</option>
            <?php foreach ($dept_options as $d): ?>
              <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Employment Type</label>
        <select  name="employment_type" id="editEmployment">
              <option value="">— Select —</option>
              <option value="Permanent">Permanent</option>
              <option value="Temporary">Temporary</option>
              <option value="Contractual">Contractual</option>
              <option value="Part-time">Part-time</option>
            </select>
          </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Appointment Status</label>
          <select name="appointment_status" id="editAppointmentStatus">
            <option value="Newly Hired">Newly Hired</option>
            <option value="Regular">Regular</option>
            <option value="Transferee">Transferee</option>
            <option value="Reinstated">Reinstated</option>
          </select>
        </div>
        <div class="form-group">
          <label>Field / Level</label>
          <div style="display:flex;gap:10px;margin-top:4px">
            <label class="level-card" id="editLevelCardTeaching" onclick="editSelectLevel('Teaching')" style="flex:1;padding:10px 8px">
              <span class="level-icon">🎓</span>
              <span class="level-title" style="font-size:12px">Teaching</span>
            </label>
            <label class="level-card" id="editLevelCardNonTeaching" onclick="editSelectLevel('Non-Teaching')" style="flex:1;padding:10px 8px">
              <span class="level-icon">🏢</span>
              <span class="level-title" style="font-size:12px">Non-Teaching</span>
            </label>
          </div>
          <input type="hidden" name="personnel_level" id="editPersonnelLevel">
        </div>
      </div>

      <div class="edit-section-title">🏛️ Government-Issued Numbers Details</div>
      <div class="form-grid-2">
          <div class="fg">
            <label>TIN Number</label>
            <div class="iw"><input type="text" name="tin_number" id="editTinNumber" placeholder="000-000-000-000" maxlength="15" oninput="apValTIN()"><span class="ii" id="apTINIcon"></span></div>
            <span class="field-hint">9 or 12 digits</span>
            <span class="field-error" id="apTINErr"></span>
          </div>
          <div class="fg">
            <label>PhilHealth Number</label>
            <div class="iw"><input type="text" name="philhealth_number" id="editPhilHealthNumber" placeholder="00-000000000-0" maxlength="14" oninput="apValPhilHealth()"><span class="ii" id="apPhilHealthIcon"></span></div>
            <span class="field-hint">12-digit PhilHealth ID</span>
            <span class="field-error" id="apPhilHealthErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>Pag-IBIG / HDMF Number</label>
            <div class="iw"><input type="text" name="pagibig_number" id="editPagibigNumber" placeholder="0000-0000-0000" maxlength="14" oninput="apValPagIBIG()"><span class="ii" id="apPagIBIGIcon"></span></div>
            <span class="field-hint">12-digit MID Number</span>
            <span class="field-error" id="apPagIBIGErr"></span>
          </div>
          <div class="fg">
            <label>SSS / GSIS Number</label>
            <div class="iw"><input type="text" name="sssgsis_number" id="editSSSGSISNumber" placeholder="00-0000000-0" maxlength="12" oninput="apValSSS()"><span class="ii" id="apSSSIcon"></span></div>
            <span class="field-hint">SSS: 10 digits | GSIS: 11 digits</span>
            <span class="field-error" id="apSSSErr"></span>
          </div>
        </div>
        <div class="form-grid-2">
          <div class="fg">
            <label>CRN Number</label>
            <div class="iw"><input type="text" name="crn_number" id="editCrnNumber" placeholder="0000-0000000-0" maxlength="14" oninput="apValCRN()"><span class="ii" id="apCRNIcon"></span></div>
            <span class="field-hint">Common Reference Number</span>
            <span class="field-error" id="apCRNErr"></span>
          </div>
          <div class="fg">
            <label>BP Number</label>
            <div class="iw"><input type="text" name="bp_number" id="editBpNumber" placeholder="0000-0000-0000-0" maxlength="16"><span class="ii" id="apBPIcon"></span></div>
            <span class="field-hint">Barangay / Business Permit Number</span>
          </div>
        </div>


      <div class="edit-section-title">🏖 Leave Days Allocation</div>
      <div class="form-group">
        <label>Annual Leave Days Allocation</label>
        <div class="leave-alloc-wrap">
          <input type="number" name="leave_days_allocation" id="editLeaveAlloc" min="1" max="365" value="30">
          <span class="leave-alloc-note">days per year (default: 30)</span>
        </div>
      </div>
      <div class="edit-section-title">🔑 Change Password <span style="font-weight:400;text-transform:none;letter-spacing:0">(leave blank to keep current)</span></div>
      <div class="form-row">
        <div class="form-group"><label>New Password</label><input type="password" name="new_password" id="editNewPassword" placeholder="Leave blank to keep current"></div>
        <div class="form-group"><label>Confirm Password</label><input type="password" id="editConfirmPassword" placeholder="Repeat new password"></div>
      </div>
      <div id="editPwMismatch" style="color:var(--danger);font-size:12px;margin-top:-10px;margin-bottom:10px;display:none">⚠️ Passwords do not match.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('editTeacherModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
const docsByTeacher    = <?= $docs_by_teacher_json ?>;
const leaveByTeacher   = <?= $leave_by_teacher_json ?>;
const leaveTotalAbsent = <?= $leave_total_json ?>;
const TOTAL_LEAVE_DAYS = <?= $total_leave_days ?>;

const avatarColors   = ['pm-avatar-a','pm-avatar-b','pm-avatar-c'];
const fileIcons      = { pdf:'📕',doc:'📘',docx:'📘',xls:'📗',xlsx:'📗',ppt:'📙',pptx:'📙',jpg:'🖼',jpeg:'🖼',png:'🖼',gif:'🖼',zip:'📦',rar:'📦',txt:'📄',csv:'📊' };
const leaveTypeClass = { 'Sick Leave':'leave-sick','Vacation Leave':'leave-vacation','Emergency Leave':'leave-emergency','Maternity Leave':'leave-maternity','Other':'leave-other' };
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function getExt(f){return(f||'').split('.').pop().toLowerCase();}
function getIcon(f){return fileIcons[getExt(f)]||'📄';}
function fmtSize(b){if(!b)return'—';if(b<1024)return b+' B';if(b<1048576)return(b/1024).toFixed(1)+' KB';return(b/1048576).toFixed(1)+' MB';}
function fmtDate(s){if(!s)return'—';return new Date(s).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'});}
function fmtDateChip(s){const d=new Date(s+'T00:00:00');return MONTHS[d.getMonth()].substring(0,3)+' '+d.getDate();}

document.querySelectorAll('.modal-overlay').forEach(o=>{
  o.addEventListener('click',e=>{if(e.target===o)o.classList.remove('open');});
});

/* ── TEACHER PROFILE ─────────────────────────────────── */
function openTeacherProfile(t){
  const av=document.getElementById('pmAvatar');
  av.textContent=t.full_name[0].toUpperCase();
  av.className='pm-avatar '+avatarColors[t.id%3];
  document.getElementById('pmName').innerHTML = `${t.full_name} <span class="badge badge-${t.status}" style="font-size:11px;vertical-align:middle">${t.status.charAt(0).toUpperCase()+t.status.slice(1)}</span>`;
  document.getElementById('pmEmail').textContent=t.email;
  const tags=document.getElementById('pmTags');tags.innerHTML='';
  if(t.subject)    tags.innerHTML+=`<span class="tag tag-blue">${t.subject}</span>`;
  if(t.department) tags.innerHTML+=`<span class="tag tag-green">${t.department}</span>`;
  const totalAbsent=leaveTotalAbsent[t.id]||0;
  const leaveLeft=Math.max(0,TOTAL_LEAVE_DAYS-totalAbsent);
  tags.innerHTML+=leaveLeft<=5?`<span class="tag tag-red">⚠ ${leaveLeft} leave days left</span>`:`<span class="tag tag-green">🏖 ${leaveLeft} leave days left</span>`;
  if(totalAbsent>0)tags.innerHTML+=`<span class="tag tag-red">❌ ${totalAbsent} days absent</span>`;

  // Personal
  document.getElementById('pmFullName').textContent    = t.full_name;
  document.getElementById('pmUsername').textContent    = '@' + t.username;
  document.getElementById('pmGender').textContent      = t.gender || '—';
  document.getElementById('pmCivilStatus').textContent = t.civil_status || '—';
  document.getElementById('pmDOB').textContent         = t.date_of_birth || '—';
  document.getElementById('pmPOB').textContent         = t.place_of_birth || '—';
  document.getElementById('pmEmailInfo').textContent   = t.email;
  document.getElementById('pmPhone').textContent       = t.phone || '—';
  document.getElementById('pmAddress').textContent     = t.address || '—';

  // Employment
  document.getElementById('pmSubject').textContent        = t.subject || '—';
  document.getElementById('pmDepartment').textContent     = t.department || '—';
  document.getElementById('pmEmploymentType').textContent = t.employment_type || '—';
  document.getElementById('pmDateHired').textContent      = t.date_hired || '—';
  document.getElementById('pmAppointmentStatus').textContent = t.appointment_status || '—';


  // Government Numbers
  document.getElementById('pmTIN').textContent       = t.tin_number || '—';
  document.getElementById('pmPhilHealth').textContent = t.philhealth_number || '—';
  document.getElementById('pmPagIBIG').textContent   = t.pagibig_number || '—';
  document.getElementById('pmSSS').textContent       = t.sss_number || '—';
  document.getElementById('pmCRN').textContent       = t.crn_number || '—';
  document.getElementById('pmBP').textContent        = t.bp_number || '—';

  const docs=docsByTeacher[t.id]||[];
  document.getElementById('pmDocTabCount').textContent=docs.length;
  document.getElementById('pmDocCount').textContent  =docs.length+(docs.length===1?' file':' files');
  document.getElementById('pmDocsList').innerHTML=docs.length===0?`<div class="no-content"><span class="no-content-icon">📭</span><p>No documents uploaded yet.</p></div>`:
    docs.map(d=>`<div class="doc-row"><span class="doc-icon">${getIcon(d.file_name)}</span><div class="doc-info"><strong>${d.title}</strong><span>${d.file_name} · ${fmtSize(d.file_size)} · ${fmtDate(d.uploaded_at)}</span></div><div class="doc-actions"><a class="btn btn-view btn-sm" href="${d.file_path}" target="_blank">👁 View</a><a class="btn btn-download btn-sm" href="${d.file_path}" download="${d.file_name}">⬇</a></div></div>`).join('');
  const leaveRecords=leaveByTeacher[t.id]||[];
  document.getElementById('pmCreditTabCount').textContent=leaveRecords.length;
  renderServiceCreditPanel(t.id,leaveRecords,totalAbsent,leaveLeft);
  document.getElementById('teacherProfileModal').classList.add('open');
  switchPmTab('info',document.querySelectorAll('.pm-tab')[0]);
}
document.querySelectorAll('.action-wrap').forEach(wrap => {
  const btn = wrap.querySelector('.action-btn');
  const dropdown = wrap.querySelector('.action-dropdown');

  btn.addEventListener('mouseenter', () => {
    const rect = btn.getBoundingClientRect();
    dropdown.style.top  = (rect.bottom + 5) + 'px';
    dropdown.style.left = (rect.right - dropdown.offsetWidth || rect.right - 148) + 'px';
    dropdown.style.opacity = '1';
    dropdown.style.visibility = 'visible';
    dropdown.style.transform = 'translateY(0)';
  });

  wrap.addEventListener('mouseleave', () => {
    dropdown.style.opacity = '0';
    dropdown.style.visibility = 'hidden';
    dropdown.style.transform = 'translateY(-4px)';
  });
});

function renderServiceCreditPanel(tid,records,totalAbsent,leaveLeft){
  const pct=Math.min(100,Math.round((totalAbsent/TOTAL_LEAVE_DAYS)*100));
  const barClass=pct>=80?'danger-fill':pct>=50?'warn-fill':'';
  document.getElementById('pmScStats').innerHTML=`
    <div class="sc-stat green"><div class="sc-icon">🏖️</div><div class="sc-num">${TOTAL_LEAVE_DAYS}</div><div class="sc-label">Total Allocation</div></div>
    <div class="sc-stat ${leaveLeft<=5?'danger':'blue'}"><div class="sc-icon">${leaveLeft<=5?'⚠️':'✅'}</div><div class="sc-num" style="color:${leaveLeft<=5?'var(--danger)':leaveLeft<=10?'var(--warn)':'var(--accent)'}">${leaveLeft}</div><div class="sc-label">Leave Days Available</div></div>
    <div class="sc-stat danger"><div class="sc-icon">📅</div><div class="sc-num" style="color:var(--danger)">${totalAbsent}</div><div class="sc-label">Days Absent</div></div>
    <div class="sc-stat purple"><div class="sc-icon">📝</div><div class="sc-num" style="color:var(--purple)">${records.length}</div><div class="sc-label">Reports Filed</div></div>`;
  document.getElementById('pmScProgressLabel').textContent=`${totalAbsent} / ${TOTAL_LEAVE_DAYS} days (${pct}%)`;
  const fill=document.getElementById('pmScProgressFill');
  fill.style.width=pct+'%';
  fill.className='progress-bar-fill'+(barClass?' '+barClass:'');
  const leaveList=document.getElementById('pmLeaveList');
  if(records.length===0){leaveList.innerHTML=`<div class="no-content"><span class="no-content-icon">📋</span><p>No leave records filed yet.</p></div>`;return;}
  leaveList.innerHTML=records.map(r=>{
    const dates=r.absent_dates?r.absent_dates.split(',').filter(Boolean):[];
    const ltClass=leaveTypeClass[r.leave_type]||'leave-other';
    const chips=dates.map(d=>`<span class="date-chip">${fmtDateChip(d.trim())}</span>`).join('');
    const scanBtn=r.scan_file_path?`<a class="btn btn-purple btn-sm" href="${r.scan_file_path}" target="_blank">📎 Scan</a>`:`<span style="color:var(--muted);font-size:11px">No scan</span>`;
    return `<div class="leave-log-row"><div class="leave-log-header"><strong>${r.last_name}, ${r.first_name}</strong><div class="leave-log-meta"><span class="leave-type-badge ${ltClass}">${r.leave_type}</span><span style="color:var(--danger);font-weight:700;font-family:'JetBrains Mono',monospace">${r.days_absent} day${r.days_absent!=1?'s':''}</span></div></div>${dates.length?`<div class="leave-log-dates">${chips}</div>`:''} ${r.reason?`<div style="font-size:12px;color:var(--muted);margin-top:6px">${r.reason}</div>`:''}<div class="leave-log-footer"><small>Filed: ${fmtDate(r.filed_at)}</small>${scanBtn}</div></div>`;
  }).join('');
}

function switchPmTab(tab,el){
  document.querySelectorAll('#teacherProfileModal .pm-tab').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('#teacherProfileModal .pm-panel').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById({info:'pmPanelInfo',docs:'pmPanelDocs',credit:'pmPanelCredit'}[tab]).classList.add('active');
}

/* ── DELETE CONFIRMATION ─────────────────────────────── */
function confirmDelete(id, name) {
  Swal.fire({
    icon: 'warning',
    title: 'Remove Personnel?',
    html: `Are you sure you want to remove <strong>${name}</strong>?<br><span style="font-size:12px;color:#9c7b7e">This action cannot be undone.</span>`,
    showCancelButton: true,
    confirmButtonText: '🗑️ Yes, Remove',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#c8102e',
    cancelButtonColor: '#9c7b7e',
    reverseButtons: true,
    customClass: { popup: 'swal-custom' }
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'manage_personnel.php';
      form.innerHTML = `
        <input type="hidden" name="action" value="delete_teacher">
        <input type="hidden" name="teacher_id" value="${id}">
      `;
      document.body.appendChild(form);
      form.submit();
    }
  });
}


/* ── EDIT MODAL ──────────────────────────────────────── */
function openEditModal(t,leaveAlloc){
  document.getElementById('editTeacherId').value     =t.id;
  document.getElementById('editFullName').value      =t.full_name||'';
  document.getElementById('editUsername').value      =t.username||'';
  document.getElementById('editEmail').value         =t.email||'';
  document.getElementById('editPhone').value         =t.phone||'';
   document.getElementById('editEmployment').value     =t.employment_type||'';
  document.getElementById('editDateHired').value     =t.date_hired||'';
  document.getElementById('editAppointmentStatus').value = t.appointment_status || 'Regular';
  document.getElementById('editSubject').value       =t.subject||'';
  document.getElementById('editDepartment').value    =t.department||'';
  document.getElementById('editAddress').value       =t.address||'';
  document.getElementById('editStatus').value        =t.status||'active';
  document.getElementById('editTinNumber').value    =t.tin_number||'';
  document.getElementById('editPhilHealthNumber').value    =t.philhealth_number||'';
  document.getElementById('editPagibigNumber').value    =t.pagibig_number||'';
  document.getElementById('editSSSGSISNumber').value    =t.sss_number||'';
  document.getElementById('editCrnNumber').value    =t.crn_number||'';
  document.getElementById('editBpNumber').value    =t.bp_number||'';
  document.getElementById('editLeaveAlloc').value    =leaveAlloc||30;
  document.getElementById('editNewPassword').value   ='';
  document.getElementById('editConfirmPassword').value='';
  document.getElementById('editPwMismatch').style.display='none';
  // Set level cards
  const lvl = t.personnel_level || '';
  document.getElementById('editPersonnelLevel').value = lvl;
  document.getElementById('editLevelCardTeaching').classList.toggle('selected', lvl === 'Teaching');
  document.getElementById('editLevelCardNonTeaching').classList.toggle('selected', lvl === 'Non-Teaching');
  document.getElementById('editTeacherModal').classList.add('open');
}
document.getElementById('editTeacherForm').addEventListener('submit',function(e){
  const pw=document.getElementById('editNewPassword').value;
  const cpw=document.getElementById('editConfirmPassword').value;
  if(pw&&pw!==cpw){e.preventDefault();document.getElementById('editPwMismatch').style.display='block';document.getElementById('editConfirmPassword').focus();}
});
document.getElementById('editConfirmPassword').addEventListener('input',function(){
  const pw=document.getElementById('editNewPassword').value;
  document.getElementById('editPwMismatch').style.display=(pw&&this.value&&pw!==this.value)?'block':'none';
});


function renderPage(tbody, countEl, q, totalCount) {
  const allRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  const total = filteredRows.length;
  const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;

  const start = (currentPage - 1) * ROWS_PER_PAGE;
  const end = start + ROWS_PER_PAGE;

  allRows.forEach(row => row.style.display = 'none');
  filteredRows.forEach((row, i) => { row.style.display = (i >= start && i < end) ? '' : 'none'; });

  tbody.querySelector('.no-results-row')?.remove();
  if (total === 0 && q) {
    const cols = tbody.closest('table').querySelectorAll('thead th').length;
    const nr = document.createElement('tr'); nr.className = 'no-results-row';
    nr.innerHTML = `<td colspan="${cols}"><span class="nr-icon">🔍</span>No teachers match "<strong>${q}</strong>"</td>`;
    tbody.appendChild(nr);
  }

  if (countEl) {
    if (!q) countEl.innerHTML = '';
    else if (total === 0) countEl.innerHTML = `No teachers found for "<span>${q}</span>"`;
    else countEl.innerHTML = `Showing <span>${Math.min(end, total) - start}</span> of ${total} result${total !== 1 ? 's' : ''}`;
  }

  renderPagination(total, totalPages, totalCount, start, end, tbody, countEl, q);
}

function renderPagination(total, totalPages, totalCount, start, end, tbody, countEl, q) {
  const info = document.getElementById('paginationInfo');
  const btns = document.getElementById('paginationBtns');
  if (!info || !btns) return;

  const showing = Math.min(end, total) - start;
  info.innerHTML = `Showing <span>${total === 0 ? 0 : start + 1}–${Math.min(end, total)}</span> of <span>${total}</span> personnel`;

  let html = `<button class="page-btn" onclick="changePage(-1)" ${currentPage === 1 ? 'disabled' : ''}>‹</button>`;
  for (let i = 1; i <= totalPages; i++) {
    if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
      if (i === 3 || i === totalPages - 2) html += `<button class="page-btn" disabled>…</button>`;
      continue;
    }
    html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
  }
  html += `<button class="page-btn" onclick="changePage(1)" ${currentPage === totalPages ? 'disabled' : ''}>›</button>`;
  btns.innerHTML = html;
}

function changePage(dir) {
  const tbody = document.getElementById('teacherTbody');
  const countEl = document.getElementById('teacherCount');
  const q = document.getElementById('teacherSearch').value.trim().toLowerCase();
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / ROWS_PER_PAGE));
  currentPage = Math.max(1, Math.min(totalPages, currentPage + dir));
  renderPage(tbody, countEl, q, filteredRows.length);
}

function goToPage(page) {
  const tbody = document.getElementById('teacherTbody');
  const countEl = document.getElementById('teacherCount');
  const q = document.getElementById('teacherSearch').value.trim().toLowerCase();
  currentPage = page;
  renderPage(tbody, countEl, q, filteredRows.length);
}

function clearSearch(inputId, tbodyId, countId) {
  const input = document.getElementById(inputId); input.value = '';
  filterTable(inputId, tbodyId, countId); input.focus();
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('teacherTbody');
  filteredRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  renderPage(tbody, document.getElementById('teacherCount'), '', filteredRows.length);
});

/* ── ADD PERSONNEL WIZARD ────────────────────────────── */
let apStep = 1;
const AP_TOTAL = 4;
const AP_LABELS = [
  'Step 1 of 4 — Personal Information',
  'Step 2 of 4 — Employment Details',
  'Step 3 of 4 — Government Numbers',
  'Step 4 of 4 — Review & Submit'
];

function openAddPersonnel() {
  document.getElementById('addPersonnelModal').classList.add('open');
  apGoToStep(1);
}
function closeAddPersonnel() {
  document.getElementById('levelCardTeaching').classList.remove('selected');
  document.getElementById('levelCardNonTeaching').classList.remove('selected');
  document.getElementById('apPosDeptRow').style.display = 'none';
  document.querySelector('input[name="apLevel"]:checked') && (document.querySelector('input[name="apLevel"]:checked').checked = false);
  document.getElementById('addPersonnelModal').classList.remove('open');
}
function apGoToStep(step) {
  if (step < 1 || step > AP_TOTAL) return;
  if (step > apStep && !apValidateCurrent()) return;
  apStep = step;
  apUpdateUI();
}
function apNextStep() {
  if (!apValidateCurrent()) return;
  if (apStep < AP_TOTAL) { apStep++; if (apStep === AP_TOTAL) apBuildSummary(); apUpdateUI(); }
}
function apPrevStep() { if (apStep > 1) { apStep--; apUpdateUI(); } }

function apUpdateUI() {
  for (let i = 1; i <= AP_TOTAL; i++) {
    document.getElementById('apPanel'+i).classList.toggle('active', i === apStep);
    const tab = document.getElementById('apTab'+i);
    tab.classList.toggle('active', i === apStep);
    tab.classList.toggle('done', i < apStep);
    const dot = document.getElementById('apDot'+i);
    dot.className = 'step-dot'+(i===apStep?' active':'')+(i<apStep?' done':'');
  }
  document.getElementById('apStepLabel').textContent = AP_LABELS[apStep-1];
  document.getElementById('apProgressFill').style.width = (apStep/AP_TOTAL*100)+'%';
  document.getElementById('apBtnBack').style.display   = apStep>1?'inline-flex':'none';
  document.getElementById('apBtnNext').style.display   = apStep<AP_TOTAL?'inline-flex':'none';
  document.getElementById('apBtnSubmit').style.display = apStep===AP_TOTAL?'inline-flex':'none';
}

function apValidateCurrent() {
  if (apStep===1) return apValStep1();
  if (apStep===2) return apValStep2();
  return true;
}
function apValStep1() {
  let ok = true;
  ok = apValName('apLastName','apLastNameErr','Last name') && ok;
  ok = apValName('apFirstName','apFirstNameErr','First name') && ok;
  ok = apValSelect('apGender','apGenderErr','Gender') && ok;
  ok = apValSelect('apCivilStatus','apCivilStatusErr','Civil status') && ok;
  ok = apValDOB() && ok;
  ok = apValReq('apPOB','apPOBErr','Place of birth') && ok;
  ok = apValEmail() && ok;
  ok = apValPhone() && ok;
  ok = apValReq('apAddress','apAddressErr','Address') && ok;
  return ok;
}
function apValStep2() {
  let ok = true;

  // Username & password
  ok = apValUsername() && ok;
  ok = apValPassword() && ok;

  // Level/field selection
  const levelChosen = document.querySelector('input[name="apLevel"]:checked');
  if (!levelChosen) {
    document.getElementById('apLevelErr').textContent = 'Please select a field/level.';
    document.getElementById('apLevelErr').classList.add('show');
    ok = false;
  } else {
    document.getElementById('apLevelErr').textContent = '';
    document.getElementById('apLevelErr').classList.remove('show');
  }

  // Position (always required once level is chosen)
  ok = apValSelect('apSubject', 'apSubjectErr', 'Position') && ok;

  // Department (only required for Teaching)
  const isTeaching = levelChosen && levelChosen.value === 'Teaching';
  if (isTeaching) {
    ok = apValSelect('apDepartment', 'apDepartmentErr', 'Department') && ok;
  } else {
    document.getElementById('apDepartmentErr').textContent = '';
    document.getElementById('apDepartmentErr').classList.remove('show');
  }

  // Date hired & leave days
  ok = apValReq('apDateHired', 'apDateHiredErr', 'Date hired') && ok;
  ok = apValLeaveDays() && ok;

  return ok;
}

function apSet(id,iconId,errId,valid,msg){
  const inp=document.getElementById(id);
  const err=errId?document.getElementById(errId):null;
  const ico=iconId?document.getElementById(iconId):null;
  inp.classList.toggle('ap-error',!valid&&!!msg);
  inp.classList.toggle('ap-valid',valid&&inp.value.trim()!=='');
  if(err){err.textContent=msg||'';err.classList.toggle('show',!!msg&&!valid);}
  if(ico)ico.textContent=inp.value.trim()?(valid?'✅':'❌'):'';
  return valid;
}
function apValName(id,errId,label){
  const v=document.getElementById(id).value.trim();
  if(!v)return apSet(id,id+'Icon',errId,false,label+' is required.');
  if(/[0-9!@#$%^&*()\[\]{};':"\\|,.<>/?]/.test(v))return apSet(id,id+'Icon',errId,false,label+' must contain letters only.');
  return apSet(id,id+'Icon',errId,true,'');
}
function apValReq(id,errId,label){
  const v=document.getElementById(id).value.trim();
  if(!v)return apSet(id,null,errId,false,label+' is required.');
  return apSet(id,null,errId,true,'');
}
function apValSelect(id,errId,label){
  const v=document.getElementById(id).value;
  if(!v)return apSet(id,null,errId,false,'Please select a '+label.toLowerCase()+'.');
  return apSet(id,null,errId,true,'');
}
function apValEmail(){
  const v=document.getElementById('apEmail').value.trim();
  if(!v)return apSet('apEmail','apEmailIcon','apEmailErr',false,'Email is required.');
  if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))return apSet('apEmail','apEmailIcon','apEmailErr',false,'Enter a valid email address.');
  return apSet('apEmail','apEmailIcon','apEmailErr',true,'');
}
function apValPhone(){
  const v=document.getElementById('apPhone').value.trim();
  if(!v)return apSet('apPhone','apPhoneIcon','apPhoneErr',false,'Contact number is required.');
  if(/[^0-9]/.test(v))return apSet('apPhone','apPhoneIcon','apPhoneErr',false,'Digits only — no letters or symbols.');
  if(v.length!==11)return apSet('apPhone','apPhoneIcon','apPhoneErr',false,'Must be exactly 11 digits.');
  if(!v.startsWith('09'))return apSet('apPhone','apPhoneIcon','apPhoneErr',false,'Must start with 09.');
  return apSet('apPhone','apPhoneIcon','apPhoneErr',true,'');
}
function apValDOB(){
  const v=document.getElementById('apDOB').value;
  if(!v)return apSet('apDOB',null,'apDOBErr',false,'Date of birth is required.');
  const age=Math.floor((new Date()-new Date(v))/(365.25*24*3600*1000));
  if(age<18)return apSet('apDOB',null,'apDOBErr',false,'Must be at least 18 years old.');
  if(age>80)return apSet('apDOB',null,'apDOBErr',false,'Please check the date of birth.');
  return apSet('apDOB',null,'apDOBErr',true,'');
}
function apValUsername(){
  const v=document.getElementById('apUsername').value.trim();
  if(!v)return apSet('apUsername','apUsernameIcon','apUsernameErr',false,'Username is required.');
  if(/[^a-z0-9._]/.test(v))return apSet('apUsername','apUsernameIcon','apUsernameErr',false,'Lowercase letters, numbers, dots, underscores only.');
  if(v.length<3)return apSet('apUsername','apUsernameIcon','apUsernameErr',false,'At least 3 characters.');
  return apSet('apUsername','apUsernameIcon','apUsernameErr',true,'');
}
function apValPassword(){
  const v=document.getElementById('apPassword').value;
  if(!v)return apSet('apPassword','apPasswordIcon','apPasswordErr',false,'Password is required.');
  if(v.length<8)return apSet('apPassword','apPasswordIcon','apPasswordErr',false,'At least 8 characters.');
  return apSet('apPassword','apPasswordIcon','apPasswordErr',true,'');
}
function apValLeaveDays(){
  const v=parseInt(document.getElementById('apLeaveDays').value);
  if(isNaN(v)||v<1)return apSet('apLeaveDays',null,'apLeaveDaysErr',false,'At least 1 day.');
  if(v>365)return apSet('apLeaveDays',null,'apLeaveDaysErr',false,'Cannot exceed 365 days.');
  return apSet('apLeaveDays',null,'apLeaveDaysErr',true,'');
}
function apValTIN(){
  const v=document.getElementById('apTIN').value.trim();
  if(!v)return apSet('apTIN','apTINIcon','apTINErr',true,'');
  const d=v.replace(/-/g,'');
  if(/[^0-9\-]/.test(v))return apSet('apTIN','apTINIcon','apTINErr',false,'Digits and dashes only.');
  if(d.length!==9&&d.length!==12)return apSet('apTIN','apTINIcon','apTINErr',false,'TIN must be 9 or 12 digits.');
  return apSet('apTIN','apTINIcon','apTINErr',true,'');
}
function apValPhilHealth(){
  const v=document.getElementById('apPhilHealth').value.trim();
  if(!v)return apSet('apPhilHealth','apPhilHealthIcon','apPhilHealthErr',true,'');
  const d=v.replace(/[-\s]/g,'');
  if(/[^0-9\-\s]/.test(v))return apSet('apPhilHealth','apPhilHealthIcon','apPhilHealthErr',false,'Digits only.');
  if(d.length!==12)return apSet('apPhilHealth','apPhilHealthIcon','apPhilHealthErr',false,'Must be 12 digits.');
  return apSet('apPhilHealth','apPhilHealthIcon','apPhilHealthErr',true,'');
}
function apValPagIBIG(){
  const v=document.getElementById('apPagIBIG').value.trim();
  if(!v)return apSet('apPagIBIG','apPagIBIGIcon','apPagIBIGErr',true,'');
  const d=v.replace(/[-\s]/g,'');
  if(/[^0-9\-\s]/.test(v))return apSet('apPagIBIG','apPagIBIGIcon','apPagIBIGErr',false,'Digits only.');
  if(d.length!==12)return apSet('apPagIBIG','apPagIBIGIcon','apPagIBIGErr',false,'Must be 12 digits.');
  return apSet('apPagIBIG','apPagIBIGIcon','apPagIBIGErr',true,'');
}
function apValSSS(){
  const v=document.getElementById('apSSS').value.trim();
  if(!v)return apSet('apSSS','apSSSIcon','apSSSErr',true,'');
  const d=v.replace(/[-\s]/g,'');
  if(/[^0-9\-\s]/.test(v))return apSet('apSSS','apSSSIcon','apSSSErr',false,'Digits only.');
  if(d.length<10||d.length>11)return apSet('apSSS','apSSSIcon','apSSSErr',false,'SSS: 10 digits | GSIS: 11 digits.');
  return apSet('apSSS','apSSSIcon','apSSSErr',true,'');
}
function apValCRN(){
  const v=document.getElementById('apCRN').value.trim();
  if(!v)return apSet('apCRN','apCRNIcon','apCRNErr',true,'');
  if(/[^0-9\-\s]/.test(v))return apSet('apCRN','apCRNIcon','apCRNErr',false,'Digits and dashes only.');
  return apSet('apCRN','apCRNIcon','apCRNErr',true,'');
}

function apBuildSummary(){
  const sfx=document.getElementById('apSuffix').value;
  const full=[document.getElementById('apFirstName').value,document.getElementById('apMiddleName').value,document.getElementById('apLastName').value,sfx?'('+sfx+')':''].filter(Boolean).join(' ');
  document.getElementById('sr_name').textContent    =full||'—';
  document.getElementById('sr_gender').textContent  =document.getElementById('apGender').value||'—';
  document.getElementById('sr_civil').textContent   =document.getElementById('apCivilStatus').value||'—';
  document.getElementById('sr_dob').textContent     =document.getElementById('apDOB').value||'—';
  document.getElementById('sr_pob').textContent     =document.getElementById('apPOB').value||'—';
  document.getElementById('sr_phone').textContent   =document.getElementById('apPhone').value||'—';
  document.getElementById('sr_email').textContent   =document.getElementById('apEmail').value||'—';
  document.getElementById('sr_address').textContent =document.getElementById('apAddress').value||'—';
  document.getElementById('sr_username').textContent='@'+(document.getElementById('apUsername').value||'—');
  document.getElementById('sr_subject').textContent =document.getElementById('apSubject').value||'—';
  document.getElementById('sr_dept').textContent    =document.getElementById('apDepartment').value||'—';
  document.getElementById('sr_hired').textContent   =document.getElementById('apDateHired').value||'—';
  document.getElementById('sr_emptype').textContent =document.getElementById('apEmpType').value||'—';
  document.getElementById('sr_appt').textContent = document.getElementById('apAppointmentStatus').value||'—';
  document.getElementById('sr_leave').textContent   =document.getElementById('apLeaveDays').value+' days';
  document.getElementById('sr_tin').textContent        =document.getElementById('apTIN').value||'—';
  document.getElementById('sr_philhealth').textContent =document.getElementById('apPhilHealth').value||'—';
  document.getElementById('sr_pagibig').textContent    =document.getElementById('apPagIBIG').value||'—';
  document.getElementById('sr_sss').textContent        =document.getElementById('apSSS').value||'—';
  document.getElementById('sr_crn').textContent        =document.getElementById('apCRN').value||'—';
  document.getElementById('sr_bp').textContent         =document.getElementById('apBP').value||'—';
}

function apSubmit(){
  const btn=document.getElementById('apBtnSubmit');
  btn.disabled=true;
  btn.innerHTML='⏳ Saving...';
  const form=document.getElementById('apForm');
  const fields={
    last_name:             document.getElementById('apLastName').value,
    first_name:            document.getElementById('apFirstName').value,
    middle_name:           document.getElementById('apMiddleName').value,
    suffix:                document.getElementById('apSuffix').value,
    gender:                document.getElementById('apGender').value,
    civil_status:          document.getElementById('apCivilStatus').value,
    date_of_birth:         document.getElementById('apDOB').value,
    place_of_birth:        document.getElementById('apPOB').value,
    email:                 document.getElementById('apEmail').value,
    phone:                 document.getElementById('apPhone').value,
    address:               document.getElementById('apAddress').value,
    username:              document.getElementById('apUsername').value,
    password:              document.getElementById('apPassword').value,
    personnel_level: document.querySelector('input[name="apLevel"]:checked')?.value || '',
    subject:               document.getElementById('apSubject').value,
    department:            document.getElementById('apDepartment').value,
    employment_type:       document.getElementById('apEmpType').value,
    appointment_status:    document.getElementById('apAppointmentStatus').value,
    date_hired:            document.getElementById('apDateHired').value,
    leave_days_allocation: document.getElementById('apLeaveDays').value,
    tin_number:            document.getElementById('apTIN').value,
    philhealth_number:     document.getElementById('apPhilHealth').value,
    pagibig_number:        document.getElementById('apPagIBIG').value,
    sss_number:            document.getElementById('apSSS').value,
    crn_number:            document.getElementById('apCRN').value,
    bp_number:             document.getElementById('apBP').value,
  };
  form.querySelectorAll('input:not([name="action"])').forEach(i=>i.remove());
  Object.entries(fields).forEach(([name,value])=>{
    const inp=document.createElement('input');
    inp.type='hidden';inp.name=name;inp.value=value;
    form.appendChild(inp);
  });
  form.submit();
}

function apSelectLevel(level) {
  // Highlight selected card
  document.getElementById('levelCardTeaching').classList.toggle('selected', level === 'Teaching');
  document.getElementById('levelCardNonTeaching').classList.toggle('selected', level === 'Non-Teaching');

  // Show the position/department row
  document.getElementById('apPosDeptRow').style.display = 'grid';

  const deptField = document.getElementById('apDeptField');
  const deptSelect = document.getElementById('apDepartment');
  const deptReq = document.getElementById('apDeptReq');

  if (level === 'Teaching') {
    // Department enabled and required
    deptField.style.opacity = '1';
    deptField.style.pointerEvents = 'auto';
    deptSelect.disabled = false;
    deptReq.style.display = 'inline';
  } else {
    // Non-Teaching: department still available (optional)
    deptField.style.opacity = '1';
    deptField.style.pointerEvents = 'auto';
    deptSelect.disabled = false;
    deptReq.style.display = 'none'; // not required for Non-Teaching
  }

  // Reset position when switching
  document.getElementById('apSubject').value = '';
  document.getElementById('apSubjectErr').textContent = '';
  document.getElementById('apLevelErr').textContent = '';
}

const ROWS_PER_PAGE = 10;
let currentPage = 1;
let filteredRows = [];


function getActiveFilters() {
  return {
    appt:    document.getElementById('filterApptStatus').value,
    emptype: document.getElementById('filterEmpType').value,
    dept:    document.getElementById('filterDepartment').value,
    subject: document.getElementById('filterPosition').value,
    level: document.getElementById('filterLevel').value,
  };
}

function editSelectLevel(level) {
  document.getElementById('editLevelCardTeaching').classList.toggle('selected', level === 'Teaching');
  document.getElementById('editLevelCardNonTeaching').classList.toggle('selected', level === 'Non-Teaching');
  document.getElementById('editPersonnelLevel').value = level;
}

function applyFilters() {
  const filters = getActiveFilters();
  const activeCount = Object.values(filters).filter(Boolean).length;

  // Style active selects
  ['filterApptStatus','filterEmpType','filterLevel','filterDepartment','filterPosition'].forEach(id => {
    const el = document.getElementById(id);
    el.classList.toggle('active-filter', !!el.value);
  });

  // Show/hide clear button
  const clearBtn = document.getElementById('filterClearBtn');
  const chip = document.getElementById('filterCountChip');
  clearBtn.classList.toggle('visible', activeCount > 0);
  chip.textContent = activeCount;

  filterTable('teacherSearch', 'teacherTbody', 'teacherCount');
}

function clearAllFilters() {
    ['filterApptStatus','filterEmpType','filterLevel','filterDepartment','filterPosition'].forEach(id => {
    document.getElementById(id).value = '';
    document.getElementById(id).classList.remove('active-filter');
  });
  document.getElementById('filterClearBtn').classList.remove('visible');
  filterTable('teacherSearch', 'teacherTbody', 'teacherCount');
}

function filterTable(inputId, tbodyId, countId) {
  const q = document.getElementById(inputId).value.trim().toLowerCase();
  const tbody = document.getElementById(tbodyId);
  const countEl = document.getElementById(countId);
  const clearBtn = document.getElementById(inputId + 'Clear');
  const filters = getActiveFilters();
  const allRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';

  filteredRows = allRows.filter(row => {
    const matchSearch = !q || ['name','subject','dept','email','username'].some(a => (row.getAttribute('data-' + a) || '').includes(q));
    const matchAppt    = !filters.appt    || (row.getAttribute('data-appt') || '') === filters.appt;
    const matchEmptype = !filters.emptype || (row.getAttribute('data-emptype') || '').toLowerCase() === filters.emptype.toLowerCase();
    const matchDept    = !filters.dept    || (row.getAttribute('data-dept') || '').toLowerCase() === filters.dept.toLowerCase();
    const matchSubject = !filters.subject || (row.getAttribute('data-subject') || '').toLowerCase() === filters.subject.toLowerCase();
    const matchLevel   = !filters.level   || (row.getAttribute('data-level') || '').toLowerCase() === filters.level.toLowerCase();

    const match = matchSearch && matchAppt && matchEmptype && matchDept && matchSubject && matchLevel;

    if (match && q) {
      const el = row.querySelector('.searchable-name');
      if (el) highlightText(el, q);
    } else if (!q) {
      const el = row.querySelector('.searchable-name');
      if (el) { const o = el.getAttribute('data-original'); if (o) el.innerHTML = o; }
    }
    return match;
  });

  currentPage = 1;
  renderPage(tbody, countEl, q, allRows.length);
}

function renderPage(tbody, countEl, q, totalCount) {
  const allRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  const total = filteredRows.length;
  const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;

  const start = (currentPage - 1) * ROWS_PER_PAGE;
  const end = start + ROWS_PER_PAGE;

  allRows.forEach(row => row.style.display = 'none');
  filteredRows.forEach((row, i) => { row.style.display = (i >= start && i < end) ? '' : 'none'; });

  tbody.querySelector('.no-results-row')?.remove();
  const hasFilters = Object.values(getActiveFilters()).some(Boolean);
  if (total === 0 && (q || hasFilters)) {
    const cols = tbody.closest('table').querySelectorAll('thead th').length;
    const nr = document.createElement('tr'); nr.className = 'no-results-row';
    nr.innerHTML = `<td colspan="${cols}"><span class="nr-icon">🔍</span>No personnel match the current filters.</td>`;
    tbody.appendChild(nr);
  }

  if (countEl) {
    if (!q && !hasFilters) countEl.innerHTML = '';
    else if (total === 0) countEl.innerHTML = `No results found`;
    else countEl.innerHTML = `Showing <span>${Math.min(end, total) - start}</span> of <span>${total}</span> result${total !== 1 ? 's' : ''}`;
  }

  renderPagination(total, totalPages, totalCount, start, end, tbody, countEl, q);
}

function renderPagination(total, totalPages, totalCount, start, end, tbody, countEl, q) {
  const info = document.getElementById('paginationInfo');
  const btns = document.getElementById('paginationBtns');
  if (!info || !btns) return;

  info.innerHTML = `Showing <span>${total === 0 ? 0 : start + 1}–${Math.min(end, total)}</span> of <span>${total}</span> personnel`;

  let html = `<button class="page-btn" onclick="changePage(-1)" ${currentPage === 1 ? 'disabled' : ''}>‹</button>`;
  for (let i = 1; i <= totalPages; i++) {
    if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
      if (i === 3 || i === totalPages - 2) html += `<button class="page-btn" disabled>…</button>`;
      continue;
    }
    html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
  }
  html += `<button class="page-btn" onclick="changePage(1)" ${currentPage === totalPages ? 'disabled' : ''}>›</button>`;
  btns.innerHTML = html;
}

function changePage(dir) {
  const tbody = document.getElementById('teacherTbody');
  const countEl = document.getElementById('teacherCount');
  const q = document.getElementById('teacherSearch').value.trim().toLowerCase();
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / ROWS_PER_PAGE));
  currentPage = Math.max(1, Math.min(totalPages, currentPage + dir));
  renderPage(tbody, countEl, q, filteredRows.length);
}

function goToPage(page) {
  const tbody = document.getElementById('teacherTbody');
  const countEl = document.getElementById('teacherCount');
  const q = document.getElementById('teacherSearch').value.trim().toLowerCase();
  currentPage = page;
  renderPage(tbody, countEl, q, filteredRows.length);
}

function clearSearch(inputId, tbodyId, countId) {
  const input = document.getElementById(inputId); input.value = '';
  filterTable(inputId, tbodyId, countId); input.focus();
}

// Initialize on load
document.addEventListener('DOMContentLoaded', () => {
  const tbody = document.getElementById('teacherTbody');
  filteredRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  renderPage(tbody, document.getElementById('teacherCount'), '', filteredRows.length);
});

/* ── SWEETALERT TRIGGERS ─────────────────────────────── */
<?php if ($swal_success): ?>
Swal.fire({
  icon: 'success',
  title: 'Personnel Added!',
  html: `<strong><?= $swal_name ?></strong> has been successfully registered and their account has been created.`,
  confirmButtonText: 'Great!',
  confirmButtonColor: '#c8102e',
  timer: 6000,
  timerProgressBar: true,
  customClass: { popup: 'swal-custom' }
});
<?php elseif ($swal_error): ?>
Swal.fire({
  icon: 'error',
  title: 'Something went wrong',
  text: '<?= $swal_error ?>',
  confirmButtonText: 'Try Again',
  confirmButtonColor: '#c8102e',
  customClass: { popup: 'swal-custom' }
});
<?php endif; ?>
</script>
</body>
</html>