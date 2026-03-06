<?php
// teacher_profile.php — MY PROFILE
require_once 'teacher_shared.php';

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // UPDATE PROFILE
    if ($_POST['action'] === 'update_profile') {
        $first_name         = trim($_POST['first_name']         ?? '');
        $last_name          = trim($_POST['last_name']          ?? '');
        $middle_name        = trim($_POST['middle_name']        ?? '');
        $suffix             = trim($_POST['suffix']             ?? '');
        $email              = trim($_POST['email']              ?? '');
        $phone              = trim($_POST['phone']              ?? '');
        $gender             = trim($_POST['gender']             ?? '');
        $civil_status       = trim($_POST['civil_status']       ?? '');
        $date_of_birth      = trim($_POST['date_of_birth']      ?? '') ?: null;
        $place_of_birth     = trim($_POST['place_of_birth']     ?? '');
        $address            = trim($_POST['address']            ?? '');
        $subject            = trim($_POST['subject']            ?? '');
        $department         = trim($_POST['department']         ?? '');
        $employment_type    = trim($_POST['employment_type']    ?? '');
        $appointment_status = trim($_POST['appointment_status'] ?? '');
        $tin_number         = trim($_POST['tin_number']         ?? '');
        $philhealth_number  = trim($_POST['philhealth_number']  ?? '');
        $pagibig_number     = trim($_POST['pagibig_number']     ?? '');
        $sss_number         = trim($_POST['sss_number']         ?? '');
        $crn_number         = trim($_POST['crn_number']         ?? '');
        $bp_number          = trim($_POST['bp_number']          ?? '');

        if (!$first_name || !$last_name || !$email) {
            $err = 'First name, last name, and email are required.';
        } else {
            $s = $conn->prepare("UPDATE personnels SET
                first_name=?, last_name=?, middle_name=?, suffix=?,
                email=?, phone=?, gender=?, civil_status=?,
                date_of_birth=?, place_of_birth=?, address=?,
                subject=?, department=?, employment_type=?, appointment_status=?,
                tin_number=?, philhealth_number=?, pagibig_number=?,
                sss_number=?, crn_number=?, bp_number=?
                WHERE id=?");
            $s->bind_param('sssssssssssssssssssssi',
                $first_name, $last_name, $middle_name, $suffix,
                $email, $phone, $gender, $civil_status,
                $date_of_birth, $place_of_birth, $address,
                $subject, $department, $employment_type, $appointment_status,
                $tin_number, $philhealth_number, $pagibig_number,
                $sss_number, $crn_number, $bp_number,
                $teacher_id
            );
            if ($s->execute()) {
                $msg = 'Profile updated successfully!';
                $stmt = $conn->prepare("SELECT t.*, u.username FROM personnels t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $teacher = $stmt->get_result()->fetch_assoc();
            } else {
                $err = 'Update failed: ' . $s->error;
            }
        }
    }

    // CHANGE PASSWORD
    if ($_POST['action'] === 'change_password') {
        $current_pw = trim($_POST['current_password'] ?? '');
        $new_pw     = trim($_POST['new_password']     ?? '');
        $confirm_pw = trim($_POST['confirm_password'] ?? '');

        if (!$current_pw || !$new_pw || !$confirm_pw) {
            $err = 'Please fill in all password fields.';
        } elseif ($new_pw !== $confirm_pw) {
            $err = 'New passwords do not match.';
        } elseif (strlen($new_pw) < 6) {
            $err = 'Password must be at least 6 characters.';
        } else {
            $stmt_pw = $conn->prepare("SELECT password FROM users WHERE id=?");
            $stmt_pw->bind_param('i', $user_id);
            $stmt_pw->execute();
            $row = $stmt_pw->get_result()->fetch_assoc();
            if ($row && password_verify($current_pw, $row['password'])) {
                $hash     = password_hash($new_pw, PASSWORD_BCRYPT);
                $stmt_upw = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt_upw->bind_param('si', $hash, $user_id);
                $stmt_upw->execute();
                $msg = 'Password changed successfully!';
            } else {
                $err = 'Current password is incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Profile - TMCNHS Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<style>
<?php readfile(__DIR__.'/teacher_style.css.php'); ?>
.section-divider{font-size:11px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:2px;color:var(--muted);margin:28px 0 16px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.info-item{padding:14px 0;border-bottom:1px solid var(--border)}
.info-item:nth-child(odd){padding-right:24px;border-right:1px solid var(--border)}
.info-item:nth-child(even){padding-left:24px}
.info-item label{font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;color:var(--muted);display:block;margin-bottom:4px}
.info-item span{font-size:14px;font-weight:500}
.info-item.full-row{grid-column:1/-1;padding-right:0;border-right:none;padding-left:0}
.pw-form{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:28px;margin-top:28px}
.subsection{font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1.5px;color:var(--accent);margin:20px 0 12px;padding-bottom:6px;border-bottom:1px dashed var(--border)}
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-img-wrap">
      <img src="../uploads/logo/TMCNHS-Logo.png" alt="TMCNHS Logo" onerror="this.style.display='none'">
    </div>
    <div class="sidebar-logo-text">
      <h2>Trece Martires City<br>National High School</h2>
      <span>Teacher Portal</span>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-label">Main</div>
    <a class="nav-item" href="teacher_dashboard.php"><span class="ni">&#128202;</span> Dashboard</a>
    <a class="nav-item" href="teacher_documents.php"><span class="ni">&#128193;</span> My Documents</a>
    <a class="nav-item" href="teacher_service_credit.php"><span class="ni">&#128203;</span> Service Credit</a>
    <a class="nav-item active" href="teacher_profile.php"><span class="ni">&#128100;</span> My Profile</a>
  </nav>
  <div class="sidebar-footer"><div class="user-chip">
    <div class="avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($teacher['full_name']) ?></strong><small>Teacher</small></div>
    <a href="../logout.php" class="logout-btn" title="Logout" style="font-size: 1.5rem;">&#9167;</a>
  </div></div>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>My Profile</h1><p>View and edit your personal information</p></div>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">&#9888;&#65039; <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- PROFILE HEADER -->
    <div class="profile-hero">
      <div class="prof-avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
      <div class="prof-info">
        <h2><?= htmlspecialchars($teacher['full_name']) ?></h2>
        <p>@<?= htmlspecialchars($teacher['username']) ?> &middot; <?= htmlspecialchars($teacher['email']) ?></p>
        <div class="prof-tags">
          <?php if ($teacher['subject']): ?><span class="tag tag-green"><?= htmlspecialchars($teacher['subject']) ?></span><?php endif; ?>
          <?php if ($teacher['department']): ?><span class="tag tag-blue"><?= htmlspecialchars($teacher['department']) ?></span><?php endif; ?>
          <span class="tag <?= $teacher['status']==='active'?'tag-green':'tag-red' ?>"><?= ucfirst($teacher['status'] ?? 'active') ?></span>
          <?php if ($teacher['date_hired']): ?><span class="tag tag-purple">&#128197; Hired <?= htmlspecialchars($teacher['date_hired']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CURRENT INFO READ-ONLY -->
    <div class="section-divider">&#128203; Current Information</div>
    <div class="edit-form" style="margin-bottom:0;border-radius:16px 16px 0 0">
      <div class="info-grid">
        <div class="info-item"><label>Full Name</label><span><?= htmlspecialchars($teacher['full_name']) ?></span></div>
        <div class="info-item"><label>Username</label><span style="font-family:'JetBrains Mono',monospace">@<?= htmlspecialchars($teacher['username']) ?></span></div>
        <div class="info-item"><label>Email</label><span><?= htmlspecialchars($teacher['email']) ?></span></div>
        <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($teacher['phone'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Gender</label><span><?= htmlspecialchars($teacher['gender'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Civil Status</label><span><?= htmlspecialchars($teacher['civil_status'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Date of Birth</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['date_of_birth'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Place of Birth</label><span><?= htmlspecialchars($teacher['place_of_birth'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Subject / Position</label><span><?= htmlspecialchars($teacher['subject'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Department</label><span><?= htmlspecialchars($teacher['department'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Employment Type</label><span><?= htmlspecialchars($teacher['employment_type'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Appointment Status</label><span><?= htmlspecialchars($teacher['appointment_status'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Date Hired</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['date_hired'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Status</label><span><span class="tag <?= $teacher['status']==='active'?'tag-green':'tag-red' ?>"><?= ucfirst($teacher['status'] ?? 'active') ?></span></span></div>
        <div class="info-item"><label>TIN Number</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['tin_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>PhilHealth No.</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['philhealth_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>Pag-IBIG No.</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['pagibig_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>SSS / GSIS No.</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['sss_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>CRN Number</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['crn_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item"><label>BP Number</label><span style="font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($teacher['bp_number'] ?: '&#8212;') ?></span></div>
        <div class="info-item full-row"><label>Address</label><span><?= htmlspecialchars($teacher['address'] ?: '&#8212;') ?></span></div>
      </div>
    </div>

    <!-- EDIT FORM -->
    <div class="section-divider" style="margin-top:32px">&#9999;&#65039; Edit Personal Information</div>
    <div class="edit-form">
      <form method="POST">
        <input type="hidden" name="action" value="update_profile">

        <div class="subsection">&#128100; Personal Details</div>
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?= htmlspecialchars($teacher['first_name']) ?>" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?= htmlspecialchars($teacher['last_name']) ?>" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" value="<?= htmlspecialchars($teacher['middle_name'] ?? '') ?>"></div>
          <div class="form-group"><label>Suffix</label>
            <select name="suffix">
              <?php foreach ([''=>'&#8212; None &#8212;','Jr.'=>'Jr.','Sr.'=>'Sr.','II'=>'II','III'=>'III','IV'=>'IV'] as $val=>$lbl): ?>
                <option value="<?= $val ?>" <?= ($teacher['suffix']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Gender</label>
            <select name="gender">
              <option value="">&#8212; Select &#8212;</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($teacher['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Civil Status</label>
            <select name="civil_status">
              <option value="">&#8212; Select &#8212;</option>
              <?php foreach (['Single','Married','Widowed','Separated'] as $cs): ?>
                <option value="<?= $cs ?>" <?= ($teacher['civil_status']??'')===$cs?'selected':'' ?>><?= $cs ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Date of Birth</label><input type="date" name="date_of_birth" value="<?= htmlspecialchars($teacher['date_of_birth'] ?? '') ?>"></div>
          <div class="form-group"><label>Place of Birth</label><input type="text" name="place_of_birth" value="<?= htmlspecialchars($teacher['place_of_birth'] ?? '') ?>" placeholder="City, Province"></div>
        </div>

        <div class="subsection">&#128222; Contact Information</div>
        <div class="form-row">
          <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?= htmlspecialchars($teacher['email']) ?>" required></div>
          <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?= htmlspecialchars($teacher['phone'] ?? '') ?>" placeholder="09XXXXXXXXX" maxlength="11"></div>
        </div>
        <div class="form-group"><label>Address</label><textarea name="address" rows="2" placeholder="Complete address with ZIP code"><?= htmlspecialchars($teacher['address'] ?? '') ?></textarea></div>

        <div class="subsection">&#127979; Employment Details</div>
        <div class="form-row">
          <div class="form-group"><label>Subject / Position</label><input type="text" name="subject" value="<?= htmlspecialchars($teacher['subject'] ?? '') ?>" placeholder="e.g. Teacher II"></div>
          <div class="form-group"><label>Department</label><input type="text" name="department" value="<?= htmlspecialchars($teacher['department'] ?? '') ?>" placeholder="e.g. Science"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Employment Type</label>
            <select name="employment_type">
              <?php foreach (['Permanent','Temporary','Contractual','Part-time'] as $et): ?>
                <option value="<?= $et ?>" <?= ($teacher['employment_type']??'')===$et?'selected':'' ?>><?= $et ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Appointment Status</label>
            <select name="appointment_status">
              <?php foreach (['Newly Hired','Regular','Transferee','Reinstated'] as $as): ?>
                <option value="<?= $as ?>" <?= ($teacher['appointment_status']??'')===$as?'selected':'' ?>><?= $as ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="subsection">&#129306; Government ID Numbers</div>
        <div class="form-row">
          <div class="form-group"><label>TIN Number</label><input type="text" name="tin_number" value="<?= htmlspecialchars($teacher['tin_number'] ?? '') ?>" placeholder="000-000-000-000" style="font-family:'JetBrains Mono',monospace"></div>
          <div class="form-group"><label>PhilHealth Number</label><input type="text" name="philhealth_number" value="<?= htmlspecialchars($teacher['philhealth_number'] ?? '') ?>" placeholder="12-digit PhilHealth ID" style="font-family:'JetBrains Mono',monospace"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Pag-IBIG / HDMF Number</label><input type="text" name="pagibig_number" value="<?= htmlspecialchars($teacher['pagibig_number'] ?? '') ?>" placeholder="12-digit MID" style="font-family:'JetBrains Mono',monospace"></div>
          <div class="form-group"><label>SSS / GSIS Number</label><input type="text" name="sss_number" value="<?= htmlspecialchars($teacher['sss_number'] ?? '') ?>" placeholder="SSS (10) or GSIS (11) digits" style="font-family:'JetBrains Mono',monospace"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>CRN Number</label><input type="text" name="crn_number" value="<?= htmlspecialchars($teacher['crn_number'] ?? '') ?>" placeholder="Common Reference Number" style="font-family:'JetBrains Mono',monospace"></div>
          <div class="form-group"><label>BP Number</label><input type="text" name="bp_number" value="<?= htmlspecialchars($teacher['bp_number'] ?? '') ?>" placeholder="Barangay / Business Permit No." style="font-family:'JetBrains Mono',monospace"></div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px">&#128190; Save Changes</button>
      </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="section-divider" style="margin-top:32px">&#128273; Change Password</div>
    <div class="pw-form">
      <form method="POST" id="pwForm">
        <input type="hidden" name="action" value="change_password">
        <div class="form-row">
          <div class="form-group"><label>Current Password *</label><input type="password" name="current_password" placeholder="Enter current password" required></div>
          <div class="form-group"><label>New Password *</label><input type="password" name="new_password" id="newPw" placeholder="Min. 6 characters" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Confirm New Password *</label><input type="password" name="confirm_password" id="confirmPw" placeholder="Repeat new password" required></div>
          <div class="form-group" style="display:flex;align-items:flex-end">
            <button type="submit" class="btn btn-primary" style="width:100%">&#128273; Change Password</button>
          </div>
        </div>
        <div id="pwMismatch" style="color:var(--danger);font-size:12px;display:none;margin-top:-8px">&#9888;&#65039; Passwords do not match.</div>
      </form>
    </div>
  </div>
</main>

<div class="success-modal-overlay" id="docSuccessModal" style="display:none"><div class="success-modal doc-success"><div class="confetti-container" id="docConfetti"></div><div class="success-icon-wrap doc-icon">&#128196;<div class="check-badge">&#10003;</div></div><div class="success-title"></div><div class="success-subtitle"></div><div class="success-detail-pill doc-pill">&#128206; <span id="docSuccessName"></span></div><button class="success-close-btn doc-close" onclick="closeDocSuccess()">&#10003; Got it!</button></div></div>
<div class="success-modal-overlay" id="leaveSuccessModal" style="display:none"><div class="success-modal leave-success"><div class="confetti-container" id="leaveConfetti"></div><div class="success-icon-wrap leave-icon">&#128203;<div class="check-badge">&#10003;</div></div><div class="success-title"></div><div class="success-subtitle"></div><div class="success-detail-pill leave-pill">&#127958;&#65039; <span id="leaveSuccessInfo"></span></div><button class="success-close-btn leave-close" onclick="closeLeaveSuccess()">&#10003; Got it!</button></div></div>
<div class="modal-overlay" id="uploadModal"></div>
<div class="modal-overlay" id="leaveModal"></div>
<div id="calGrid" style="display:none"></div><span id="calMonthLabel" style="display:none"></span>
<div id="selectedDatesDisplay" style="display:none"></div>
<input id="absentDatesInput" type="hidden"><input id="daysAbsentInput" type="hidden">
<input id="daysAbsentReadonly" type="hidden"><span id="daysAbsentDisplay" style="display:none"></span>

<script src="teacher_js.js"></script>
<script>
document.getElementById('confirmPw').addEventListener('input', function() {
  const match = this.value === document.getElementById('newPw').value;
  document.getElementById('pwMismatch').style.display = (this.value && !match) ? 'block' : 'none';
});
document.getElementById('pwForm').addEventListener('submit', function(e) {
  if (document.getElementById('newPw').value !== document.getElementById('confirmPw').value) {
    e.preventDefault(); document.getElementById('pwMismatch').style.display = 'block';
  }
});
</script>
</body>
</html>