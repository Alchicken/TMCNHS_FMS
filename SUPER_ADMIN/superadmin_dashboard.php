<?php
// SUPER_ADMIN/superadmin_dashboard.php
require_once '../Config.php';
require_once '../helpers/ActionRequest.php';

// Gate: only super_admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../index.php'); exit;
}

function notifyAdminOfReview(mysqli $conn, int $requestId): void {
    $conn->query("CREATE TABLE IF NOT EXISTS admin_request_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        admin_user_id INT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin (admin_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $conn->prepare("SELECT admin_user_id FROM action_requests WHERE id = ?");
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) return;

    $adminUserId = (int)$row['admin_user_id'];
    $ins = $conn->prepare("INSERT INTO admin_request_notifications (request_id, admin_user_id) VALUES (?, ?)");
    $ins->bind_param('ii', $requestId, $adminUserId);
    $ins->execute();
}

function paginationLinks(string $prefix, int $current, int $total, array $get): string {
    if ($total <= 1) return '';
    $out = '<div class="pg-wrap">';
    $base = array_filter($get, fn($k) => !str_starts_with($k, $prefix), ARRAY_FILTER_USE_KEY);
    $url  = fn($p) => '?' . http_build_query(array_merge($base, [
        "{$prefix}_page"  => $p,
        "{$prefix}_from"  => $get["{$prefix}_from"]  ?? '',
        "{$prefix}_to"    => $get["{$prefix}_to"]    ?? '',
        "{$prefix}_admin" => $get["{$prefix}_admin"] ?? '',
    ])) . '#';
    $out .= '<button class="pg-btn"' . ($current<=1?' disabled':'') . ' onclick="location.href=\'' . $url($current-1) . '\'">&lsaquo;</button>';
    for ($i = 1; $i <= $total; $i++) {
        if ($total > 7 && abs($i - $current) > 2 && $i !== 1 && $i !== $total) {
            if ($i === 2 || $i === $total - 1) $out .= '<button class="pg-btn" disabled>…</button>';
            continue;
        }
        $out .= '<button class="pg-btn' . ($i===$current?' pg-active':'') . '" onclick="location.href=\'' . $url($i) . '\'">' . $i . '</button>';
    }
    $out .= '<button class="pg-btn"' . ($current>=$total?' disabled':'') . ' onclick="location.href=\'' . $url($current+1) . '\'">&rsaquo;</button>';
    $out .= '</div>';
    return $out;
}

function dateFilterForm(string $prefix, array $get, string $anchor, mysqli $conn, bool $showAdminFilter = false, bool $isAuditLog = false): string {
    $from       = htmlspecialchars($get["{$prefix}_from"]  ?? '');
    $to         = htmlspecialchars($get["{$prefix}_to"]    ?? '');
    $adminFilter = $get["{$prefix}_admin"] ?? '';

    $hidden = '';
    foreach ($get as $k => $v) {
        if (!str_starts_with($k, $prefix) && $k !== 'action') {
            $hidden .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
        }
    }

    // Build admin dropdown
    $adminDropdown = '';
    if ($showAdminFilter) {
        if ($isAuditLog) {
            $adminRows = $conn->query("SELECT DISTINCT u.username FROM admin_audit_log al JOIN users u ON u.id = al.user_id ORDER BY u.username")->fetch_all(MYSQLI_ASSOC);
        } else {
            $adminRows = $conn->query("SELECT DISTINCT u.username FROM action_requests ar JOIN users u ON u.id = ar.admin_user_id ORDER BY u.username")->fetch_all(MYSQLI_ASSOC);
        }
        $adminDropdown = '<label>Admin <select name="' . $prefix . '_admin" class="df-select">';
        $adminDropdown .= '<option value="">— All Admins —</option>';
        foreach ($adminRows as $row) {
            $sel = ($adminFilter === $row['username']) ? ' selected' : '';
            $adminDropdown .= '<option value="' . htmlspecialchars($row['username']) . '"' . $sel . '>' . htmlspecialchars($row['username']) . '</option>';
        }
        $adminDropdown .= '</select></label>';
    }

    $hasFilter = $from || $to || $adminFilter;
    $clearParams = array_filter($get, fn($k) => !str_starts_with($k, $prefix), ARRAY_FILTER_USE_KEY);

    return '
    <form method="GET" action="" class="date-filter-form">
      ' . $hidden . '
      <input type="hidden" name="' . $prefix . '_page" value="1">
      <label>From <input type="date" name="' . $prefix . '_from" value="' . $from . '"></label>
      <label>To <input type="date" name="' . $prefix . '_to" value="' . $to . '"></label>
      ' . $adminDropdown . '
      <button type="submit" class="df-btn">Filter</button>
      ' . ($hasFilter ? '<a href="?' . http_build_query($clearParams) . $anchor . '" class="df-clear">✕ Clear</a>' : '') . '
    </form>';
}

$ar = new ActionRequest($conn);
$ar->expireOldRequests();

$pending  = $ar->getPendingRequests();
$unread   = $ar->unreadCount();

$conn->query("UPDATE superadmin_notifications SET is_read = 1 WHERE is_read = 0");

// --- Handle approve / reject POST ---
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $note      = trim($_POST['note'] ?? '');

    if ($action === 'approve' && $requestId) {
      if ($ar->approve($requestId, $_SESSION['user_id'])) {
          $ar->log($_SESSION['user_id'], 'super_admin', 'approved_request', "Request #$requestId approved");
          notifyAdminOfReview($conn, $requestId);

          $reqRow = $conn->query("SELECT * FROM action_requests WHERE id=$requestId")->fetch_assoc();
          $actionType = $reqRow['action_type'] ?? '';
          $meta = json_decode($reqRow['meta_json'] ?? '{}', true) ?? [];

          if ($actionType === 'edit_personnel') {
            $tid = (int)($meta['teacher_id'] ?? $reqRow['target_id']);
            $conn->query("ALTER TABLE personnels ADD COLUMN IF NOT EXISTS leave_days_allocation INT DEFAULT 30");

            $fn   = $conn->real_escape_string($meta['full_name']        ?? '');
            $fnf  = $conn->real_escape_string($meta['first_name']       ?? '');
            $fnm  = $conn->real_escape_string($meta['middle_name']      ?? '');
            $fnl  = $conn->real_escape_string($meta['last_name']        ?? '');
            $em   = $conn->real_escape_string($meta['email']            ?? '');
            $ph   = $conn->real_escape_string($meta['phone']            ?? '');
            $sub  = $conn->real_escape_string($meta['subject']          ?? '');
            $dep  = $conn->real_escape_string($meta['department']       ?? '');
            $addr = $conn->real_escape_string($meta['address']          ?? '');
            $dh   = $conn->real_escape_string($meta['date_hired']       ?? '');
            $st   = in_array($meta['status'] ?? '', ['active','inactive']) ? $meta['status'] : 'active';
            $la   = (int)($meta['leave_days_allocation']                ?? 30);
            $as   = $conn->real_escape_string($meta['appointment_status'] ?? 'Regular');
            $et   = $conn->real_escape_string($meta['employment_type']  ?? '');
            $pl   = $conn->real_escape_string($meta['personnel_level']  ?? '');
            $tin  = $conn->real_escape_string($meta['tin_number']       ?? '');
            $phi  = $conn->real_escape_string($meta['philhealth_number'] ?? '');
            $pag  = $conn->real_escape_string($meta['pagibig_number']   ?? '');
            $sss  = $conn->real_escape_string($meta['sss_number']       ?? '');
            $crn  = $conn->real_escape_string($meta['crn_number']       ?? '');
            $bp   = $conn->real_escape_string($meta['bp_number']        ?? '');

            $conn->query("UPDATE personnels SET
                full_name='$fn', first_name='$fnf', middle_name='$fnm', last_name='$fnl',
                email='$em', phone='$ph',
                subject='$sub', department='$dep', address='$addr',
                date_hired='$dh', status='$st', leave_days_allocation=$la,
                appointment_status='$as', employment_type='$et', personnel_level='$pl',
                tin_number='$tin', philhealth_number='$phi', pagibig_number='$pag',
                sss_number='$sss', crn_number='$crn', bp_number='$bp'
                WHERE id=$tid");

            if (!empty($meta['new_password_hash'])) {
                $hash = $conn->real_escape_string($meta['new_password_hash']);
                $uid  = $conn->query("SELECT user_id FROM personnels WHERE id=$tid")->fetch_assoc()['user_id'] ?? 0;
                if ($uid) $conn->query("UPDATE users SET password='$hash' WHERE id=$uid");
            }

            $conn->query("UPDATE action_requests SET token_used=1 WHERE id=$requestId");
            $ar->log($_SESSION['user_id'], 'super_admin', 'executed_edit_personnel',
                "Executed edit of personnel ID $tid (Request #$requestId)");
            $message = "✅ Request #$requestId approved and personnel record updated immediately.";
          }
          elseif ($actionType === 'delete_record') {
              $tid = (int)($meta['teacher_id'] ?? $reqRow['target_id']);
              $r   = $conn->query("SELECT user_id, full_name FROM personnels WHERE id=$tid")->fetch_assoc();
              if ($r) {
                  $conn->query("DELETE FROM users WHERE id={$r['user_id']}");
                  $conn->query("DELETE FROM personnels WHERE id=$tid");
              }
              $conn->query("UPDATE action_requests SET token_used=1 WHERE id=$requestId");
              $ar->log($_SESSION['user_id'], 'super_admin', 'executed_delete_personnel',
                  "Deleted personnel ID $tid (Request #$requestId)");
              $message = "✅ Request #$requestId approved and personnel record deleted.";
          } elseif ($actionType === 'add_personnel') {
              $tid = (int)$reqRow['target_id'];
              $m   = $meta; // shorthand

              // Hash should already be stored; if not, skip password update
              $hashed = $conn->real_escape_string($m['password_hash'] ?? '');

              // 1. Insert into users table
              $uname = $conn->real_escape_string($m['username'] ?? '');
              $conn->query("INSERT INTO users (username, password, role) VALUES ('$uname', '$hashed', 'teacher')");
              $newUserId = $conn->insert_id;

              if ($newUserId) {
                  // 2. Build all fields
                  $fn   = $conn->real_escape_string($m['full_name']           ?? '');
                  $fnf  = $conn->real_escape_string($m['first_name']          ?? '');
                  $fnm  = $conn->real_escape_string($m['middle_name']         ?? '');
                  $fnl  = $conn->real_escape_string($m['last_name']           ?? '');
                  $sfx  = $conn->real_escape_string($m['suffix']              ?? '');
                  $gen  = $conn->real_escape_string($m['gender']              ?? '');
                  $civ  = $conn->real_escape_string($m['civil_status']        ?? '');
                  $dob  = $conn->real_escape_string($m['date_of_birth']       ?? '');
                  $pob  = $conn->real_escape_string($m['place_of_birth']      ?? '');
                  $em   = $conn->real_escape_string($m['email']               ?? '');
                  $ph   = $conn->real_escape_string($m['phone']               ?? '');
                  $addr = $conn->real_escape_string($m['address']             ?? '');
                  $sub  = $conn->real_escape_string($m['subject']             ?? '');
                  $dep  = $conn->real_escape_string($m['department']          ?? '');
                  $et   = $conn->real_escape_string($m['employment_type']     ?? '');
                  $dh   = $conn->real_escape_string($m['date_hired']          ?? '');
                  $la   = (int)($m['leave_days_allocation']                   ?? 30);
                  $tin  = $conn->real_escape_string($m['tin_number']          ?? '');
                  $phi  = $conn->real_escape_string($m['philhealth_number']   ?? '');
                  $pag  = $conn->real_escape_string($m['pagibig_number']      ?? '');
                  $sss  = $conn->real_escape_string($m['sss_number']          ?? '');
                  $crn  = $conn->real_escape_string($m['crn_number']          ?? '');
                  $bp   = $conn->real_escape_string($m['bp_number']           ?? '');
                  $as   = $conn->real_escape_string($m['appointment_status']  ?? 'Newly Hired');
                  $pl   = $conn->real_escape_string($m['personnel_level']     ?? '');

                  $conn->query("INSERT INTO personnels (
                      user_id, last_name, first_name, middle_name, suffix,
                      gender, civil_status, date_of_birth, place_of_birth,
                      email, phone, address,
                      subject, department, employment_type,
                      date_hired, status, leave_days_allocation,
                      tin_number, philhealth_number, pagibig_number, sss_number, crn_number, bp_number,
                      appointment_status, personnel_level
                  ) VALUES (
                      $newUserId, '$fnl','$fnf','$fnm','$sfx',
                      '$gen','$civ','$dob','$pob',
                      '$em','$ph','$addr',
                      '$sub','$dep','$et',
                      '$dh','active',$la,
                      '$tin','$phi','$pag','$sss','$crn','$bp',
                      '$as','$pl'
                  )");

                  $conn->query("UPDATE action_requests SET token_used=1 WHERE id=$requestId");
                  $ar->log($_SESSION['user_id'], 'super_admin', 'executed_add_personnel',
                      "Added personnel: {$fn} (Request #$requestId)");
                  $message = "✅ Request #$requestId approved and personnel '{$fn}' has been added.";
              } else {
                  $message = "❌ Failed to create user account for this personnel. Username may already exist.";
              }
          } else {
              $message = "✅ Request #$requestId approved. Admin has 15 minutes to execute the action.";
          }
      }
    } elseif ($action === 'reject' && $requestId) {
        if ($ar->reject($requestId, $_SESSION['user_id'], $note)) {
            $ar->log($_SESSION['user_id'], 'super_admin', 'rejected_request', "Request #$requestId rejected. Note: $note");
            notifyAdminOfReview($conn, $requestId);
            $message = "❌ Request #$requestId rejected.";
        }
    }
    $pending = $ar->getPendingRequests();
}

// ── PAGINATION + DATE FILTER + ADMIN FILTER PARAMS ───────────────────────────
$perPage = 10;

// --- Request History ---
$rhPage       = max(1, (int)($_GET['rh_page']  ?? 1));
$rhDateFrom   = $_GET['rh_from']  ?? '';
$rhDateTo     = $_GET['rh_to']    ?? '';
$rhAdminFilter= trim($_GET['rh_admin'] ?? '');
$rhWhere      = "WHERE 1=1";
if ($rhDateFrom)   $rhWhere .= " AND DATE(ar.requested_at) >= '" . $conn->real_escape_string($rhDateFrom) . "'";
if ($rhDateTo)     $rhWhere .= " AND DATE(ar.requested_at) <= '" . $conn->real_escape_string($rhDateTo)   . "'";
if ($rhAdminFilter) $rhWhere .= " AND u.username = '" . $conn->real_escape_string($rhAdminFilter) . "'";
$rhTotal  = (int)$conn->query("SELECT COUNT(*) AS c FROM action_requests ar JOIN users u ON u.id=ar.admin_user_id $rhWhere")->fetch_assoc()['c'];
$rhPages  = max(1, ceil($rhTotal / $perPage));
$rhOffset = ($rhPage - 1) * $perPage;
$recent   = $conn->query("
    SELECT ar.*, u.username AS admin_username
    FROM action_requests ar JOIN users u ON u.id = ar.admin_user_id
    $rhWhere ORDER BY ar.requested_at DESC LIMIT $perPage OFFSET $rhOffset
")->fetch_all(MYSQLI_ASSOC);

// ALL rows for PDF export (no pagination limit)
$recentAll = $conn->query("
    SELECT ar.*, u.username AS admin_username
    FROM action_requests ar JOIN users u ON u.id = ar.admin_user_id
    $rhWhere ORDER BY ar.requested_at DESC
")->fetch_all(MYSQLI_ASSOC);

// --- Admin Accounts ---
$amPage       = max(1, (int)($_GET['am_page']  ?? 1));
$amDateFrom   = $_GET['am_from']  ?? '';
$amDateTo     = $_GET['am_to']    ?? '';
$amAdminFilter= trim($_GET['am_admin'] ?? '');
$amWhere      = "WHERE role='admin'";
if ($amDateFrom)   $amWhere .= " AND DATE(created_at) >= '" . $conn->real_escape_string($amDateFrom) . "'";
if ($amDateTo)     $amWhere .= " AND DATE(created_at) <= '" . $conn->real_escape_string($amDateTo)   . "'";
if ($amAdminFilter) $amWhere .= " AND username = '" . $conn->real_escape_string($amAdminFilter) . "'";
$amTotal  = (int)$conn->query("SELECT COUNT(*) AS c FROM users $amWhere")->fetch_assoc()['c'];
$amPages  = max(1, ceil($amTotal / $perPage));
$amOffset = ($amPage - 1) * $perPage;
$admins   = $conn->query("SELECT id, username, created_at FROM users $amWhere ORDER BY username LIMIT $perPage OFFSET $amOffset")->fetch_all(MYSQLI_ASSOC);

// ALL admin rows for export
$adminsAll = $conn->query("SELECT id, username, created_at FROM users $amWhere ORDER BY username")->fetch_all(MYSQLI_ASSOC);

// --- Audit Log ---
$alPage       = max(1, (int)($_GET['al_page']  ?? 1));
$alDateFrom   = $_GET['al_from']  ?? '';
$alDateTo     = $_GET['al_to']    ?? '';
$alAdminFilter= trim($_GET['al_admin'] ?? '');
$alWhere      = "WHERE 1=1";
if ($alDateFrom)   $alWhere .= " AND DATE(al.performed_at) >= '" . $conn->real_escape_string($alDateFrom) . "'";
if ($alDateTo)     $alWhere .= " AND DATE(al.performed_at) <= '" . $conn->real_escape_string($alDateTo)   . "'";
if ($alAdminFilter) $alWhere .= " AND u.username = '" . $conn->real_escape_string($alAdminFilter) . "'";
$alTotal  = (int)$conn->query("SELECT COUNT(*) AS c FROM admin_audit_log al JOIN users u ON u.id = al.user_id $alWhere")->fetch_assoc()['c'];
$alPages  = max(1, ceil($alTotal / $perPage));
$alOffset = ($alPage - 1) * $perPage;
$audit    = $conn->query("
    SELECT al.*, u.username FROM admin_audit_log al JOIN users u ON u.id = al.user_id
    $alWhere ORDER BY al.performed_at DESC LIMIT $perPage OFFSET $alOffset
")->fetch_all(MYSQLI_ASSOC);

// ALL audit rows for PDF export
$auditAll = $conn->query("
    SELECT al.*, u.username FROM admin_audit_log al JOIN users u ON u.id = al.user_id
    $alWhere ORDER BY al.performed_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Pass data to JS for export
$rhDataJson    = json_encode($recentAll);
$alDataJson    = json_encode($auditAll);
$adminsDataJson= json_encode($adminsAll);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin — Control Panel</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<!-- jsPDF + AutoTable for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<!-- SheetJS for Excel export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --red: #c8102e; --red-dark: #9b0c22; --red-deep: #6b0518;
  --gold: #d4a84b; --gold-light: #f0d080;
  --cream: #fdf8f0; --text: #1a0608; --muted: #7a5a5e;
  --border: #e8cdd0; --white: #ffffff;
  --green: #1a7a45; --green-bg: #f0faf4;
  --orange: #b85c00; --orange-bg: #fff7ed;
  --blue: #1a4a8a; --blue-bg: #f0f6ff;
}
body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--text); }

/* SIDEBAR */
.layout { display: flex; min-height: 100vh; }
.sidebar {
  width: 260px; flex-shrink: 0;
  background: linear-gradient(180deg, var(--red-deep) 0%, var(--red-dark) 60%, var(--red) 100%);
  padding: 0;
  display: flex; flex-direction: column;
  position: sticky; top: 0; height: 100vh;
}
.sb-logo { padding: 28px 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
.sb-logo .badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(212,168,75,0.2); border: 1px solid rgba(212,168,75,0.4);
  border-radius: 999px; padding: 5px 14px;
  color: var(--gold-light); font-size: 10px;
  font-family: 'JetBrains Mono', monospace; letter-spacing: 2px; text-transform: uppercase;
}
.sb-logo .badge::before {
  content: ''; width: 6px; height: 6px; border-radius: 50%;
  background: var(--gold); box-shadow: 0 0 6px var(--gold);
  animation: pulse 2s infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }
.sb-title { color: #fff; font-family: 'DM Serif Display', serif; font-size: 18px; margin-top: 12px; }
.sb-sub { color: rgba(255,255,255,0.5); font-size: 11px; font-family: 'JetBrains Mono', monospace; margin-top: 3px; }
.sb-nav { padding: 16px 0; flex: 1; }
.sb-nav a {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 24px; color: rgba(255,255,255,0.7);
  text-decoration: none; font-size: 14px; font-weight: 500;
  transition: all 0.2s; border-left: 3px solid transparent;
}
.sb-nav a:hover, .sb-nav a.active {
  background: rgba(255,255,255,0.1); color: #fff;
  border-left-color: var(--gold);
}
.sb-nav a .icon { font-size: 18px; width: 22px; text-align: center; }
.sb-nav .section-label {
  padding: 16px 24px 6px; font-size: 10px;
  font-family: 'JetBrains Mono', monospace; letter-spacing: 2px;
  text-transform: uppercase; color: rgba(255,255,255,0.3);
}
.sb-footer {
  padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.1);
  color: rgba(255,255,255,0.5); font-size: 12px;
}
.sb-footer strong { color: rgba(255,255,255,0.8); display: block; margin-bottom: 2px; }
.sb-footer a { color: rgba(255,255,255,0.4); font-size: 11px; text-decoration: none; }
.sb-footer a:hover { color: #fff; }

/* MAIN */
.main { flex: 1; padding: 32px 36px; overflow-y: auto; }
.page-header { margin-bottom: 28px; }
.page-header h1 { font-family: 'DM Serif Display', serif; font-size: 28px; color: var(--text); }
.page-header p { color: var(--muted); font-size: 14px; margin-top: 4px; }

/* STATS ROW */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card {
  background: var(--white); border: 1.5px solid var(--border);
  border-radius: 16px; padding: 20px 22px; transition: box-shadow 0.2s;
}
.stat-card:hover { box-shadow: 0 4px 20px rgba(200,16,46,0.08); }
.stat-card .label { font-size: 11px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.stat-card .value { font-size: 32px; font-weight: 700; color: var(--text); line-height: 1; }
.stat-card .value.red { color: var(--red); }
.stat-card .value.green { color: var(--green); }
.stat-card .value.orange { color: var(--orange); }

/* FLASH */
.flash { border-radius: 12px; padding: 14px 18px; font-size: 14px; font-weight: 500; margin-bottom: 22px; }
.flash.success { background: var(--green-bg); color: var(--green); border: 1.5px solid #a3d9b8; }
.flash.error   { background: #fff0f2; color: var(--red); border: 1.5px solid #ffc9d0; }

/* SECTION */
.section { background: var(--white); border: 1.5px solid var(--border); border-radius: 18px; margin-bottom: 24px; overflow: hidden; }
.section-head { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.section-head h2 { font-family: 'DM Serif Display', serif; font-size: 20px; }
.section-head .badge-count {
  background: var(--red); color: #fff;
  border-radius: 999px; padding: 3px 12px;
  font-size: 12px; font-weight: 700;
}
.section-head-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* EXPORT BUTTONS */
.btn-export-pdf {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: 1.5px solid #c8102e; background: #fff0f2; color: #c8102e;
  font-family: 'DM Sans', sans-serif; transition: all 0.18s; text-decoration: none;
}
.btn-export-pdf:hover { background: #c8102e; color: #fff; }
.btn-export-excel {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
  cursor: pointer; border: 1.5px solid #1a7a45; background: var(--green-bg); color: var(--green);
  font-family: 'DM Sans', sans-serif; transition: all 0.18s; text-decoration: none;
}
.btn-export-excel:hover { background: var(--green); color: #fff; }

/* REQUEST CARDS */
.request-list { padding: 16px 24px; display: flex; flex-direction: column; gap: 14px; }
.req-card {
  border: 1.5px solid var(--border); border-radius: 14px;
  padding: 18px 20px; background: #fdfbfb;
  display: flex; align-items: flex-start; gap: 16px;
}
.req-icon { font-size: 28px; flex-shrink: 0; margin-top: 2px; }
.req-body { flex: 1; }
.req-body .req-title { font-weight: 600; font-size: 15px; color: var(--text); }
.req-body .req-meta { font-size: 12px; color: var(--muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
.req-body .req-label { display: inline-block; margin-top: 6px; font-size: 12px; background: var(--orange-bg); color: var(--orange); border-radius: 6px; padding: 2px 10px; border: 1px solid #fddcb0; }
.req-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0; }
.btn { padding: 8px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; font-family: 'DM Sans', sans-serif; }
.btn-approve { background: var(--green); color: #fff; }
.btn-approve:hover { background: #155e35; }
.btn-reject  { background: #fff0f2; color: var(--red); border: 1.5px solid #ffc9d0; }
.btn-reject:hover { background: #ffe0e5; }
.btn-sm { padding: 6px 14px; font-size: 12px; }
.btn-primary { background: linear-gradient(135deg, var(--red), var(--red-dark)); color: #fff; box-shadow: 0 3px 12px rgba(200,16,46,0.2); }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(200,16,46,0.3); }

/* MODAL */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.5); z-index: 9999;
  align-items: center; justify-content: center;
  backdrop-filter: blur(4px);
}
.modal-overlay.open { display: flex; }
.modal { background: var(--white); border-radius: 20px; padding: 28px 32px; width: 440px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.modal h3 { font-family: 'DM Serif Display', serif; font-size: 22px; margin-bottom: 6px; }
.modal p { color: var(--muted); font-size: 14px; margin-bottom: 18px; }
.modal textarea { width: 100%; border: 1.5px solid var(--border); border-radius: 10px; padding: 12px; font-size: 14px; font-family: 'DM Sans', sans-serif; resize: vertical; min-height: 90px; outline: none; color: var(--text); }
.modal textarea:focus { border-color: var(--red); box-shadow: 0 0 0 3px rgba(200,16,46,0.08); }
.modal-actions { display: flex; gap: 10px; margin-top: 16px; justify-content: flex-end; }
.btn-ghost { background: var(--border); color: var(--muted); }
.btn-ghost:hover { background: #ddc8cc; }

/* TABLE */
.table-wrap { overflow-x: auto; padding: 0 24px 24px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th { text-align: left; padding: 10px 12px; font-size: 11px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); border-bottom: 2px solid var(--border); }
td { padding: 12px 12px; border-bottom: 1px solid #f5eaec; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
.status-badge { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 3px 12px; font-size: 11px; font-weight: 600; }
.status-pending  { background: var(--orange-bg); color: var(--orange); border: 1px solid #fddcb0; }
.status-approved { background: var(--green-bg); color: var(--green); border: 1px solid #a3d9b8; }
.status-rejected { background: #fff0f2; color: var(--red); border: 1px solid #ffc9d0; }
.status-expired  { background: #f5f5f5; color: #999; border: 1px solid #ddd; }

/* ADMIN CREATION */
.create-admin-form { padding: 24px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 14px; align-items: end; }
.field label { display: block; font-size: 12px; font-weight: 600; color: var(--text); margin-bottom: 7px; }
.field input {
  width: 100%; padding: 11px 14px; border: 1.5px solid var(--border);
  border-radius: 10px; font-size: 14px; outline: none;
  font-family: 'DM Sans', sans-serif; color: var(--text); background: var(--white);
}
.field input:focus { border-color: var(--red); box-shadow: 0 0 0 3px rgba(200,16,46,0.08); }

/* EMPTY STATE */
.empty-state { text-align: center; padding: 40px 24px; color: var(--muted); }
.empty-state .icon { font-size: 40px; margin-bottom: 10px; }
.empty-state p { font-size: 14px; }

/* PAGINATION & DATE FILTER */
.pg-wrap { display:flex; align-items:center; gap:4px; justify-content:flex-end; padding:14px 24px; border-top:1px solid var(--border); flex-wrap:wrap; }
.pg-btn { min-width:32px; height:32px; border-radius:7px; border:1.5px solid var(--border); background:#fff; color:var(--text); font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; transition:all 0.15s; padding:0 6px; }
.pg-btn:hover:not(:disabled) { border-color:var(--red); color:var(--red); background:rgba(200,16,46,0.04); }
.pg-btn.pg-active { background:var(--red); border-color:var(--red); color:#fff; }
.pg-btn:disabled { opacity:0.35; cursor:not-allowed; }

.date-filter-form {
  display:flex; align-items:center; gap:10px;
  padding:14px 24px; border-bottom:1px solid var(--border);
  flex-wrap:wrap; background:#fdfbfb;
}
.date-filter-form label { font-size:12px; color:var(--muted); display:flex; align-items:center; gap:6px; font-family:'JetBrains Mono',monospace; }
.date-filter-form input[type=date] { padding:6px 10px; border:1.5px solid var(--border); border-radius:8px; font-size:12px; font-family:'DM Sans',sans-serif; outline:none; color:var(--text); }
.date-filter-form input[type=date]:focus { border-color:var(--red); }
.df-select {
  padding:6px 10px; border:1.5px solid var(--border); border-radius:8px;
  font-size:12px; font-family:'DM Sans',sans-serif; outline:none; color:var(--text);
  background:#fff; cursor:pointer; min-width:140px;
}
.df-select:focus { border-color:var(--red); box-shadow: 0 0 0 3px rgba(200,16,46,0.08); }
.df-btn { padding:7px 16px; background:var(--red); color:#fff; border:none; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; }
.df-btn:hover { background:var(--red-dark); }
.df-clear { font-size:12px; color:var(--muted); text-decoration:none; padding:4px 8px; border-radius:6px; border:1px dashed var(--border); }
.df-clear:hover { color:var(--red); border-color:var(--red); }
.table-info { font-size:12px; color:var(--muted); font-family:'JetBrains Mono',monospace; padding:0 24px 10px; }
.table-info span { color:var(--text); font-weight:600; }

/* Active filter badge */
.active-filter-badge {
  display:inline-flex; align-items:center; gap:5px;
  background:#fff3e0; border:1px solid #fddcb0; border-radius:999px;
  padding:2px 10px; font-size:11px; color:var(--orange); font-family:'JetBrains Mono',monospace;
}

@media (max-width: 1100px) {
  .stats-row { grid-template-columns: repeat(2, 1fr); }
  .form-row { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 768px) {
  .layout { flex-direction: column; }
  .sidebar { width: 100%; height: auto; position: static; }
  .main { padding: 20px 16px; }
  .stats-row { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sb-logo">
      <div class="badge">Super Admin</div>
      <div class="sb-title">TMCNHS Portal</div>
      <div class="sb-sub">Control Panel</div>
    </div>
    <nav class="sb-nav">
      <div class="section-label">Overview</div>
      <a href="superadmin_dashboard.php" class="active">
        <span class="icon">🛡️</span> Dashboard
      </a>
      <div class="section-label">Approvals</div>
      <a href="#pending-requests">
        <span class="icon">⏳</span> Pending Requests
        <?php if ($unread > 0): ?>
          <span style="margin-left:auto;background:var(--gold);color:#fff;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:700"><?= $unread ?></span>
        <?php endif; ?>
      </a>
      <a href="#request-history">
        <span class="icon">📋</span> Request History
      </a>
      <div class="section-label">Management</div>
      <a href="#admin-management">
        <span class="icon">👤</span> Admin Accounts
      </a>
      <a href="#audit-log">
        <span class="icon">🗂️</span> Audit Log
      </a>
      <a href="../ADMIN_SIDE/admin_dashboard.php">
        <span class="icon">↩️</span> Admin Dashboard
      </a>
    </nav>
    <div class="sb-footer">
      <strong>👑 <?= htmlspecialchars($_SESSION['username']) ?></strong>
      <a href="../logout.php">Sign out →</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main">
    <div class="page-header">
      <h1>Super Admin Panel</h1>
      <p>Review and approve admin action requests, manage admin accounts, and monitor all activity.</p>
    </div>

    <?php if ($message): ?>
      <div class="flash <?= str_starts_with($message, '✅') ? 'success' : 'error' ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- STATS -->
    <?php
      $totalPending  = count($pending);
      $totalApproved = $conn->query("SELECT COUNT(*) AS c FROM action_requests WHERE status='approved'")->fetch_assoc()['c'];
      $totalRejected = $conn->query("SELECT COUNT(*) AS c FROM action_requests WHERE status='rejected'")->fetch_assoc()['c'];
      $totalAdmins   = count($admins);
    ?>
    <div class="stats-row">
      <div class="stat-card">
        <div class="label">Pending</div>
        <div class="value red"><?= $totalPending ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Approved (All time)</div>
        <div class="value green"><?= $totalApproved ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Rejected</div>
        <div class="value orange"><?= $totalRejected ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Admin Accounts</div>
        <div class="value"><?= $totalAdmins ?></div>
      </div>
    </div>

    <!-- PENDING REQUESTS -->
    <div class="section" id="pending-requests">
      <div class="section-head">
        <h2>⏳ Pending Requests</h2>
        <?php if ($totalPending > 0): ?>
          <span class="badge-count"><?= $totalPending ?> awaiting</span>
        <?php endif; ?>
      </div>
      <?php if (empty($pending)): ?>
        <div class="empty-state">
          <div class="icon">✅</div>
          <p>No pending requests. All caught up!</p>
        </div>
      <?php else: ?>
        <div class="request-list">
          <?php foreach ($pending as $req): ?>
          <?php
            $icons = [
              'download_file'   => '📥',
              'delete_record'   => '🗑️',
              'export_data'     => '📤',
              'edit_personnel'  => '✏️',
              'create_admin'    => '👤',
              'deactivate_user' => '🚫',
              'other'           => '📌',
            ];
            $icon = $icons[$req['action_type']] ?? '📌';
          ?>
          <div class="req-card">
            <div class="req-icon"><?= $icon ?></div>
            <?php $reqNote = trim(json_decode($req['meta_json'] ?? '{}', true)['note'] ?? ''); ?>
            <div class="req-body">
              <div class="req-title"><?= htmlspecialchars($req['action_label']) ?></div>
              <div class="req-meta">
                By: <strong><?= htmlspecialchars($req['admin_username']) ?></strong>
                &nbsp;·&nbsp; Requested: <?= date('M j, Y g:i A', strtotime($req['requested_at'])) ?>
                &nbsp;·&nbsp; Request #<?= $req['id'] ?>
              </div>
              <span class="req-label"><?= str_replace('_', ' ', strtoupper($req['action_type'])) ?></span>
              <?php if ($reqNote): ?>
                <div style="margin-top:8px;padding:8px 12px;background:#fffbf0;border:1px solid #fddcb0;border-radius:8px;font-size:13px;color:#6b3a00">
                  📝 <strong>Reason:</strong> <?= htmlspecialchars($reqNote) ?>
                </div>
              <?php else: ?>
                <div style="margin-top:8px;padding:6px 12px;background:#fff0f2;border:1px solid #ffc9d0;border-radius:8px;font-size:12px;color:#c8102e">
                  ⚠️ No reason provided
                </div>
              <?php endif; ?>
            </div>
            <div class="req-actions">
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                <button type="submit" class="btn btn-approve btn-sm">✓ Approve</button>
              </form>
              <button class="btn btn-reject btn-sm"
                      onclick="openReject(<?= $req['id'] ?>, '<?= htmlspecialchars($req['action_label'], ENT_QUOTES) ?>')">
                ✕ Reject
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ADMIN ACCOUNTS -->
    <div class="section" id="admin-management">
      <div class="section-head">
        <h2>👤 Admin Accounts</h2>
        <div class="section-head-right">
          <?php if ($amAdminFilter): ?>
            <span class="active-filter-badge">👤 <?= htmlspecialchars($amAdminFilter) ?></span>
          <?php endif; ?>
          <button class="btn-export-pdf" onclick="exportAdminPDF()">⬇ PDF</button>
          <button class="btn-export-excel" onclick="exportAdminExcel()">⬇ Excel</button>
        </div>
      </div>
      <div class="create-admin-form">
        <p style="font-size:14px;color:var(--muted);margin-bottom:18px;">Only the Super Admin can create new administrator accounts.</p>
        <form method="POST" action="create_admin.php">
          <div class="form-row">
            <div class="field">
              <label>Username</label>
              <input type="text" name="username" placeholder="e.g. admin_juan" required>
            </div>
            <div class="field">
              <label>Password</label>
              <input type="password" name="password" placeholder="Strong password" required>
            </div>
            <div class="field">
              <label>Confirm Password</label>
              <input type="password" name="password_confirm" placeholder="Repeat password" required>
            </div>
            <div class="field">
              <label>&nbsp;</label>
              <button type="submit" class="btn btn-primary">+ Create Admin</button>
            </div>
          </div>
        </form>

        <?= dateFilterForm('am', $_GET, '#admin-management', $conn, true, false) ?>
        <div class="table-info">Showing <span><?= count($admins) ?></span> of <span><?= $amTotal ?></span> admins</div>
        <table style="margin-top:8px" id="adminTable">
          <thead>
            <tr><th>#</th><th>Username</th><th>Created</th><th>Action</th></tr>
          </thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td style="font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= $a['id'] ?></td>
              <td><strong><?= htmlspecialchars($a['username']) ?></strong></td>
              <td style="color:var(--muted)"><?= date('M j, Y', strtotime($a['created_at'])) ?></td>
              <td>
                <form method="POST" action="deactivate_admin.php"
                      onsubmit="return confirm('Deactivate <?= htmlspecialchars($a['username']) ?>?')">
                  <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                  <button type="submit" class="btn btn-reject btn-sm">Deactivate</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?= paginationLinks('am', $amPage, $amPages, $_GET) ?>
      </div>
    </div>

    <!-- REQUEST HISTORY TABLE -->
    <div class="section" id="request-history">
      <div class="section-head">
        <h2>📋 Request History</h2>
        <div class="section-head-right">
          <?php if ($rhAdminFilter): ?>
            <span class="active-filter-badge">👤 <?= htmlspecialchars($rhAdminFilter) ?></span>
          <?php endif; ?>
          <span style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace"><?= $rhTotal ?> total</span>
          <button class="btn-export-pdf" onclick="exportRequestHistoryPDF()">⬇ PDF</button>
        </div>
      </div>
      <?= dateFilterForm('rh', $_GET, '#request-history', $conn, true, false) ?>
      <div class="table-info">Showing <span><?= count($recent) ?></span> of <span><?= $rhTotal ?></span> requests</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>ID</th><th>Admin</th><th>Action</th><th>Requested</th><th>Status</th><th>Reviewed</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
              <td style="font-family:'JetBrains Mono',monospace;color:var(--muted)">#<?= $r['id'] ?></td>
              <td><strong><?= htmlspecialchars($r['admin_username']) ?></strong></td>
              <td><?= htmlspecialchars($r['action_label']) ?></td>
              <td style="color:var(--muted);font-size:12px"><?= date('M j, Y g:i A', strtotime($r['requested_at'])) ?></td>
              <td><span class="status-badge status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
              <td style="color:var(--muted);font-size:12px"><?= $r['reviewed_at'] ? date('M j, Y g:i A', strtotime($r['reviewed_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= paginationLinks('rh', $rhPage, $rhPages, $_GET) ?>
    </div>

    <!-- AUDIT LOG -->
    <div class="section" id="audit-log">
      <div class="section-head">
        <h2>🗂️ Audit Log</h2>
        <div class="section-head-right">
          <?php if ($alAdminFilter): ?>
            <span class="active-filter-badge">👤 <?= htmlspecialchars($alAdminFilter) ?></span>
          <?php endif; ?>
          <span style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace"><?= $alTotal ?> total</span>
          <button class="btn-export-pdf" onclick="exportAuditLogPDF()">⬇ PDF</button>
        </div>
      </div>
      <?= dateFilterForm('al', $_GET, '#audit-log', $conn, true, true) ?>
      <div class="table-info">Showing <span><?= count($audit) ?></span> of <span><?= $alTotal ?></span> entries</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>IP</th><th>Time</th></tr>
          </thead>
          <tbody>
            <?php foreach ($audit as $log): ?>
            <tr>
              <td><strong><?= htmlspecialchars($log['username']) ?></strong></td>
              <td><span style="font-family:'JetBrains Mono',monospace;font-size:11px"><?= $log['role'] ?></span></td>
              <td><?= htmlspecialchars($log['action']) ?></td>
              <td style="color:var(--muted);font-size:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['details']) ?></td>
              <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted)"><?= $log['ip_address'] ?></td>
              <td style="color:var(--muted);font-size:12px"><?= date('M j, g:i A', strtotime($log['performed_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= paginationLinks('al', $alPage, $alPages, $_GET) ?>
    </div>

  </main>
</div>

<!-- REJECT MODAL -->
<div class="modal-overlay" id="rejectModal">
  <div class="modal">
    <h3>Reject Request</h3>
    <p id="rejectModalDesc">Provide a reason (optional) — this will be shown to the admin.</p>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="request_id" id="rejectRequestId">
      <textarea name="note" placeholder="Reason for rejection..."></textarea>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeReject()">Cancel</button>
        <button type="submit" class="btn btn-reject">Reject Request</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── PHP DATA PASSED TO JS ────────────────────────────────────────────────────
const rhData     = <?= $rhDataJson ?>;
const alData     = <?= $alDataJson ?>;
const adminsData = <?= $adminsDataJson ?>;

// Active filters (for PDF subtitle)
const rhAdminFilter = <?= json_encode($rhAdminFilter) ?>;
const alAdminFilter = <?= json_encode($alAdminFilter) ?>;
const amAdminFilter = <?= json_encode($amAdminFilter) ?>;
const rhDateFrom    = <?= json_encode($rhDateFrom) ?>;
const rhDateTo      = <?= json_encode($rhDateTo) ?>;
const alDateFrom    = <?= json_encode($alDateFrom) ?>;
const alDateTo      = <?= json_encode($alDateTo) ?>;
const amDateFrom    = <?= json_encode($amDateFrom) ?>;
const amDateTo      = <?= json_encode($amDateTo) ?>;

// ── HELPERS ──────────────────────────────────────────────────────────────────
function filterLabel(admin, from, to) {
    const parts = [];
    if (admin) parts.push('Admin: ' + admin);
    if (from)  parts.push('From: ' + from);
    if (to)    parts.push('To: ' + to);
    return parts.length ? parts.join('  |  ') : 'All records';
}

function pdfHeader(doc, title, subtitle) {
    // Red header bar
    doc.setFillColor(155, 12, 34);
    doc.rect(0, 0, doc.internal.pageSize.getWidth(), 28, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(14);
    doc.setFont('helvetica', 'bold');
    doc.text('TMCNHS Portal — Super Admin', 14, 11);
    doc.setFontSize(10);
    doc.setFont('helvetica', 'normal');
    doc.text(title, 14, 19);

    // Subtitle / filter info
    doc.setTextColor(80, 40, 50);
    doc.setFontSize(8);
    doc.text(subtitle, 14, 33);

    // Generated at
    const now = new Date().toLocaleString();
    doc.setFontSize(7);
    doc.setTextColor(150, 100, 110);
    doc.text('Generated: ' + now, doc.internal.pageSize.getWidth() - 14, 33, { align: 'right' });
}

// ── EXPORT: REQUEST HISTORY PDF ──────────────────────────────────────────────
function exportRequestHistoryPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    pdfHeader(doc, 'Request History', filterLabel(rhAdminFilter, rhDateFrom, rhDateTo));

    const rows = rhData.map(r => [
        '#' + r.id,
        r.admin_username || '',
        r.action_label   || '',
        r.requested_at ? new Date(r.requested_at).toLocaleString() : '',
        (r.status || '').charAt(0).toUpperCase() + (r.status || '').slice(1),
        r.reviewed_at ? new Date(r.reviewed_at).toLocaleString() : '—',
    ]);

    doc.autoTable({
        startY: 38,
        head: [['ID', 'Admin', 'Action', 'Requested', 'Status', 'Reviewed']],
        body: rows,
        styles: { fontSize: 8, cellPadding: 4 },
        headStyles: { fillColor: [155, 12, 34], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [253, 248, 240] },
        didDrawPage: (data) => {
            doc.setFontSize(7);
            doc.setTextColor(150);
            doc.text('Page ' + data.pageNumber, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 8, { align: 'center' });
        }
    });

    const fname = 'request_history' + (rhAdminFilter ? '_' + rhAdminFilter : '') + '.pdf';
    doc.save(fname);
}

// ── EXPORT: AUDIT LOG PDF ────────────────────────────────────────────────────
function exportAuditLogPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });

    pdfHeader(doc, 'Audit Log', filterLabel(alAdminFilter, alDateFrom, alDateTo));

    const rows = alData.map(r => [
        r.username   || '',
        r.role       || '',
        r.action     || '',
        (r.details   || '').substring(0, 60) + ((r.details || '').length > 60 ? '…' : ''),
        r.ip_address || '',
        r.performed_at ? new Date(r.performed_at).toLocaleString() : '',
    ]);

    doc.autoTable({
        startY: 38,
        head: [['User', 'Role', 'Action', 'Details', 'IP', 'Time']],
        body: rows,
        styles: { fontSize: 8, cellPadding: 4 },
        headStyles: { fillColor: [155, 12, 34], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [253, 248, 240] },
        columnStyles: { 3: { cellWidth: 70 } },
        didDrawPage: (data) => {
            doc.setFontSize(7);
            doc.setTextColor(150);
            doc.text('Page ' + data.pageNumber, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 8, { align: 'center' });
        }
    });

    const fname = 'audit_log' + (alAdminFilter ? '_' + alAdminFilter : '') + '.pdf';
    doc.save(fname);
}

// ── EXPORT: ADMIN ACCOUNTS PDF ───────────────────────────────────────────────
function exportAdminPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    pdfHeader(doc, 'Admin Accounts', filterLabel(amAdminFilter, amDateFrom, amDateTo));

    const rows = adminsData.map(a => [
        a.id,
        a.username   || '',
        a.created_at ? new Date(a.created_at).toLocaleDateString() : '',
    ]);

    doc.autoTable({
        startY: 38,
        head: [['ID', 'Username', 'Created']],
        body: rows,
        styles: { fontSize: 9, cellPadding: 5 },
        headStyles: { fillColor: [155, 12, 34], textColor: 255, fontStyle: 'bold' },
        alternateRowStyles: { fillColor: [253, 248, 240] },
        didDrawPage: (data) => {
            doc.setFontSize(7);
            doc.setTextColor(150);
            doc.text('Page ' + data.pageNumber, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 8, { align: 'center' });
        }
    });

    const fname = 'admin_accounts' + (amAdminFilter ? '_' + amAdminFilter : '') + '.pdf';
    doc.save(fname);
}

// ── EXPORT: ADMIN ACCOUNTS EXCEL ─────────────────────────────────────────────
function exportAdminExcel() {
    const rows = adminsData.map(a => ({
        'ID':       a.id,
        'Username': a.username,
        'Created':  a.created_at ? new Date(a.created_at).toLocaleDateString() : '',
    }));

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows);

    // Column widths
    ws['!cols'] = [{ wch: 8 }, { wch: 25 }, { wch: 18 }];

    XLSX.utils.book_append_sheet(wb, ws, 'Admin Accounts');
    const fname = 'admin_accounts' + (amAdminFilter ? '_' + amAdminFilter : '') + '.xlsx';
    XLSX.writeFile(wb, fname);
}

// ── MODAL ────────────────────────────────────────────────────────────────────
function openReject(id, label) {
    document.getElementById('rejectRequestId').value = id;
    document.getElementById('rejectModalDesc').textContent = `Rejecting: "${label}"`;
    document.getElementById('rejectModal').classList.add('open');
}
function closeReject() {
    document.getElementById('rejectModal').classList.remove('open');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeReject();
});

// Auto-refresh every 30 seconds to catch new requests
setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>