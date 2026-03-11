<?php
// org_management.php — ORGANIZATIONAL MANAGEMENT
require_once 'admin_shared.php';

$err = '';

// ── HELPERS ──────────────────────────────────────────────────────────────────
function fetchDepartments($conn) {
    $arr = [];
    $res = $conn->query("
        SELECT d.*,
               p.full_name AS head_name,
               (SELECT COUNT(*) FROM personnels per WHERE per.department = d.name AND per.status = 'active') AS personnel_count
        FROM departments d
        LEFT JOIN personnels p ON d.head_personnel_id = p.id
        ORDER BY d.name
    ");
    if ($res) while ($row = $res->fetch_assoc()) $arr[] = $row;
    return $arr;
}

function fetchPositions($conn) {
    $arr = [];
    $res = $conn->query("
        SELECT pos.*,
               (SELECT COUNT(*) FROM personnels p WHERE p.subject = pos.title AND p.status = 'active') AS personnel_count
        FROM positions pos
        ORDER BY pos.title
    ");
    if ($res) while ($row = $res->fetch_assoc()) $arr[] = $row;
    return $arr;
}

function fetchPersonnelList($conn) {
    $arr = [];
    $res = $conn->query("SELECT id, full_name, subject FROM personnels WHERE status='active' ORDER BY full_name");
    if ($res) while ($row = $res->fetch_assoc()) $arr[] = $row;
    return $arr;
}

// ── ADD DEPARTMENT ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_department') {
    $name    = trim($_POST['name'] ?? '');
    $code    = trim($_POST['code'] ?? '') ?: null;
    $desc    = trim($_POST['description'] ?? '') ?: null;
    $head_id = intval($_POST['head_personnel_id'] ?? 0) ?: null;
    $status  = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if (!$name) { $err = 'Department name is required.'; }
    else {
        $chk = $conn->prepare("SELECT id FROM departments WHERE name=?");
        $chk->bind_param('s', $name); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $err = "Department '{$name}' already exists.";
        } else {
            $s = $conn->prepare("INSERT INTO departments (name,code,description,head_personnel_id,status) VALUES (?,?,?,?,?)");
            $s->bind_param('sssss', $name, $code, $desc, $head_id, $status);
            if ($s->execute()) { header("Location: org_management.php?tab=departments&swal=added&name=".urlencode($name)); exit; }
            else $err = $s->error;
        }
    }
}

// ── EDIT DEPARTMENT ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_department') {
    $id      = (int)$_POST['dept_id'];
    $name    = trim($_POST['name'] ?? '');
    $code    = trim($_POST['code'] ?? '') ?: null;
    $desc    = trim($_POST['description'] ?? '') ?: null;
    $head_id = intval($_POST['head_personnel_id'] ?? 0) ?: null;
    $status  = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if (!$name) { $err = 'Department name is required.'; }
    else {
        $s = $conn->prepare("UPDATE departments SET name=?,code=?,description=?,head_personnel_id=?,status=? WHERE id=?");
        $s->bind_param('sssssi', $name, $code, $desc, $head_id, $status, $id);
        $s->execute();
        header("Location: org_management.php?tab=departments&swal=updated&name=".urlencode($name)); exit;
    }
}

// ── DELETE DEPARTMENT ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_department') {
    $id = (int)$_POST['dept_id'];
    $conn->query("DELETE FROM departments WHERE id=$id");
    header("Location: org_management.php?tab=departments&swal=deleted&what=Department"); exit;
}

// ── ADD POSITION ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_position') {
    $title   = trim($_POST['title'] ?? '');
    $level   = in_array($_POST['level'] ?? '', ['Teaching','Non-Teaching','Administrative','Managerial']) ? $_POST['level'] : 'Teaching';
    $desc    = trim($_POST['description'] ?? '') ?: null;
    $status  = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if (!$title) { $err = 'Position title is required.'; }
    else {
        $stmt = $conn->prepare("INSERT INTO positions (title,level,description,status) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $title, $level, $desc, $status);
        if ($stmt->execute()) { header("Location: org_management.php?tab=positions&swal=added&name=".urlencode($title)); exit; }
        else $err = $stmt->error;
    }
}

// ── EDIT POSITION ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_position') {
    $id      = (int)$_POST['pos_id'];
    $title   = trim($_POST['title'] ?? '');
    $level   = in_array($_POST['level'] ?? '', ['Teaching','Non-Teaching','Administrative','Managerial']) ? $_POST['level'] : 'Teaching';
    $desc    = trim($_POST['description'] ?? '') ?: null;
    $status  = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

    if (!$title) { $err = 'Position title is required.'; }
    else {
        $s = $conn->prepare("UPDATE positions SET title=?,level=?,description=?,status=? WHERE id=?");
        $s->bind_param('ssssi', $title, $level, $desc, $status, $id);
        $s->execute();
        header("Location: org_management.php?tab=positions&swal=updated&name=".urlencode($title)); exit;
    }
}

// ── DELETE POSITION ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_position') {
    $id = (int)$_POST['pos_id'];
    $conn->query("DELETE FROM positions WHERE id=$id");
    header("Location: org_management.php?tab=positions&swal=deleted&what=Position"); exit;
}

$departments    = fetchDepartments($conn);
$positions      = fetchPositions($conn);
$personnel_list = fetchPersonnelList($conn);
$active_tab     = ($_GET['tab'] ?? '') === 'positions' ? 'positions' : 'departments';

// Stats
$active_depts = count(array_filter($departments, fn($d) => $d['status'] === 'active'));
$active_pos   = count(array_filter($positions,   fn($p) => $p['status'] === 'active'));

// ── ID NOTIFICATIONS for dashboard ────────────────────────
$id_notifs = [];
$conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    type ENUM('id_updated','expiring_soon','expired') NOT NULL,
    id_kind ENUM('prc','other') NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
$notif_result = $conn->query(
    "SELECT n.*, p.full_name, p.subject
     FROM admin_notifications n
     JOIN personnels p ON p.id = n.personnel_id
     WHERE n.type IN ('expired','expiring_soon')
     ORDER BY n.type ASC, n.created_at DESC
     LIMIT 20"
);
if ($notif_result) {
    while ($row = $notif_result->fetch_assoc()) $id_notifs[] = $row;
}
$notif_unread = count(array_filter($id_notifs, fn($n) => !$n['is_read']));


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Organizational Management — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f2f2;--surface:#ffffff;--card:#ffffff;--border:#f0d4d4;
  --accent:#c8102e;--accent2:#9b0c22;--text:#1a0608;--muted:#9c7b7e;
  --success:#059669;--danger:#c8102e;--warn:#d97706;--purple:#7b1fa2;
  --sidebar:260px;--red: #c8102e; --red-dark:  #9b0c22;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex}

 /* ── SIDEBAR ─────────────────────────────────────────── */
  .sidebar { width: var(--sidebar); background: linear-gradient(135deg, var(--red), var(--red-dark));  display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; box-shadow: 4px 0 20px rgba(155,12,34,0.15); }
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

/* MAIN */
.main{margin-left:var(--sidebar);flex:1;min-width:0}
.topbar{padding:20px 32px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--surface);position:sticky;top:0;z-index:50}
.topbar h1{font-size:18px;font-weight:700}
.topbar p{color:var(--muted);font-size:12px;margin-top:2px}
.page{padding:28px 32px}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none}
.btn-primary{background:var(--accent);color:white}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(155,12,34,.35)}
.btn-ghost{background:var(--surface);color:var(--text);border:1px solid var(--border)}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}

/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.red::before{background:linear-gradient(90deg,var(--accent2),var(--accent))}
.stat-card.green::before{background:linear-gradient(90deg,#047857,var(--success))}
.stat-card.warn::before{background:linear-gradient(90deg,#b45309,var(--warn))}
.stat-card.purple::before{background:linear-gradient(90deg,#6d28d9,var(--purple))}
.stat-icon{font-size:22px;margin-bottom:8px}
.stat-num{font-size:28px;font-weight:700;font-family:'JetBrains Mono',monospace;line-height:1}
.stat-label{color:var(--muted);font-size:11px;margin-top:4px}

/* TABS */
.tab-bar{display:flex;gap:4px;margin-bottom:24px;background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:5px;width:fit-content}
.tab-pill{padding:9px 20px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;color:var(--muted);background:none;border:none;font-family:'DM Sans',sans-serif;transition:all .2s;display:flex;align-items:center;gap:7px}
.tab-pill:hover{color:var(--text)}
.tab-pill.active{background:var(--accent);color:white;box-shadow:0 4px 14px rgba(200,16,46,.3)}
.tab-count{background:rgba(200,16,46,.12);color:var(--accent);border-radius:20px;font-size:10px;font-family:'JetBrains Mono',monospace;padding:1px 7px}
.tab-pill.active .tab-count{background:rgba(255,255,255,.25);color:white}

/* TABLE */
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.section-header h2{font-size:15px;font-weight:700}
.section-header p{color:var(--muted);font-size:12px;margin-top:2px}
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:visible;box-shadow:0 2px 12px rgba(155,12,34,.06)}
table{width:100%;border-collapse:collapse}
thead{background:linear-gradient(135deg,#fdf0f0,#fff8f8)}
thead tr{border-bottom:2px solid var(--border)}
th{padding:14px 16px;text-align:left;font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1.5px;color:var(--muted);white-space:nowrap}
th:first-child{border-radius:16px 0 0 0;padding-left:20px}
th:last-child{border-radius:0 16px 0 0}
td{padding:14px 16px;border-top:1px solid var(--border);font-size:13px;vertical-align:middle}
td:first-child{padding-left:20px}
td:last-child{padding-right:20px;overflow:visible}
tr{position:relative}
tr:hover td{background:rgba(200,16,46,.02)}
tr:hover td:first-child{border-left:3px solid var(--accent);padding-left:17px}
.empty-row td{text-align:center;color:var(--muted);padding:52px 20px}
.empty-icon{font-size:36px;display:block;margin-bottom:8px}

/* NAME CELL */
.name-cell{display:flex;align-items:center;gap:12px}
.name-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;font-weight:700;color:white}
.ni-dept{background:linear-gradient(135deg,var(--accent2),var(--accent))}
.ni-pos{background:linear-gradient(135deg,#d97706,#ef4444)}
.name-info strong{display:block;font-size:13px;font-weight:600}
.name-info span{font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace}

/* BADGES */
.badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;font-family:'JetBrains Mono',monospace}
.badge-active{background:rgba(5,150,105,.12);color:var(--success)}
.badge-inactive{background:rgba(200,16,46,.08);color:var(--danger)}
.badge-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.code-chip{font-family:'JetBrains Mono',monospace;font-size:11px;background:rgba(200,16,46,.08);color:var(--accent);padding:2px 8px;border-radius:5px;font-weight:600}
.count-chip{display:inline-flex;align-items:center;gap:4px;background:rgba(200,16,46,.1);color:var(--accent);border-radius:20px;font-size:11px;font-family:'JetBrains Mono',monospace;padding:2px 10px;font-weight:600}

.level-Teaching{background:rgba(200,16,46,.1);color:var(--accent);border-radius:6px;padding:2px 9px;font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:600}
.level-Non-Teaching{background:rgba(217,119,6,.1);color:var(--warn);border-radius:6px;padding:2px 9px;font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:600}
.level-Administrative{background:rgba(123,31,162,.1);color:var(--purple);border-radius:6px;padding:2px 9px;font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:600}
.level-Managerial{background:rgba(5,150,105,.1);color:var(--success);border-radius:6px;padding:2px 9px;font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:600}

/* ACTION DROPDOWN */
.action-wrap{position:relative;display:inline-block}
.action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 10px;background:transparent;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer;color:var(--muted);transition:all .2s}
.action-btn:hover{border-color:var(--accent);color:var(--accent);background:rgba(200,16,46,.04)}
.chevron{font-size:9px;transition:transform .2s}
.action-dropdown{position:fixed;background:var(--card);border:1.5px solid var(--border);border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.10);min-width:148px;z-index:9999;opacity:0;visibility:hidden;transform:translateY(-4px);transition:all .18s ease;overflow:hidden}
.action-dropdown.open{opacity:1;visibility:visible;transform:translateY(0)}
.action-dropdown-item{display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:12.5px;font-weight:500;cursor:pointer;transition:background .12s;border:none;background:none;width:100%;text-align:left;font-family:'DM Sans',sans-serif;color:var(--text)}
.action-dropdown-item:hover{background:var(--bg)}
.action-dropdown-item.item-edit:hover{color:var(--warn)}
.action-dropdown-item.item-delete{color:var(--danger)}
.action-dropdown-item.item-delete:hover{background:rgba(200,16,46,.06)}
.action-dropdown-divider{height:1px;background:var(--border);margin:3px 10px}

/* MODAL */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.78);z-index:1000;align-items:flex-start;justify-content:center;backdrop-filter:blur(5px);padding:24px 20px;overflow-y:auto}
.modal-overlay.open{display:flex}
@keyframes slideIn{from{opacity:0;transform:translateY(-20px)}to{opacity:1;transform:translateY(0)}}
.modal-box{background:var(--card);border:1px solid var(--border);border-radius:20px;padding:32px;width:100%;max-width:520px;animation:slideIn .3s ease;margin:auto}
.modal-box h2{font-size:18px;font-weight:700;margin-bottom:4px}
.modal-sub{color:var(--muted);font-size:12px;margin-bottom:24px;font-family:'JetBrains Mono',monospace}
.modal-footer{display:flex;gap:10px;justify-content:flex-end;margin-top:22px;padding-top:18px;border-top:1px solid var(--border)}

/* FORM */
.fg{display:flex;flex-direction:column;margin-bottom:14px}
.fg label{font-size:10px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:1px;color:var(--muted);margin-bottom:6px}
.req{color:var(--danger);margin-left:2px}
.fg input,.fg select,.fg textarea{width:100%;padding:10px 13px;background:var(--surface);border:1.5px solid var(--border);border-radius:9px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:13px;outline:none;transition:all .2s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(200,16,46,.1)}
.fg textarea{resize:vertical;min-height:70px}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* ALERT */
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.alert-danger{background:rgba(255,107,107,.1);border:1px solid rgba(200,16,46,.3);color:var(--danger)}

.swal2-popup{font-family:'DM Sans',sans-serif!important;border-radius:18px!important}
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
    <a class="nav-item" href="manage_personnel.php"><span class="ni">👩‍🏫</span> Manage Personnel</a>
    <a class="nav-item" href="org_management.php"><span class="ni">🏛️</span> Org Management</a>
    <a class="nav-item" href="documents_library.php"><span class="ni">📁</span> Documents</a>
    <div class="nav-label" style="margin-top:8px">Requests & Alerts</div>
    <a class="nav-item" href="admin_my_requests.php">
      <span class="ni">📋</span> My Requests
      <?php
        // Unread request notifications badge
        $reqNotifRes = $conn->query("SELECT COUNT(*) AS c FROM admin_request_notifications WHERE admin_user_id = {$_SESSION['user_id']} AND is_read = 0");
        $reqNotifCount = $reqNotifRes ? (int)$reqNotifRes->fetch_assoc()['c'] : 0;
        if ($reqNotifCount > 0): ?>
        <span class="nav-badge"><?= $reqNotifCount ?></span>
      <?php endif; ?>
    </a>
      <a class="nav-item" href="admin_notifications.php">
          <span class="ni">🔔</span> ID Notifications
          <?php if ($notif_unread > 0): ?>
              <span class="nav-badge <?= $notif_unread>0?'pulse':'' ?>"><?= $notif_unread ?></span>
          <?php endif; ?>
      </a>
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
      <h1>Organizational Management</h1>
      <p>Manage departments and positions / designations</p>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add New</button>
  </div>

  <div class="page">
    <?php if ($err): ?>
      <div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card red">
        <div class="stat-icon">🏢</div>
        <div class="stat-num"><?= count($departments) ?></div>
        <div class="stat-label">Total Departments</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-num"><?= $active_depts ?></div>
        <div class="stat-label">Active Departments</div>
      </div>
      <div class="stat-card warn">
        <div class="stat-icon">📌</div>
        <div class="stat-num"><?= count($positions) ?></div>
        <div class="stat-label">Total Positions</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon">✅</div>
        <div class="stat-num"><?= $active_pos ?></div>
        <div class="stat-label">Active Positions</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
      <button class="tab-pill <?= $active_tab === 'departments' ? 'active' : '' ?>" onclick="switchTab('departments')">
        🏢 Departments <span class="tab-count"><?= count($departments) ?></span>
      </button>
      <button class="tab-pill <?= $active_tab === 'positions' ? 'active' : '' ?>" onclick="switchTab('positions')">
        📌 Positions <span class="tab-count"><?= count($positions) ?></span>
      </button>
    </div>

    <!-- DEPARTMENTS TABLE -->
    <div id="tabDepartments" <?= $active_tab !== 'departments' ? 'style="display:none"' : '' ?>>
      <div class="section-header">
        <div><h2>Departments</h2><p>All school departments</p></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>🏢 Department</th>
              <th>🔖 Code</th>
              <th>👤 Department Head</th>
              <th>👥 Personnel</th>
              <th>🏷 Status</th>
              <th style="text-align:right;padding-right:20px">⚙️ Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($departments)): ?>
            <tr class="empty-row"><td colspan="6"><span class="empty-icon">🏢</span>No departments yet. Click "+ Add New" to create one.</td></tr>
          <?php else: foreach ($departments as $dept): ?>
            <tr>
              <td>
                <div class="name-cell">
                  <div class="name-icon ni-dept"><?= mb_strtoupper(mb_substr($dept['name'],0,1)) ?></div>
                  <div class="name-info">
                    <strong><?= htmlspecialchars($dept['name']) ?></strong>
                    <?php if ($dept['description']): ?>
                      <span><?= htmlspecialchars(mb_strimwidth($dept['description'],0,48,'…')) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?= $dept['code'] ? '<span class="code-chip">'.htmlspecialchars($dept['code']).'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($dept['head_name'] ?? '—') ?></td>
              <td><?= $dept['personnel_count'] > 0 ? '<span class="count-chip">👥 '.$dept['personnel_count'].'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
              <td>
                <span class="badge badge-<?= $dept['status'] ?>">
                  <span class="badge-dot" style="background:<?= $dept['status']==='active'?'var(--success)':'var(--danger)' ?>"></span>
                  <?= ucfirst($dept['status']) ?>
                </span>
              </td>
              <td style="text-align:right;padding-right:20px">
                <div class="action-wrap">
                  <button class="action-btn" onclick="toggleDropdown(this)">Actions <span class="chevron">▾</span></button>
                  <div class="action-dropdown">
                    <button class="action-dropdown-item item-edit" onclick='openEditDept(<?= htmlspecialchars(json_encode($dept),ENT_QUOTES) ?>)'>
                      <span>✏️</span> Edit
                    </button>
                    <div class="action-dropdown-divider"></div>
                    <button class="action-dropdown-item item-delete" onclick="confirmDeleteDept(<?= $dept['id'] ?>,'<?= addslashes(htmlspecialchars($dept['name'])) ?>',<?= (int)$dept['personnel_count'] ?>)">
                      <span>🗑️</span> Remove
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- POSITIONS TABLE -->
    <div id="tabPositions" <?= $active_tab !== 'positions' ? 'style="display:none"' : '' ?>>
      <div class="section-header">
        <div><h2>Positions / Designations</h2><p>All teaching and non-teaching positions</p></div>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>📌 Position / Designation</th>
              <th>🎓 Level</th>
              <th>👥 Personnel</th>
              <th>🏷 Status</th>
              <th style="text-align:right;padding-right:20px">⚙️ Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($positions)): ?>
            <tr class="empty-row"><td colspan="6"><span class="empty-icon">📌</span>No positions yet. Click "+ Add New" to create one.</td></tr>
          <?php else: foreach ($positions as $pos): ?>
            <tr>
              <td>
                <div class="name-cell">
                  <div class="name-icon ni-pos"><?= mb_strtoupper(mb_substr($pos['title'],0,1)) ?></div>
                  <div class="name-info">
                    <strong><?= htmlspecialchars($pos['title']) ?></strong>
                    <?php if ($pos['description']): ?>
                      <span><?= htmlspecialchars(mb_strimwidth($pos['description'],0,48,'…')) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><span class="level-<?= htmlspecialchars($pos['level']) ?>"><?= htmlspecialchars($pos['level']) ?></span></td>
              <td><?= $pos['personnel_count'] > 0 ? '<span class="count-chip">👥 '.$pos['personnel_count'].'</span>' : '<span style="color:var(--muted)">—</span>' ?></td>
              <td>
                <span class="badge badge-<?= $pos['status'] ?>">
                  <span class="badge-dot" style="background:<?= $pos['status']==='active'?'var(--success)':'var(--danger)' ?>"></span>
                  <?= ucfirst($pos['status']) ?>
                </span>
              </td>
              <td style="text-align:right;padding-right:20px">
                <div class="action-wrap">
                  <button class="action-btn" onclick="toggleDropdown(this)">Actions <span class="chevron">▾</span></button>
                  <div class="action-dropdown">
                    <button class="action-dropdown-item item-edit" onclick='openEditPos(<?= htmlspecialchars(json_encode($pos),ENT_QUOTES) ?>)'>
                      <span>✏️</span> Edit
                    </button>
                    <div class="action-dropdown-divider"></div>
                    <button class="action-dropdown-item item-delete" onclick="confirmDeletePos(<?= $pos['id'] ?>,'<?= addslashes(htmlspecialchars($pos['title'])) ?>',<?= (int)$pos['personnel_count'] ?>)">
                      <span>🗑️</span> Remove
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /page -->
</main>

<!-- ══ DEPARTMENT MODAL ══════════════════════════════════════════════ -->
<div class="modal-overlay" id="deptModal">
  <div class="modal-box">
    <h2 id="deptModalTitle">🏢 Add Department</h2>
    <p class="modal-sub" id="deptModalSub">Fill in the department details.</p>
    <form method="POST" action="org_management.php">
      <input type="hidden" name="action" id="deptAction" value="add_department">
      <input type="hidden" name="dept_id" id="deptId">
      <div class="fg">
        <label>Department Name <span class="req">*</span></label>
        <input type="text" name="name" id="deptName" placeholder="e.g. Science &amp; Math" required>
      </div>
      <div class="form-row-2">
        <div class="fg">
          <label>Short Code</label>
          <input type="text" name="code" id="deptCode" placeholder="e.g. SCI" maxlength="20">
        </div>
        <div class="fg">
          <label>Status</label>
          <select name="status" id="deptStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="fg">
        <label>Department Head</label>
        <select name="head_personnel_id" id="deptHead">
          <option value="">— None —</option>
          <?php foreach ($personnel_list as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['full_name']) ?><?= $p['subject'] ? ' — '.htmlspecialchars($p['subject']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg">
        <label>Description</label>
        <textarea name="description" id="deptDesc" placeholder="Optional description…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('deptModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="deptSubmitBtn">💾 Add Department</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ POSITION MODAL ════════════════════════════════════════════════ -->
<div class="modal-overlay" id="posModal">
  <div class="modal-box">
    <h2 id="posModalTitle">📌 Add Position</h2>
    <p class="modal-sub" id="posModalSub">Fill in the position details.</p>
    <form method="POST" action="org_management.php">
      <input type="hidden" name="action" id="posAction" value="add_position">
      <input type="hidden" name="pos_id" id="posId">
      <div class="fg">
        <label>Position / Designation Title <span class="req">*</span></label>
        <input type="text" name="title" id="posTitle" placeholder="e.g. Teacher III" required>
      </div>
      <div class="form-row-2">
        <div class="fg">
          <label>Level</label>
          <select name="level" id="posLevel">
            <option value="Teaching">Teaching</option>
            <option value="Non-Teaching">Non-Teaching</option>
            <option value="Administrative">Administrative</option>
            <option value="Managerial">Managerial</option>
          </select>
        </div>
        <div class="fg">
          <label>Status</label>
          <select name="status" id="posStatus">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
      <div class="fg">
        <label>Description</label>
        <textarea name="description" id="posDesc" placeholder="Optional description…"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('posModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="posSubmitBtn">💾 Add Position</button>
      </div>
    </form>
  </div>
</div>

<script>
/* ── TAB SWITCHING ─────────────────────────────────────────────── */
let currentTab = '<?= $active_tab ?>';

function switchTab(tab) {
  currentTab = tab;
  document.getElementById('tabDepartments').style.display = tab === 'departments' ? '' : 'none';
  document.getElementById('tabPositions').style.display   = tab === 'positions'   ? '' : 'none';
  document.querySelectorAll('.tab-pill').forEach((p, i) => {
    p.classList.toggle('active', (i===0 && tab==='departments') || (i===1 && tab==='positions'));
  });
  history.replaceState(null, '', '?tab=' + tab);
}

function openAddModal() {
  currentTab === 'departments' ? openAddDept() : openAddPos();
}

/* ── DROPDOWN ──────────────────────────────────────────────────── */
let openDropdown = null;

function toggleDropdown(btn) {
  const wrap = btn.closest('.action-wrap');
  const dd   = wrap.querySelector('.action-dropdown');

  if (openDropdown && openDropdown !== dd) {
    openDropdown.classList.remove('open');
  }

  if (dd.classList.contains('open')) {
    dd.classList.remove('open');
    openDropdown = null;
    return;
  }

  const rect = btn.getBoundingClientRect();
  dd.style.top  = (rect.bottom + 5) + 'px';
  dd.style.left = (rect.right - 148) + 'px';
  dd.classList.add('open');
  openDropdown = dd;
}

document.addEventListener('click', e => {
  if (!e.target.closest('.action-wrap') && openDropdown) {
    openDropdown.classList.remove('open');
    openDropdown = null;
  }
});

/* ── MODAL HELPERS ─────────────────────────────────────────────── */
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

/* ── DEPARTMENT MODALS ─────────────────────────────────────────── */
function openAddDept() {
  document.getElementById('deptModalTitle').textContent = '🏢 Add Department';
  document.getElementById('deptModalSub').textContent   = 'Fill in the department details.';
  document.getElementById('deptAction').value   = 'add_department';
  document.getElementById('deptId').value       = '';
  document.getElementById('deptName').value     = '';
  document.getElementById('deptCode').value     = '';
  document.getElementById('deptDesc').value     = '';
  document.getElementById('deptHead').value     = '';
  document.getElementById('deptStatus').value   = 'active';
  document.getElementById('deptSubmitBtn').textContent = '💾 Add Department';
  document.getElementById('deptModal').classList.add('open');
}

function openEditDept(d) {
  document.getElementById('deptModalTitle').textContent = '✏️ Edit Department';
  document.getElementById('deptModalSub').textContent   = 'Update department details.';
  document.getElementById('deptAction').value   = 'edit_department';
  document.getElementById('deptId').value       = d.id;
  document.getElementById('deptName').value     = d.name || '';
  document.getElementById('deptCode').value     = d.code || '';
  document.getElementById('deptDesc').value     = d.description || '';
  document.getElementById('deptHead').value     = d.head_personnel_id || '';
  document.getElementById('deptStatus').value   = d.status || 'active';
  document.getElementById('deptSubmitBtn').textContent = '💾 Save Changes';
  document.getElementById('deptModal').classList.add('open');
}

function confirmDeleteDept(id, name, count) {
  const warn = count > 0 ? `<br><span style="color:var(--warn);font-size:12px">⚠ ${count} active personnel are in this department.</span>` : '';
  Swal.fire({
    icon:'warning', title:'Remove Department?',
    html:`Are you sure you want to remove <strong>${name}</strong>?${warn}<br><span style="font-size:12px;color:#9c7b7e">This cannot be undone.</span>`,
    showCancelButton:true, confirmButtonText:'🗑️ Yes, Remove', cancelButtonText:'Cancel',
    confirmButtonColor:'#c8102e', cancelButtonColor:'#9c7b7e', reverseButtons:true,
    customClass:{popup:'swal2-popup'}
  }).then(r => {
    if (r.isConfirmed) {
      const f = document.createElement('form');
      f.method='POST'; f.action='org_management.php';
      f.innerHTML=`<input type="hidden" name="action" value="delete_department"><input type="hidden" name="dept_id" value="${id}">`;
      document.body.appendChild(f); f.submit();
    }
  });
}

/* ── POSITION MODALS ───────────────────────────────────────────── */
function openAddPos() {
  document.getElementById('posModalTitle').textContent = '📌 Add Position';
  document.getElementById('posModalSub').textContent   = 'Fill in the position details.';
  document.getElementById('posAction').value  = 'add_position';
  document.getElementById('posId').value      = '';
  document.getElementById('posTitle').value   = '';
  document.getElementById('posLevel').value   = 'Teaching';
  document.getElementById('posDesc').value    = '';
  document.getElementById('posStatus').value  = 'active';
  document.getElementById('posSubmitBtn').textContent = '💾 Add Position';
  document.getElementById('posModal').classList.add('open');
}

function openEditPos(p) {
  document.getElementById('posModalTitle').textContent = '✏️ Edit Position';
  document.getElementById('posModalSub').textContent   = 'Update position details.';
  document.getElementById('posAction').value  = 'edit_position';
  document.getElementById('posId').value      = p.id;
  document.getElementById('posTitle').value   = p.title || '';
  document.getElementById('posLevel').value   = p.level || 'Teaching';
  document.getElementById('posDesc').value    = p.description || '';
  document.getElementById('posStatus').value  = p.status || 'active';
  document.getElementById('posSubmitBtn').textContent = '💾 Save Changes';
  document.getElementById('posModal').classList.add('open');
}

function confirmDeletePos(id, name, count) {
  const warn = count > 0 ? `<br><span style="color:var(--warn);font-size:12px">⚠ ${count} personnel currently hold this position.</span>` : '';
  Swal.fire({
    icon:'warning', title:'Remove Position?',
    html:`Are you sure you want to remove <strong>${name}</strong>?${warn}<br><span style="font-size:12px;color:#9c7b7e">This cannot be undone.</span>`,
    showCancelButton:true, confirmButtonText:'🗑️ Yes, Remove', cancelButtonText:'Cancel',
    confirmButtonColor:'#c8102e', cancelButtonColor:'#9c7b7e', reverseButtons:true,
    customClass:{popup:'swal2-popup'}
  }).then(r => {
    if (r.isConfirmed) {
      const f = document.createElement('form');
      f.method='POST'; f.action='org_management.php';
      f.innerHTML=`<input type="hidden" name="action" value="delete_position"><input type="hidden" name="pos_id" value="${id}">`;
      document.body.appendChild(f); f.submit();
    }
  });
}

/* ── SWEETALERT TRIGGERS ───────────────────────────────────────── */
<?php
$swal_type = ''; $swal_title = ''; $swal_text = '';
if (isset($_GET['swal'])) {
  $name = htmlspecialchars($_GET['name'] ?? $_GET['what'] ?? '');
  switch ($_GET['swal']) {
    case 'added':   $swal_type='success'; $swal_title='Added!';   $swal_text="\"$name\" was added successfully."; break;
    case 'updated': $swal_type='success'; $swal_title='Updated!'; $swal_text="\"$name\" was updated successfully."; break;
    case 'deleted': $swal_type='success'; $swal_title='Removed!'; $swal_text=($name?:"Record")." was removed successfully."; break;
  }
}
if ($swal_type):
?>
Swal.fire({
  icon: '<?= $swal_type ?>',
  title: '<?= $swal_title ?>',
  text: '<?= addslashes($swal_text) ?>',
  confirmButtonColor: '#c8102e',
  timer: 3500,
  timerProgressBar: true,
  customClass: { popup: 'swal2-popup' }
});
<?php endif; ?>
</script>
</body>
</html>