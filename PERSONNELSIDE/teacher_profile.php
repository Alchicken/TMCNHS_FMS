<?php
// teacher_profile.php — MY PROFILE
require_once 'teacher_shared.php';

$msg = $err = '';

// ── ID type catalogue ─────────────────────────────────────────────────────────
$ID_TYPES = [
    'prc' => [
        'label'  => 'PRC License',
        'icon'   => '🎓',
        'color'  => '#6b2fa0',
        'fields' => ['id_number','profession','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'e.g. 0012345','profession'=>'e.g. Professional Teacher'],
        'hint'   => 'Required for teachers, nurses, engineers, architects, and other licensed professionals.',
    ],
    'drivers_license' => [
        'label'  => "Driver's License",
        'icon'   => '🚗',
        'color'  => '#0369a1',
        'fields' => ['id_number','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'LTO License Number'],
        'hint'   => "Land Transportation Office (LTO) issued driver's license.",
    ],
    'civil_service' => [
        'label'  => 'Civil Service Eligibility',
        'icon'   => '📋',
        'color'  => '#0e7490',
        'fields' => ['id_number','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'CS Eligibility / ID Number'],
        'hint'   => 'CSC eligibility card or certificate of eligibility.',
    ],
    'security_license' => [
        'label'  => 'Security Guard License',
        'icon'   => '🛡️',
        'color'  => '#b45309',
        'fields' => ['id_number','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'SOSIA / PNP License Number'],
        'hint'   => 'SOSIA or PNP-issued security guard license.',
    ],
    'philsys' => [
        'label'  => 'PhilSys / National ID',
        'icon'   => '🪪',
        'color'  => '#15803d',
        'fields' => ['id_number','issued_date','id_image'],
        'hints'  => ['id_number'=>'PhilSys Reference Number (PRN)'],
        'hint'   => 'Philippine Identification System national ID card.',
    ],
    'postal' => [
        'label'  => 'Postal ID',
        'icon'   => '📮',
        'color'  => '#0f766e',
        'fields' => ['id_number','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'Postal ID Number'],
        'hint'   => 'Philippine Postal Corporation issued ID.',
    ],
    'umid' => [
        'label'  => 'UMID Card',
        'icon'   => '💳',
        'color'  => '#7c3aed',
        'fields' => ['id_number','issued_date','id_image'],
        'hints'  => ['id_number'=>'UMID Card Number'],
        'hint'   => 'Unified Multi-Purpose Identification (SSS / GSIS / PhilHealth / Pag-IBIG).',
    ],
    'passport' => [
        'label'  => 'Passport',
        'icon'   => '📗',
        'color'  => '#065f46',
        'fields' => ['id_number','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'Passport Number'],
        'hint'   => 'Philippine passport issued by DFA.',
    ],
    'other' => [
        'label'  => 'Other ID',
        'icon'   => '🪪',
        'color'  => '#475569',
        'fields' => ['id_number','profession','issued_date','expiry_date','id_image'],
        'hints'  => ['id_number'=>'ID Number','profession'=>'ID Title / Description'],
        'hint'   => 'Any other valid government or professional ID not listed above.',
    ],
];

// ── Ensure all columns exist (legacy + new table) ─────────────────────────────
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS prc_license_number varchar(20) DEFAULT NULL AFTER bp_number");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS prc_license_profession varchar(150) DEFAULT NULL AFTER prc_license_number");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS prc_registration_date date DEFAULT NULL AFTER prc_license_profession");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS prc_expiry_date date DEFAULT NULL AFTER prc_registration_date");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS prc_license_image varchar(255) DEFAULT NULL AFTER prc_expiry_date");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS other_id_number varchar(100) DEFAULT NULL");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS other_id_label varchar(100) DEFAULT NULL");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS other_id_expiry date DEFAULT NULL");
$conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS other_id_image varchar(255) DEFAULT NULL");

// ── Universal ID table ────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS personnel_ids (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id  INT NOT NULL,
    id_type       VARCHAR(60)  NOT NULL,
    id_label      VARCHAR(120) NOT NULL,
    id_number     VARCHAR(120) DEFAULT NULL,
    profession    VARCHAR(150) DEFAULT NULL,
    issued_date   DATE         DEFAULT NULL,
    expiry_date   DATE         DEFAULT NULL,
    id_image      VARCHAR(255) DEFAULT NULL,
    is_primary    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pid  (personnel_id),
    INDEX idx_type (id_type),
    FOREIGN KEY (personnel_id) REFERENCES personnels(id) ON DELETE CASCADE
)");

// ── NOTIFICATION TABLE ────────────────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    type        ENUM('id_updated','expiring_soon','expired') NOT NULL,
    id_kind     ENUM('prc','other') NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_personnel (personnel_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
)");

/**
 * Push a notification to admin (deduped per 24 h).
 */
function notify_admin(mysqli $db, int $pid, string $type, string $id_kind, string $message): void {
    $dedup = $db->prepare(
        "SELECT id FROM admin_notifications
         WHERE personnel_id=? AND type=? AND id_kind=? AND created_at >= NOW() - INTERVAL 24 HOUR
         LIMIT 1"
    );
    $dedup->bind_param('iss', $pid, $type, $id_kind);
    $dedup->execute();
    if ($dedup->get_result()->num_rows > 0) return;
    $ins = $db->prepare("INSERT INTO admin_notifications (personnel_id,type,id_kind,message) VALUES (?,?,?,?)");
    $ins->bind_param('isss', $pid, $type, $id_kind, $message);
    $ins->execute();
}

// ── Load existing IDs ─────────────────────────────────────────────────────────
function load_ids(mysqli $conn, int $teacher_id): array {
    $res = $conn->prepare("SELECT * FROM personnel_ids WHERE personnel_id=? ORDER BY is_primary DESC, created_at ASC");
    $res->bind_param('i', $teacher_id);
    $res->execute();
    $rows = $res->get_result();
    $out = [];
    while ($r = $rows->fetch_assoc()) $out[] = $r;
    return $out;
}
$existing_ids = load_ids($conn, $teacher_id);

// ─────────────────────────────────────────────────────────────────────────────
// POST HANDLERS
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── UPDATE PROFILE ────────────────────────────────────────────────────────
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
                $stmt->bind_param('i', $user_id); $stmt->execute();
                $teacher = $stmt->get_result()->fetch_assoc();
            } else {
                $err = 'Update failed: ' . $s->error;
            }
        }
    }

    // ── SAVE / UPDATE ID ──────────────────────────────────────────────────────
    if ($_POST['action'] === 'save_id') {
        $id_type    = trim($_POST['id_type']     ?? '');
        $id_number  = trim($_POST['id_number']   ?? '');
        $profession = trim($_POST['profession']  ?? '');
        $issued     = trim($_POST['issued_date'] ?? '') ?: null;
        $expiry     = trim($_POST['expiry_date'] ?? '') ?: null;
        $record_id  = intval($_POST['record_id'] ?? 0);

        if (!$id_type || !isset($ID_TYPES[$id_type])) {
            $err = 'Please select a valid ID type.';
        } elseif (!$id_number) {
            $err = 'ID number is required.';
        } else {
            // Handle image
            $img_path = null;
            if ($record_id) {
                $tmp = $conn->prepare("SELECT id_image FROM personnel_ids WHERE id=? AND personnel_id=?");
                $tmp->bind_param('ii', $record_id, $teacher_id); $tmp->execute();
                $tmprow   = $tmp->get_result()->fetch_assoc();
                $img_path = $tmprow['id_image'] ?? null;
            }
            if (!empty($_FILES['id_image']['tmp_name'])) {
                $uploadDir = '../uploads/personnel_ids/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext         = strtolower(pathinfo($_FILES['id_image']['name'], PATHINFO_EXTENSION));
                $allowed     = ['jpg','jpeg','png','webp'];
                $mime        = mime_content_type($_FILES['id_image']['tmp_name']);
                $allowedMime = ['image/jpeg','image/jpg','image/png','image/webp'];
                if (in_array($ext,$allowed) && in_array($mime,$allowedMime) && $_FILES['id_image']['size']<=5*1024*1024) {
                    $fname = 'id_'.$teacher_id.'_'.time().'.'.$ext;
                    if (move_uploaded_file($_FILES['id_image']['tmp_name'], $uploadDir.$fname)) {
                        if ($img_path && file_exists('../'.ltrim($img_path,'../'))) @unlink('../'.ltrim($img_path,'../'));
                        $img_path = 'uploads/personnel_ids/'.$fname;
                    }
                } else { $err = 'Invalid file. Use JPG, PNG or WEBP under 5 MB.'; }
            }

            if (!$err) {
                $type_label = $ID_TYPES[$id_type]['label'];
                if ($record_id) {
                    $su = $conn->prepare("UPDATE personnel_ids SET id_type=?,id_label=?,id_number=?,profession=?,issued_date=?,expiry_date=?,id_image=?,updated_at=NOW() WHERE id=? AND personnel_id=?");
                    $su->bind_param('sssssssii', $id_type,$type_label,$id_number,$profession,$issued,$expiry,$img_path,$record_id,$teacher_id);
                    $su->execute();
                } else {
                    $si = $conn->prepare("INSERT INTO personnel_ids (personnel_id,id_type,id_label,id_number,profession,issued_date,expiry_date,id_image) VALUES (?,?,?,?,?,?,?,?)");
                    $si->bind_param('isssssss', $teacher_id,$id_type,$type_label,$id_number,$profession,$issued,$expiry,$img_path);
                    $si->execute();
                }
                $msg = $ID_TYPES[$id_type]['label'].' saved successfully!';

                // Notify admin
                $pname = $teacher['full_name'] ?? 'A personnel';
                notify_admin($conn, $teacher_id, 'id_updated', 'other',
                    "{$pname} updated their {$type_label} (#{$id_number}).");
                if ($expiry) {
                    $exp = new DateTime($expiry); $now_dt = new DateTime();
                    if ($exp < $now_dt) {
                        notify_admin($conn, $teacher_id, 'expired', 'other',
                            "{$pname}'s {$type_label} expired on {$expiry}.");
                    } elseif ($now_dt->diff($exp)->days <= 90) {
                        $d = $now_dt->diff($exp)->days;
                        notify_admin($conn, $teacher_id, 'expiring_soon', 'other',
                            "{$pname}'s {$type_label} is expiring in {$d} day(s) on {$expiry}.");
                    }
                }
                $existing_ids = load_ids($conn, $teacher_id);
            }
        }
    }

    // ── DELETE ID ─────────────────────────────────────────────────────────────
    if ($_POST['action'] === 'delete_id') {
        $del_id = intval($_POST['record_id'] ?? 0);
        if ($del_id) {
            $dg = $conn->prepare("SELECT id_image FROM personnel_ids WHERE id=? AND personnel_id=?");
            $dg->bind_param('ii',$del_id,$teacher_id); $dg->execute();
            $drow = $dg->get_result()->fetch_assoc();
            if (!empty($drow['id_image']) && file_exists('../'.ltrim($drow['id_image'],'../')))
                @unlink('../'.ltrim($drow['id_image'],'../'));
            $dd = $conn->prepare("DELETE FROM personnel_ids WHERE id=? AND personnel_id=?");
            $dd->bind_param('ii',$del_id,$teacher_id); $dd->execute();
            $msg = 'ID record deleted.';
            $existing_ids = load_ids($conn, $teacher_id);
        }
    }

    // ── SET PRIMARY ───────────────────────────────────────────────────────────
    if ($_POST['action'] === 'set_primary_id') {
        $pri_id = intval($_POST['record_id'] ?? 0);
        if ($pri_id) {
            $sp2 = $conn->prepare("UPDATE personnel_ids SET is_primary=0 WHERE personnel_id=?");
            $sp2->bind_param('i',$teacher_id); $sp2->execute();
            $sp3 = $conn->prepare("UPDATE personnel_ids SET is_primary=1 WHERE id=? AND personnel_id=?");
            $sp3->bind_param('ii',$pri_id,$teacher_id); $sp3->execute();
            $msg = 'Primary ID updated.';
            $existing_ids = load_ids($conn, $teacher_id);
        }
    }

    // ── UPLOAD PROFILE PICTURE ────────────────────────────────────────────────
    if ($_POST['action'] === 'upload_avatar') {
        if (!empty($_FILES['profile_image']['tmp_name'])) {
            $uploadDir = '../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext     = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','webp','gif'];
            $mime    = mime_content_type($_FILES['profile_image']['tmp_name']);
            $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
            if (in_array($ext,$allowed) && in_array($mime,$allowedMime) && $_FILES['profile_image']['size']<=3*1024*1024) {
                $filename = 'avatar_'.$teacher_id.'_'.time().'.'.$ext;
                $dest     = $uploadDir.$filename;
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $dest)) {
                    if (!empty($teacher['profile_image']) && file_exists('../'.ltrim($teacher['profile_image'],'../')))
                        @unlink('../'.ltrim($teacher['profile_image'],'../'));
                    $newPath = 'uploads/avatars/'.$filename;
                    $sa = $conn->prepare("UPDATE personnels SET profile_image=? WHERE id=?");
                    $sa->bind_param('si', $newPath, $teacher_id);
                    if ($sa->execute()) {
                        $msg = 'Profile picture updated!';
                        $stmt = $conn->prepare("SELECT t.*, u.username FROM personnels t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
                        $stmt->bind_param('i',$user_id); $stmt->execute();
                        $teacher = $stmt->get_result()->fetch_assoc();
                    } else { $err = 'Failed to save avatar: '.$sa->error; }
                } else { $err = 'Failed to move uploaded file.'; }
            } else { $err = 'Invalid file. Use JPG, PNG, WEBP or GIF under 3 MB.'; }
        } else { $err = 'No image selected.'; }
    }

    // ── REMOVE PROFILE PICTURE ────────────────────────────────────────────────
    if ($_POST['action'] === 'remove_avatar') {
        if (!empty($teacher['profile_image']) && file_exists('../'.ltrim($teacher['profile_image'],'../')))
            @unlink('../'.ltrim($teacher['profile_image'],'../'));
        $sr = $conn->prepare("UPDATE personnels SET profile_image=NULL WHERE id=?");
        $sr->bind_param('i', $teacher_id);
        if ($sr->execute()) {
            $msg = 'Profile picture removed.';
            $stmt = $conn->prepare("SELECT t.*, u.username FROM personnels t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
            $stmt->bind_param('i',$user_id); $stmt->execute();
            $teacher = $stmt->get_result()->fetch_assoc();
        } else { $err = 'Failed to remove avatar.'; }
    }

    // ── CHANGE PASSWORD ───────────────────────────────────────────────────────
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
            $stmt_pw->bind_param('i',$user_id); $stmt_pw->execute();
            $row = $stmt_pw->get_result()->fetch_assoc();
            if ($row && password_verify($current_pw, $row['password'])) {
                $hash = password_hash($new_pw, PASSWORD_BCRYPT);
                $stmt_upw = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt_upw->bind_param('si',$hash,$user_id); $stmt_upw->execute();
                $msg = 'Password changed successfully!';
            } else { $err = 'Current password is incorrect.'; }
        }
    }
}

// ── Passive expiry check for all IDs ─────────────────────────────────────────
$now_dt = new DateTime();
foreach ($existing_ids as $eid) {
    if (!empty($eid['expiry_date'])) {
        $eexp   = new DateTime($eid['expiry_date']);
        $elabel = $eid['id_label'];
        $epname = $teacher['full_name'] ?? 'A personnel';
        if ($eexp < $now_dt) {
            notify_admin($conn, $teacher_id, 'expired', 'other',
                "{$epname}'s {$elabel} expired on {$eid['expiry_date']}.");
        } elseif ($now_dt->diff($eexp)->days <= 90) {
            $ed = $now_dt->diff($eexp)->days;
            notify_admin($conn, $teacher_id, 'expiring_soon', 'other',
                "{$epname}'s {$elabel} is expiring in {$ed} day(s) on {$eid['expiry_date']}.");
        }
    }
}

// Build JS-friendly JSON for ID types
$idTypesJson = json_encode(array_values(array_map(
    function($k,$v){ return array_merge($v,['key'=>$k]); },
    array_keys($ID_TYPES), $ID_TYPES
)));
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

/* ── SHARED ── */
.section-divider {
  font-size: 11px; font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase; letter-spacing: 2px; color: var(--muted);
  margin: 28px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
.info-item { padding: 14px 0; border-bottom: 1px solid var(--border); }
.info-item:nth-child(odd)  { padding-right: 24px; border-right: 1px solid var(--border); }
.info-item:nth-child(even) { padding-left: 24px; }
.info-item label { font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); display: block; margin-bottom: 4px; }
.info-item span  { font-size: 14px; font-weight: 500; }
.info-item.full-row { grid-column: 1/-1; padding-right: 0; border-right: none; padding-left: 0; }
.subsection {
  font-size: 10px; font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase; letter-spacing: 1.5px; color: var(--accent);
  margin: 20px 0 12px; padding-bottom: 6px; border-bottom: 1px dashed var(--border);
}
.pw-form { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-top: 4px; }

/* ── EXPIRY BADGES ── */
.id-expiry-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px;
  font-family: 'JetBrains Mono', monospace; font-weight: 600;
}
.id-expiry-badge.expired  { background: rgba(200,16,46,0.10); color: var(--danger); }
.id-expiry-badge.expiring { background: rgba(217,119,6,0.10);  color: #d97706; }
.id-expiry-badge.valid    { background: rgba(5,150,105,0.10);  color: var(--success); }

/* ── PROFILE PICTURE UPLOAD ── */
.avatar-upload-wrap {
  display: flex; align-items: center; gap: 24px;
  background: var(--card); border: 1px solid var(--border);
  border-radius: 16px; padding: 22px 26px; margin-bottom: 4px;
}
.avatar-preview {
  width: 90px; height: 90px; border-radius: 50%; flex-shrink: 0;
  position: relative; cursor: pointer;
}
.avatar-preview img {
  width: 90px; height: 90px; border-radius: 50%;
  object-fit: cover; border: 3px solid var(--border);
  display: block; transition: filter 0.2s;
}
.avatar-initials {
  width: 90px; height: 90px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), #e85370);
  display: flex; align-items: center; justify-content: center;
  font-size: 32px; font-weight: 700; color: #fff;
  border: 3px solid var(--border); user-select: none;
}
.avatar-overlay {
  position: absolute; inset: 0; border-radius: 50%;
  background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity 0.2s; pointer-events: none; font-size: 22px;
}
.avatar-preview:hover .avatar-overlay { opacity: 1; }
.avatar-preview:hover img,
.avatar-preview:hover .avatar-initials { filter: brightness(0.65); }
.avatar-info { flex: 1; }
.avatar-info h4 { font-size: 14px; font-weight: 700; margin: 0 0 4px; }
.avatar-info p  { font-size: 12px; color: var(--muted); margin: 0 0 14px; line-height: 1.5; }
.avatar-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.avatar-btn {
  padding: 7px 16px; border-radius: 8px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: none; transition: all 0.18s; font-family: 'DM Sans', sans-serif;
  display: inline-flex; align-items: center; gap: 6px;
}
.avatar-btn.upload { background: var(--accent); color: #fff; }
.avatar-btn.upload:hover { background: #a80f28; }
.avatar-btn.remove { background: var(--surface); color: var(--muted); border: 1px solid var(--border); }
.avatar-btn.remove:hover { color: var(--danger); border-color: var(--danger); background: rgba(200,16,46,0.06); }

/* ── NAV ── */
.nav-item { display: flex; align-items: center; gap: 10px; position: relative; }
.nav-badge {
  position: static; margin-left: auto; min-width: 20px; height: 20px;
  border-radius: 50%; background: #ff3b3b; color: #fff;
  font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}

/* ── SIDEBAR ── */
.sidebar { display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
.nav { flex: 1; overflow-y: auto; min-height: 0; }
.sidebar-footer {
  padding: 12px 12px 16px !important; border-top: 1px solid rgba(255,255,255,0.15);
  display: flex !important; flex-direction: column !important; gap: 8px !important; flex-shrink: 0;
}

/* ══════════════════════════════════════════════════════
   ID MANAGER
══════════════════════════════════════════════════════ */
.id-manager-wrap {
  background: var(--card); border: 1px solid var(--border);
  border-radius: 16px; overflow: hidden; margin-top: 4px;
}

/* existing cards list */
.id-cards-list { padding: 20px 24px 4px; display: flex; flex-direction: column; gap: 12px; }
.id-card-row {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; border-radius: 12px; border: 1px solid var(--border);
  background: var(--surface); transition: border-color 0.18s; position: relative;
}
.id-card-row:hover { border-color: var(--accent); }
.id-card-row.is-primary { border-color: #d4a847; background: rgba(212,168,71,0.05); }
.id-card-badge-primary {
  position: absolute; top: -8px; left: 14px;
  background: linear-gradient(90deg,#b8860b,#d4a847); color:#fff;
  font-size: 9px; font-family: 'JetBrains Mono',monospace; font-weight: 700;
  letter-spacing: 1.5px; text-transform: uppercase; padding: 2px 8px; border-radius: 20px;
}
.id-card-type-icon {
  width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 20px;
  background: var(--card); border: 1.5px solid var(--border);
}
.id-card-thumb {
  width: 72px; height: 46px; border-radius: 8px; overflow: hidden; flex-shrink: 0;
  border: 1.5px solid var(--border); background: #111;
  display: flex; align-items: center; justify-content: center;
}
.id-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
.id-card-meta { flex: 1; min-width: 0; }
.id-card-meta .id-type-name { font-size: 10px; font-family: 'JetBrains Mono',monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 3px; }
.id-card-meta .id-number-val { font-size: 15px; font-weight: 700; font-family: 'JetBrains Mono',monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.id-card-meta .id-sub { font-size: 11px; color: var(--muted); margin-top: 2px; }
.id-card-meta .id-dates { font-size: 11px; color: var(--muted); margin-top: 3px; display: flex; gap: 12px; flex-wrap: wrap; }
.id-card-meta .exp-warn { color: #d97706; font-weight: 600; }
.id-card-meta .exp-bad  { color: var(--danger); font-weight: 600; }
.id-card-actions { display: flex; gap: 6px; flex-shrink: 0; }
.id-act-btn {
  padding: 5px 11px; border-radius: 7px; font-size: 11px; font-weight: 600;
  cursor: pointer; border: 1px solid var(--border); background: var(--card);
  color: var(--text); transition: all 0.15s; font-family: 'DM Sans',sans-serif;
  display: inline-flex; align-items: center; gap: 4px;
}
.id-act-btn:hover { border-color: var(--accent); color: var(--accent); }
.id-act-btn.danger:hover { border-color: var(--danger); color: var(--danger); background: rgba(200,16,46,0.05); }
.id-act-btn.star { color: #b8860b; border-color: #d4a847; }

/* empty state */
.id-empty {
  padding: 32px 24px; text-align: center;
  font-size: 13px; color: var(--muted); font-family: 'JetBrains Mono',monospace;
}
.id-empty span { font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.4; }

/* add trigger */
.id-add-trigger {
  display: flex; align-items: center; justify-content: center; gap: 9px;
  margin: 16px 24px 24px; padding: 13px; border-radius: 11px;
  border: 2px dashed var(--border); cursor: pointer; color: var(--muted);
  font-size: 13px; font-weight: 600; font-family: 'DM Sans',sans-serif;
  transition: all 0.2s; background: transparent; width: calc(100% - 48px);
}
.id-add-trigger:hover { border-color: var(--accent); color: var(--accent); background: rgba(200,16,46,0.04); }

/* form panel */
.id-form-panel {
  border-top: 1px solid var(--border); padding: 28px 24px;
  background: var(--surface); display: none;
}
.id-form-panel.open { display: block; }
.id-form-panel h3 {
  font-size: 13px; font-family: 'JetBrains Mono',monospace; text-transform: uppercase;
  letter-spacing: 1.5px; color: var(--accent); margin: 0 0 18px;
  padding-bottom: 8px; border-bottom: 1px dashed var(--border);
}

/* type selector */
.id-type-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
  gap: 8px; margin-bottom: 20px;
}
.id-type-option {
  border: 2px solid var(--border); border-radius: 10px; padding: 10px 12px;
  cursor: pointer; transition: all 0.18s; background: var(--card);
  display: flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 600; font-family: 'DM Sans',sans-serif; color: var(--text);
}
.id-type-option:hover { border-color: var(--accent); background: rgba(200,16,46,0.04); }
.id-type-option.selected { border-color: var(--accent); background: rgba(200,16,46,0.07); color: var(--accent); }
.id-type-option .opt-icon { font-size: 18px; flex-shrink: 0; }

/* dynamic fields */
.id-fields-wrap { display: none; }
.id-fields-wrap.show { display: block; }
.id-type-hint {
  padding: 10px 14px; border-radius: 9px; font-size: 11px;
  font-family: 'JetBrains Mono',monospace; line-height: 1.7; margin-bottom: 18px;
  background: rgba(200,16,46,0.05); border: 1px solid rgba(200,16,46,0.15); color: var(--muted);
}
.id-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.id-fields-grid .full { grid-column: 1/-1; }

/* upload zone */
.upload-zone {
  border: 2px dashed var(--border); border-radius: 14px; padding: 24px 16px;
  text-align: center; cursor: pointer; transition: border-color 0.2s, background 0.2s;
  background: var(--surface); position: relative; overflow: hidden;
  min-height: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.upload-zone:hover, .upload-zone.drag-over { border-color: var(--accent); background: rgba(200,16,46,0.04); }
.upload-zone input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
.upload-icon  { font-size: 28px; margin-bottom: 7px; display: block; opacity: 0.6; }
.upload-title { font-size: 13px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
.upload-hint  { font-size: 11px; color: var(--muted); line-height: 1.6; }

/* preview */
.preview-wrap {
  position: relative; border-radius: 12px; overflow: hidden;
  border: 2px solid var(--border); background: #111;
  min-height: 120px; max-height: 260px;
  display: flex; align-items: center; justify-content: center;
}
.preview-wrap img { width: 100%; max-height: 260px; object-fit: contain; display: block; }
.preview-actions { position: absolute; top: 10px; right: 10px; display: flex; gap: 6px; }
.preview-btn {
  padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 600;
  cursor: pointer; border: none; backdrop-filter: blur(8px); transition: all 0.18s;
}
.preview-btn.change { background: rgba(255,255,255,0.88); color: #111; }
.preview-btn.change:hover { background: #fff; }
.preview-btn.remove { background: rgba(200,16,46,0.82); color: #fff; }
.preview-btn.remove:hover { background: var(--accent); }

/* id-input */
.id-input {
  width: 100%; padding: 10px 13px; background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 9px; font-size: 13px; outline: none; color: var(--text);
  transition: border-color 0.2s, box-shadow 0.2s; font-family: inherit; box-sizing: border-box;
}
.id-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,16,46,0.10); }
.id-input.mono { font-family: 'JetBrains Mono',monospace; font-size: 14px; }
.id-label {
  font-size: 10px; font-family: 'JetBrains Mono',monospace; text-transform: uppercase;
  letter-spacing: 1px; color: var(--muted); display: block; margin-bottom: 6px;
}
.id-hint-box {
  border-radius: 9px; padding: 10px 13px; font-size: 11px; color: var(--muted);
  font-family: 'JetBrains Mono',monospace; line-height: 1.7; margin-top: 10px;
  background: rgba(200,16,46,0.04); border: 1px solid rgba(200,16,46,0.12);
}

/* submit row */
.id-submit-row { display: flex; gap: 10px; margin-top: 20px; align-items: center; }
.id-submit-btn {
  flex: 1; padding: 12px 20px; border: none; border-radius: 10px;
  cursor: pointer; color: #fff; background: var(--accent);
  font-family: 'DM Sans',sans-serif; font-size: 14px; font-weight: 700;
  transition: transform 0.18s, box-shadow 0.18s;
}
.id-submit-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(200,16,46,0.28); }
.id-cancel-btn {
  padding: 12px 18px; border-radius: 10px; border: 1px solid var(--border);
  background: var(--card); color: var(--muted); cursor: pointer;
  font-family: 'DM Sans',sans-serif; font-size: 13px; font-weight: 600; transition: all 0.15s;
}
.id-cancel-btn:hover { border-color: var(--danger); color: var(--danger); }
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
    <a class="nav-item" href="teacher_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item" href="teacher_documents.php"><span class="ni">📁</span> My Documents</a>
    <a class="nav-item" href="teacher_service_credit.php"><span class="ni">📋</span> Service Credit</a>
    <a class="nav-item active" href="teacher_profile.php"><span class="ni">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <?php if (!empty($teacher['profile_image'])): ?>
        <div class="avatar" style="padding:0;overflow:hidden;background:none;">
          <img src="../<?= htmlspecialchars($teacher['profile_image']) ?>?v=<?= time() ?>"
               alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        </div>
      <?php else: ?>
        <div class="avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
      <?php endif; ?>
      <div class="user-info">
        <strong><?= htmlspecialchars($teacher['full_name']) ?></strong>
        <small>Teacher</small>
      </div>
      <a href="../logout.php" class="logout-btn" title="Logout" style="font-size:1.5rem;">&#9167;</a>
    </div>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>My Profile</h1><p>View and edit your personal information</p></div>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">&#9888;&#65039; <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- ── PROFILE HEADER ── -->
    <div class="profile-hero">
      <?php if (!empty($teacher['profile_image'])): ?>
        <div class="prof-avatar" style="padding:0;overflow:hidden;background:none;">
          <img src="../<?= htmlspecialchars($teacher['profile_image']) ?>?v=<?= time() ?>"
               alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        </div>
      <?php else: ?>
        <div class="prof-avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
      <?php endif; ?>
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

    <!-- ── PROFILE PICTURE ── -->
    <div class="section-divider">&#128247; Profile Picture</div>
    <div class="avatar-upload-wrap">
      <div class="avatar-preview" onclick="document.getElementById('avatarFileInput').click()" title="Click to change photo">
        <?php if (!empty($teacher['profile_image'])): ?>
          <img id="avatarImg" src="../<?= htmlspecialchars($teacher['profile_image']) ?>?v=<?= time() ?>" alt="Profile Photo">
        <?php else: ?>
          <div class="avatar-initials" id="avatarInitials"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="avatar-overlay">&#128247;</div>
      </div>
      <div class="avatar-info">
        <h4>Profile Photo</h4>
        <p>Upload a clear photo of yourself. JPG, PNG, WEBP or GIF &middot; Max 3 MB.<br>Click the photo or button below to choose a file.</p>
        <div class="avatar-actions">
          <label class="avatar-btn upload" for="avatarFileInput">&#128247; Change Photo</label>
          <?php if (!empty($teacher['profile_image'])): ?>
          <form method="POST" style="margin:0" onsubmit="return confirm('Remove your profile picture?')">
            <input type="hidden" name="action" value="remove_avatar">
            <button type="submit" class="avatar-btn remove">&#10005; Remove</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display:none">
      <input type="hidden" name="action" value="upload_avatar">
      <input type="file" id="avatarFileInput" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif">
    </form>

    <!-- ── CURRENT INFO READ-ONLY ── -->
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

    <!-- ── EDIT FORM ── -->
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
              <?php foreach ([''=>'— None —','Jr.'=>'Jr.','Sr.'=>'Sr.','II'=>'II','III'=>'III','IV'=>'IV'] as $val=>$lbl): ?>
                <option value="<?= $val ?>" <?= ($teacher['suffix']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Gender</label>
            <select name="gender"><option value="">— Select —</option>
              <?php foreach (['Male','Female','Other'] as $g): ?>
                <option value="<?= $g ?>" <?= ($teacher['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label>Civil Status</label>
            <select name="civil_status"><option value="">— Select —</option>
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

    <!-- ══════════════════════════════════════════════════
         PROFESSIONAL & GOVERNMENT IDs
    ══════════════════════════════════════════════════ -->
    <div class="section-divider" style="margin-top:32px">
      🪪 Professional &amp; Government IDs
      <?php
        $bad = 0; $warn = 0;
        foreach ($existing_ids as $eid) {
            if (!empty($eid['expiry_date'])) {
                $eexp = new DateTime($eid['expiry_date']);
                if ($eexp < $now_dt) $bad++;
                elseif ($now_dt->diff($eexp)->days <= 90) $warn++;
            }
        }
        if ($bad)  echo '<span class="id-expiry-badge expired">⛔ '.$bad.' Expired</span>';
        if ($warn) echo '<span class="id-expiry-badge expiring">⚠️ '.$warn.' Expiring Soon</span>';
        if (!$bad && !$warn && count($existing_ids)) echo '<span class="id-expiry-badge valid">✅ All Valid</span>';
      ?>
    </div>

    <div class="id-manager-wrap">

      <!-- Existing ID cards -->
      <div class="id-cards-list">
        <?php if (empty($existing_ids)): ?>
          <div class="id-empty">
            <span>🪪</span>No IDs added yet. Click below to add your first ID.
          </div>
        <?php else: ?>
          <?php foreach ($existing_ids as $eid):
            $einfo   = $ID_TYPES[$eid['id_type']] ?? $ID_TYPES['other'];
            $eexpiry = !empty($eid['expiry_date']) ? new DateTime($eid['expiry_date']) : null;
            $eIsExp  = $eexpiry && $eexpiry < $now_dt;
            $eIsWarn = $eexpiry && !$eIsExp && $now_dt->diff($eexpiry)->days <= 90;
            $eDays   = $eexpiry ? $now_dt->diff($eexpiry)->days : null;
          ?>
          <div class="id-card-row <?= $eid['is_primary'] ? 'is-primary' : '' ?>">
            <?php if ($eid['is_primary']): ?>
              <div class="id-card-badge-primary">★ Primary</div>
            <?php endif; ?>

            <?php if (!empty($eid['id_image'])): ?>
              <div class="id-card-thumb">
                <img src="../<?= htmlspecialchars($eid['id_image']) ?>" alt="ID Photo">
              </div>
            <?php else: ?>
              <div class="id-card-type-icon"><?= $einfo['icon'] ?></div>
            <?php endif; ?>

            <div class="id-card-meta">
              <div class="id-type-name"><?= htmlspecialchars($eid['id_label']) ?></div>
              <div class="id-number-val"><?= htmlspecialchars($eid['id_number'] ?: '—') ?></div>
              <?php if (!empty($eid['profession'])): ?>
                <div class="id-sub"><?= htmlspecialchars($eid['profession']) ?></div>
              <?php endif; ?>
              <div class="id-dates">
                <?php if (!empty($eid['issued_date'])): ?>
                  <span>Issued: <?= htmlspecialchars($eid['issued_date']) ?></span>
                <?php endif; ?>
                <?php if ($eexpiry): ?>
                  <?php if ($eIsExp): ?>
                    <span class="exp-bad">⛔ Expired <?= htmlspecialchars($eid['expiry_date']) ?></span>
                  <?php elseif ($eIsWarn): ?>
                    <span class="exp-warn">⚠️ Expires in <?= $eDays ?> day(s) (<?= htmlspecialchars($eid['expiry_date']) ?>)</span>
                  <?php else: ?>
                    <span>Expires: <?= htmlspecialchars($eid['expiry_date']) ?></span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="id-card-actions">
              <?php if (!$eid['is_primary']): ?>
              <form method="POST" style="margin:0">
                <input type="hidden" name="action" value="set_primary_id">
                <input type="hidden" name="record_id" value="<?= $eid['id'] ?>">
                <button type="submit" class="id-act-btn star" title="Set as primary">★ Primary</button>
              </form>
              <?php endif; ?>
              <button type="button" class="id-act-btn"
                onclick='openEditForm(<?= htmlspecialchars(json_encode($eid), ENT_QUOTES) ?>)'>
                ✏️ Edit
              </button>
              <form method="POST" style="margin:0"
                onsubmit="return confirm('Delete this ID record?')">
                <input type="hidden" name="action" value="delete_id">
                <input type="hidden" name="record_id" value="<?= $eid['id'] ?>">
                <button type="submit" class="id-act-btn danger">🗑️</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Add trigger -->
      <button type="button" class="id-add-trigger" id="idAddTrigger" onclick="openAddForm()">
        ＋ Add New ID
      </button>

      <!-- Add / Edit form panel -->
      <div class="id-form-panel" id="idFormPanel">
        <h3 id="idFormTitle">➕ Add New ID</h3>
        <form method="POST" enctype="multipart/form-data" id="idForm">
          <input type="hidden" name="action" value="save_id">
          <input type="hidden" name="record_id" id="idRecordId" value="0">
          <input type="hidden" name="id_type" id="idTypeInput" value="">

          <!-- Step 1: type selector -->
          <div id="idTypeStep">
            <div class="id-label" style="margin-bottom:10px">Select ID Type</div>
            <div class="id-type-grid">
              <?php foreach ($ID_TYPES as $key => $info): ?>
              <div class="id-type-option" data-key="<?= $key ?>" onclick="selectIdType('<?= $key ?>')">
                <span class="opt-icon"><?= $info['icon'] ?></span>
                <?= htmlspecialchars($info['label']) ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Step 2: dynamic fields -->
          <div class="id-fields-wrap" id="idFieldsWrap">
            <div class="id-type-hint" id="idTypeHint"></div>
            <div class="id-fields-grid">

              <!-- ID Number (always shown) -->
              <div class="form-group" id="fieldIdNumber">
                <label class="id-label" id="labelIdNumber">ID Number *</label>
                <input type="text" name="id_number" id="inputIdNumber"
                       class="id-input mono" placeholder="Enter ID number"
                       style="width:100%;box-sizing:border-box">
              </div>

              <!-- Profession / Description (optional) -->
              <div class="form-group" id="fieldProfession" style="display:none">
                <label class="id-label" id="labelProfession">Profession / Description</label>
                <input type="text" name="profession" id="inputProfession"
                       class="id-input" placeholder=""
                       style="width:100%;box-sizing:border-box">
              </div>

              <!-- Issued date (optional) -->
              <div class="form-group" id="fieldIssuedDate" style="display:none">
                <label class="id-label">Issued Date</label>
                <input type="date" name="issued_date" id="inputIssuedDate"
                       class="id-input" style="width:100%;box-sizing:border-box">
              </div>

              <!-- Expiry date (optional) -->
              <div class="form-group" id="fieldExpiryDate" style="display:none">
                <label class="id-label">Expiry Date</label>
                <input type="date" name="expiry_date" id="inputExpiryDate"
                       class="id-input" style="width:100%;box-sizing:border-box">
              </div>

              <!-- Image upload (always shown, full width) -->
              <div class="form-group full">
                <label class="id-label">ID Photo <span style="opacity:.5;font-size:10px">(optional)</span></label>
                <div id="idUploadZone" class="upload-zone">
                  <input type="file" name="id_image" id="idFileInput" accept="image/jpeg,image/png,image/webp">
                  <span class="upload-icon">📷</span>
                  <div class="upload-title">Upload ID Photo</div>
                  <div class="upload-hint">JPG, PNG or WEBP &middot; Max 5 MB &middot; Drag &amp; drop or click</div>
                </div>
                <div id="idPreviewWrap" class="preview-wrap" style="display:none">
                  <img id="idPreviewImg" src="" alt="Preview">
                  <div class="preview-actions">
                    <label class="preview-btn change" for="idFileInput">📷 Change</label>
                    <button type="button" class="preview-btn remove" onclick="removeIdPreview()">✕ Remove</button>
                  </div>
                </div>
                <div class="id-hint-box">🔒 Stored securely — visible only to you and administrators.</div>
              </div>
            </div>

            <div class="id-submit-row">
              <button type="submit" class="id-submit-btn">💾 Save ID</button>
              <button type="button" class="id-cancel-btn" onclick="closeIdForm()">Cancel</button>
            </div>
          </div>
        </form>
      </div>
    </div><!-- end id-manager-wrap -->

    <!-- ── CHANGE PASSWORD ── -->
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

<!-- shared modals (kept for compatibility) -->
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
// ── Password mismatch ─────────────────────────────────────────────
document.getElementById('confirmPw').addEventListener('input', function () {
  const match = this.value === document.getElementById('newPw').value;
  document.getElementById('pwMismatch').style.display = (this.value && !match) ? 'block' : 'none';
});
document.getElementById('pwForm').addEventListener('submit', function (e) {
  if (document.getElementById('newPw').value !== document.getElementById('confirmPw').value) {
    e.preventDefault(); document.getElementById('pwMismatch').style.display = 'block';
  }
});

// ── Avatar upload: live preview then auto-submit ──────────────────
document.getElementById('avatarFileInput').addEventListener('change', function () {
  if (!this.files || !this.files[0]) return;
  const file = this.files[0];
  if (file.size > 3 * 1024 * 1024) { alert('File too large. Maximum size is 3 MB.'); this.value = ''; return; }
  const reader = new FileReader();
  reader.onload = e => {
    const wrap    = document.querySelector('.avatar-preview');
    const overlay = wrap.querySelector('.avatar-overlay');
    wrap.innerHTML = '';
    const img = document.createElement('img');
    img.id = 'avatarImg'; img.src = e.target.result; img.alt = 'Preview';
    wrap.appendChild(img); wrap.appendChild(overlay);
    document.getElementById('avatarForm').submit();
  };
  reader.readAsDataURL(file);
});

// ── ID Manager ───────────────────────────────────────────────────
const ID_TYPES = <?= $idTypesJson ?>;

function openAddForm() {
  document.getElementById('idFormTitle').textContent = '➕ Add New ID';
  document.getElementById('idRecordId').value = '0';
  document.getElementById('idTypeInput').value = '';
  document.getElementById('idForm').reset();
  removeIdPreview();
  document.querySelectorAll('.id-type-option').forEach(o => o.classList.remove('selected'));
  document.getElementById('idFieldsWrap').classList.remove('show');
  document.getElementById('idTypeStep').style.display = '';
  const panel = document.getElementById('idFormPanel');
  panel.classList.add('open');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  document.getElementById('idAddTrigger').style.display = 'none';
}

function openEditForm(rec) {
  document.getElementById('idFormTitle').textContent = '✏️ Edit ID';
  document.getElementById('idRecordId').value = rec.id;
  selectIdType(rec.id_type, false);
  document.getElementById('inputIdNumber').value   = rec.id_number   || '';
  document.getElementById('inputProfession').value = rec.profession  || '';
  document.getElementById('inputIssuedDate').value = rec.issued_date || '';
  document.getElementById('inputExpiryDate').value = rec.expiry_date || '';
  if (rec.id_image) {
    document.getElementById('idPreviewImg').src             = '../' + rec.id_image;
    document.getElementById('idPreviewWrap').style.display = 'flex';
    document.getElementById('idUploadZone').style.display  = 'none';
  } else {
    removeIdPreview();
  }
  const panel = document.getElementById('idFormPanel');
  panel.classList.add('open');
  panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  document.getElementById('idAddTrigger').style.display = 'none';
}

function selectIdType(key, scroll) {
  document.querySelectorAll('.id-type-option').forEach(o =>
    o.classList.toggle('selected', o.dataset.key === key)
  );
  document.getElementById('idTypeInput').value = key;

  const cfg = ID_TYPES.find(t => t.key === key);
  if (!cfg) return;

  document.getElementById('idTypeHint').textContent = cfg.hint || '';

  const has = f => cfg.fields.includes(f);
  document.getElementById('fieldProfession').style.display  = has('profession')  ? '' : 'none';
  document.getElementById('fieldIssuedDate').style.display  = has('issued_date') ? '' : 'none';
  document.getElementById('fieldExpiryDate').style.display  = has('expiry_date') ? '' : 'none';

  if (cfg.hints) {
    if (cfg.hints.id_number)  document.getElementById('inputIdNumber').placeholder  = cfg.hints.id_number;
    if (cfg.hints.profession) document.getElementById('inputProfession').placeholder = cfg.hints.profession;
  }

  document.getElementById('idFieldsWrap').classList.add('show');
  if (scroll !== false)
    document.getElementById('idFieldsWrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeIdForm() {
  document.getElementById('idFormPanel').classList.remove('open');
  document.getElementById('idAddTrigger').style.display = '';
}

// image preview
document.getElementById('idFileInput').addEventListener('change', function () {
  if (!this.files || !this.files[0]) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('idPreviewImg').src            = e.target.result;
    document.getElementById('idPreviewWrap').style.display = 'flex';
    document.getElementById('idUploadZone').style.display  = 'none';
  };
  reader.readAsDataURL(this.files[0]);
});

function removeIdPreview() {
  document.getElementById('idFileInput').value            = '';
  document.getElementById('idPreviewImg').src             = '';
  document.getElementById('idPreviewWrap').style.display  = 'none';
  document.getElementById('idUploadZone').style.display   = '';
}

// drag-drop
const uz = document.getElementById('idUploadZone');
uz.addEventListener('dragover',  e => { e.preventDefault(); uz.classList.add('drag-over'); });
uz.addEventListener('dragleave', ()  => uz.classList.remove('drag-over'));
uz.addEventListener('drop', e => {
  e.preventDefault(); uz.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) {
    const dt = new DataTransfer(); dt.items.add(file);
    document.getElementById('idFileInput').files = dt.files;
    const reader = new FileReader();
    reader.onload = ev => {
      document.getElementById('idPreviewImg').src            = ev.target.result;
      document.getElementById('idPreviewWrap').style.display = 'flex';
      uz.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }
});
</script>
</body>
</html>