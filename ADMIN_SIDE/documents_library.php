<?php
// documents_library.php — DOCUMENTS LIBRARY
require_once 'admin_shared.php';
require_once '../helpers/ActionRequest.php';
$ar = new ActionRequest($conn);

// Is the current user a super_admin? They bypass approval.
$isSuperAdmin = ($_SESSION['role'] === 'super_admin');

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
<title>Documents Library — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
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
  --red:     #c8102e;
  --red-dark:#9b0c22;
  --sidebar: 260px;
}
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

/* ── SIDEBAR ─────────────────────────────────────────── */
.sidebar { width: var(--sidebar);background: linear-gradient(135deg, var(--red), var(--red-dark));  display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; box-shadow: 4px 0 20px rgba(155,12,34,0.15); }
.sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.12); display: flex; flex-direction: column; align-items: center; gap: 12px; background: transparent; }
.sidebar-logo-img-wrap { width: 76px; height: 76px; border-radius: 50%; background: #fff; padding: 6px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 18px rgba(0,0,0,0.3), 0 0 0 3px rgba(255,255,255,0.18); flex-shrink: 0; }
.sidebar-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
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

.main { margin-left: var(--sidebar); flex: 1; }
.topbar { padding: 20px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--surface); position: sticky; top: 0; z-index: 50; }
.topbar h1 { font-size: 18px; font-weight: 700; }
.topbar p { color: var(--muted); font-size: 12px; margin-top: 2px; }
.page { padding: 28px 32px; }

.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.btn-download { background: rgba(5,150,105,0.15); color: var(--success); border: 1px solid rgba(5,150,105,0.3); }
.btn-download:hover { background: rgba(5,150,105,0.25); }
.btn-view { background: rgba(200,16,46,0.12); color: var(--accent); border: 1px solid rgba(200,16,46,0.3); }
.btn-view:hover { background: rgba(200,16,46,0.2); }
.btn-purple { background: rgba(167,139,250,0.12); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }
.btn-purple:hover { background: rgba(167,139,250,0.2); }
/* Approval-pending state */
.btn-pending { background: rgba(217,119,6,0.12); color: var(--warn); border: 1px solid rgba(217,119,6,0.3); cursor: not-allowed; }

.folder-topbar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); }
.bc-link { color: var(--accent); cursor: pointer; font-weight: 600; }
.bc-link:hover { opacity: 0.75; }
.bc-current { color: var(--text); font-weight: 600; }

.folder-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
.folder-tab { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid var(--border); color: var(--muted); background: none; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
.folder-tab:hover { border-color: var(--accent); color: var(--text); }
.folder-tab.active { background: rgba(200,16,46,0.1); border-color: var(--accent); color: var(--accent); }
.folder-tab.tab-purple.active { background: rgba(167,139,250,0.1); border-color: var(--purple); color: var(--purple); }
.tab-count { font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 1px 7px; border-radius: 20px; }
.folder-tab.active .tab-count    { background: rgba(200,16,46,0.2); }
.folder-tab.tab-purple.active .tab-count { background: rgba(167,139,250,0.2); }

.folder-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(200px,1fr)); gap: 16px; }
.folder-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 22px 20px 18px; cursor: pointer; transition: all 0.22s; position: relative; overflow: hidden; }
.folder-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--accent2),var(--accent)); opacity:0; transition:opacity 0.22s; }
.folder-card:hover { border-color: var(--accent); transform: translateY(-3px); box-shadow: 0 12px 30px rgba(200,16,46,0.12); }
.folder-card:hover::before { opacity: 1; }
.folder-card.leave-folder::before { background: linear-gradient(90deg,#7c3aed,var(--purple)); }
.folder-card.leave-folder:hover { border-color: var(--purple); box-shadow: 0 12px 30px rgba(167,139,250,0.12); }
.folder-icon { font-size: 38px; margin-bottom: 12px; display: block; transition: transform 0.22s; }
.folder-card:hover .folder-icon { transform: scale(1.1); }
.folder-name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
.folder-meta { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.folder-badge { position: absolute; top: 14px; right: 14px; background: rgba(200,16,46,0.15); color: var(--accent); border-radius: 20px; font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 2px 9px; font-weight: 600; }
.folder-badge.empty { background: rgba(136,146,176,0.1); color: var(--muted); }
.folder-badge.purple { background: rgba(167,139,250,0.15); color: var(--purple); }

.folder-contents { display: none; }
.folder-contents.active { display: block; }
.folder-view-header { display: flex; align-items: center; gap: 14px; padding: 20px 24px; background: var(--surface); border-bottom: 1px solid var(--border); border-radius: 14px 14px 0 0; }
.fv-avatar { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white; flex-shrink: 0; }
.fv-info h3 { font-size: 16px; font-weight: 700; margin-bottom: 2px; }
.fv-info p  { color: var(--muted); font-size: 12px; }
.fv-back { margin-left: auto; background: var(--card); border: 1px solid var(--border); border-radius: 9px; padding: 8px 16px; font-family: 'DM Sans', sans-serif; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
.fv-back:hover { border-color: var(--accent); color: var(--accent); }

.inner-tab-bar { display: flex; gap: 0; background: var(--card); border: 1px solid var(--border); border-top: none; border-bottom: 1px solid var(--border); }
.inner-tab { padding: 12px 20px; font-size: 13px; font-weight: 600; cursor: pointer; color: var(--muted); border-bottom: 2px solid transparent; transition: all 0.2s; display: flex; align-items: center; gap: 6px; background: none; border-left: none; border-right: none; border-top: none; }
.inner-tab:hover { color: var(--text); background: rgba(200,16,46,0.03); }
.inner-tab.active { color: var(--accent); border-bottom-color: var(--accent); }
.inner-tab.purple.active { color: var(--purple); border-bottom-color: var(--purple); }
.inner-tab .it-count { font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 1px 7px; border-radius: 20px; background: rgba(200,16,46,0.06); }

.file-list-wrap { background: var(--card); border: 1px solid var(--border); border-top: none; border-radius: 0 0 14px 14px; overflow: hidden; }
.file-row { display: flex; align-items: center; gap: 14px; padding: 14px 24px; border-top: 1px solid var(--border); transition: background 0.15s; }
.file-row:first-child { border-top: none; }
.file-row:hover { background: rgba(200,16,46,0.04); }
.file-row-icon { font-size: 24px; flex-shrink: 0; }
.file-row-info { flex: 1; min-width: 0; }
.file-row-info strong { display: block; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.file-row-info span { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.file-row-actions { display: flex; gap: 8px; flex-shrink: 0; }
.empty-folder { text-align: center; padding: 56px 20px; color: var(--muted); }
.empty-folder .ef-icon { font-size: 40px; margin-bottom: 10px; display: block; }
.empty-folder p { font-size: 13px; }

.leave-file-row { display: flex; align-items: center; gap: 14px; padding: 14px 24px; border-top: 1px solid var(--border); transition: background 0.15s; }
.leave-file-row:first-child { border-top: none; }
.leave-file-row:hover { background: rgba(167,139,250,0.04); }

.leave-folder-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; padding: 16px 24px; background: var(--card); border: 1px solid var(--border); border-top: none; }
.lfs-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; }
.lfs-num   { font-size: 22px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.lfs-label { font-size: 10px; color: var(--muted); margin-top: 3px; }

.leave-type-badge { padding: 2px 10px; border-radius: 20px; font-size: 10px; font-family: 'JetBrains Mono', monospace; }
.leave-sick      { background: rgba(239,68,68,0.15); color: #f87171; }
.leave-vacation  { background: rgba(200,16,46,0.15); color: var(--accent); }
.leave-emergency { background: rgba(245,158,11,0.15); color: var(--warn); }
.leave-maternity { background: rgba(167,139,250,0.15); color: var(--purple); }
.leave-other     { background: rgba(136,146,176,0.15); color: var(--muted); }
.date-chip { padding: 2px 8px; background: rgba(167,139,250,0.12); color: var(--purple); border-radius: 5px; font-size: 10px; font-family: 'JetBrains Mono', monospace; }

.folder-search-wrap { position: relative; margin-bottom: 16px; }
.folder-search-wrap .fs-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 14px; pointer-events: none; opacity: 0.4; }
.folder-search-input { width: 100%; padding: 10px 16px 10px 40px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13px; outline: none; transition: border-color 0.2s; }
.folder-search-input::placeholder { color: var(--muted); }
.folder-search-input:focus { border-color: var(--accent); }

/* ── APPROVAL NOTICE BANNER ──────────────────────────── */
.approval-notice {
  display: flex; align-items: center; gap: 12px;
  background: #fff7ed; border: 1.5px solid #fddcb0;
  border-radius: 12px; padding: 14px 18px; margin-bottom: 20px;
  font-size: 13px; color: #92400e;
}
.approval-notice .an-icon { font-size: 20px; flex-shrink: 0; }
.approval-notice strong { display: block; font-weight: 600; margin-bottom: 2px; }
.approval-notice span { font-size: 12px; opacity: 0.8; }

/* ── CUSTOM DOWNLOAD APPROVAL MODAL ──────────────────── */
.dl-modal-overlay {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(10,2,4,0.55);
  backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity 0.25s ease;
}
.dl-modal-overlay.open {
  opacity: 1; pointer-events: all;
}
.dl-modal {
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 32px 80px rgba(155,12,34,0.22), 0 0 0 1px rgba(200,16,46,0.08);
  padding: 0;
  width: min(440px, 92vw);
  transform: translateY(18px) scale(0.97);
  transition: transform 0.28s cubic-bezier(.34,1.56,.64,1);
  overflow: hidden;
}
.dl-modal-overlay.open .dl-modal {
  transform: translateY(0) scale(1);
}
.dl-modal-top {
  background: linear-gradient(135deg, #c8102e 0%, #9b0c22 100%);
  padding: 28px 28px 22px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  position: relative;
}
.dl-modal-top::after {
  content: '';
  position: absolute; bottom: -1px; left: 0; right: 0; height: 20px;
  background: #fff;
  border-radius: 20px 20px 0 0;
}
.dl-modal-lock-ring {
  width: 64px; height: 64px;
  background: rgba(255,255,255,0.15);
  border: 2px solid rgba(255,255,255,0.3);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px;
  animation: dl-pulse 2.2s ease-in-out infinite;
}
@keyframes dl-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(255,255,255,0.25); }
  50%      { box-shadow: 0 0 0 10px rgba(255,255,255,0); }
}
.dl-modal-top-title {
  color: #fff; font-size: 16px; font-weight: 700;
  font-family: 'DM Serif Display', serif;
  letter-spacing: 0.01em; position: relative; z-index: 1;
}
.dl-modal-body { padding: 22px 28px 24px; }
.dl-modal-label {
  font-size: 11px; font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase; letter-spacing: 1.5px;
  color: var(--muted); margin-bottom: 6px;
}
.dl-modal-filename {
  background: #fdf0f2;
  border: 1px solid rgba(200,16,46,0.15);
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 13px; font-weight: 600;
  color: var(--text);
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 16px;
}
.dl-modal-filename .dmf-icon { font-size: 20px; flex-shrink: 0; }
.dl-modal-info {
  background: #fff7ed;
  border: 1px solid #fddcb0;
  border-radius: 10px;
  padding: 11px 14px;
  font-size: 12px; color: #92400e;
  display: flex; align-items: flex-start; gap: 8px;
  margin-bottom: 22px;
  line-height: 1.5;
}
.dl-modal-info .dmi-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.dl-modal-actions {
  display: flex; gap: 10px;
}
.dl-modal-cancel {
  flex: 1; padding: 12px;
  border-radius: 11px;
  border: 1.5px solid var(--border);
  background: none;
  font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600;
  color: var(--muted); cursor: pointer;
  transition: all 0.18s;
}
.dl-modal-cancel:hover { border-color: #bbb; color: var(--text); }
.dl-modal-confirm {
  flex: 2; padding: 12px;
  border-radius: 11px; border: none;
  background: linear-gradient(135deg, #c8102e, #9b0c22);
  color: #fff;
  font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 700;
  cursor: pointer;
  box-shadow: 0 4px 18px rgba(200,16,46,0.35);
  transition: all 0.18s;
  display: flex; align-items: center; justify-content: center; gap: 6px;
}
.dl-modal-confirm:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(200,16,46,0.45); }
.dl-modal-confirm:active { transform: translateY(0); }

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
      <span><?= $isSuperAdmin ? 'Super Admin Portal' : 'Administrator Portal' ?></span>
    </div>
  </div>
  <nav class="nav">
    <div class="nav-label">Main</div>
    <a class="nav-item" href="admin_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item" href="manage_personnel.php"><span class="ni">👩‍🏫</span> Manage Personnel</a>
    <a class="nav-item" href="org_management.php"><span class="ni">🏛️</span> Org Management</a>
    <a class="nav-item active" href="documents_library.php"><span class="ni">📁</span> Documents</a>
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
        <span style="background:rgba(255,255,255,0.25);color:#fff;border-radius:999px;padding:1px 8px;font-size:10px;font-weight:700;margin-left:auto"><?= $notif_unread ?></span>
      <?php endif; ?>
    </a>
    <?php if ($isSuperAdmin): ?>
    <div class="nav-label" style="margin-top:8px">Super Admin</div>
    <a class="nav-item" href="../SUPER_ADMIN/superadmin_dashboard.php"><span class="ni">🛡️</span> Control Panel</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="avatar"><?= $isSuperAdmin ? '👑' : '🛡️' ?></div>
      <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <small><?= $isSuperAdmin ? 'Super Admin' : 'Administrator' ?></small>
      </div>
      <a href="../logout.php" class="logout-btn" title="Logout" style="font-size:1.5rem">⏏</a>
    </div>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <h1>Documents &amp; Leave Files</h1>
      <p>All documents and leave scan reports</p>
    </div>
  </div>

  <div class="page">

    <?php if (!$isSuperAdmin): ?>
    <!-- Notice shown only to regular admins -->
    <div class="approval-notice">
      <span class="an-icon">🔒</span>
      <div>
        <strong>Download Approval Required</strong>
        <span>Downloading files requires Super Admin approval. After clicking Download, you will be redirected to a waiting page until the Super Admin approves your request.</span>
      </div>
    </div>
    <?php endif; ?>

    <div id="folderGrid">
      <div class="folder-topbar">
        <div class="breadcrumb">
          <span class="bc-current">📁 Documents &amp; Leave Files</span>
        </div>
        <div style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace">
          <?= $total_docs ?> document<?= $total_docs!=1?'s':'' ?> · <?= $total_teachers ?> teacher<?= $total_teachers!=1?'s':'' ?>
        </div>
      </div>

      <div class="folder-tabs">
        <button class="folder-tab active" onclick="switchFolderTab('docs',this)">
          📄 Documents <span class="tab-count"><?= $total_docs ?></span>
        </button>
        <button class="folder-tab tab-purple" onclick="switchFolderTab('leave',this)">
          📋 Leave / Scan Reports <span class="tab-count"><?= array_sum(array_map('count', $leave_by_teacher)) ?></span>
        </button>
      </div>

      <div class="folder-search-wrap">
        <span class="fs-icon">🔍</span>
        <input type="text" class="folder-search-input" id="folderSearch" placeholder="Search teacher folders…" oninput="filterFolders(this.value)">
      </div>

      <div id="docsFolderGrid" class="folder-grid">
        <?php foreach ($teachers_arr as $t):
          $tdocs = count($docs_by_teacher[$t['id']] ?? []);
        ?>
        <div class="folder-card"
             data-folder-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
             onclick="openFolder(<?= $t['id'] ?>, 'docs')">
          <span class="folder-badge <?= $tdocs===0?'empty':'' ?>"><?= $tdocs ?> <?= $tdocs===1?'file':'files' ?></span>
          <span class="folder-icon">📂</span>
          <div class="folder-name"><?= htmlspecialchars($t['full_name']) ?></div>
          <div class="folder-meta"><?= htmlspecialchars($t['subject'] ?: $t['department'] ?: 'Teacher') ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <div id="leaveFolderGrid" class="folder-grid" style="display:none">
        <?php foreach ($teachers_arr as $t):
          $tleave = count($leave_by_teacher[$t['id']] ?? []);
          $tscan  = count(array_filter($leave_by_teacher[$t['id']] ?? [], fn($l) => !empty($l['scan_file_path'])));
        ?>
        <div class="folder-card leave-folder"
             data-folder-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
             onclick="openFolder(<?= $t['id'] ?>, 'leave')">
          <span class="folder-badge purple <?= $tleave===0?'empty':'' ?>"><?= $tleave ?> record<?= $tleave!==1?'s':'' ?></span>
          <span class="folder-icon">📋</span>
          <div class="folder-name"><?= htmlspecialchars($t['full_name']) ?></div>
          <div class="folder-meta"><?= $tscan ?> scan<?= $tscan!==1?'s':'' ?> uploaded</div>
        </div>
        <?php endforeach; ?>
      </div>

      <div id="folderNoResults" style="display:none;text-align:center;padding:48px;color:var(--muted)">
        <div style="font-size:36px;margin-bottom:10px">🔍</div>
        <p style="font-size:13px">No folders match your search.</p>
      </div>
    </div>

    <div id="folderContents" class="folder-contents">
      <div class="folder-view-header">
        <div class="fv-avatar" id="fvAvatar"></div>
        <div class="fv-info">
          <h3 id="fvName"></h3>
          <p id="fvMeta"></p>
        </div>
        <button class="fv-back" onclick="closeFolder()">← Back to Folders</button>
      </div>

      <div class="inner-tab-bar">
        <button class="inner-tab active" id="innerTabDocs" onclick="switchInnerTab('docs')">
          📄 Documents <span class="it-count" id="innerCountDocs">0</span>
        </button>
        <button class="inner-tab purple" id="innerTabLeave" onclick="switchInnerTab('leave')">
          📋 Leave Reports <span class="it-count" id="innerCountLeave">0</span>
        </button>
      </div>

      <div id="leaveStatsBar" style="display:none">
        <div class="leave-folder-stats">
          <div class="lfs-card">
            <div class="lfs-num" style="color:var(--success)" id="lfsAvailable">—</div>
            <div class="lfs-label">🏖 Leave Days Available</div>
          </div>
          <div class="lfs-card">
            <div class="lfs-num" style="color:var(--danger)" id="lfsAbsent">—</div>
            <div class="lfs-label">📅 Total Days Absent</div>
          </div>
          <div class="lfs-card">
            <div class="lfs-num" style="color:var(--purple)" id="lfsRecords">—</div>
            <div class="lfs-label">📝 Reports Filed</div>
          </div>
        </div>
      </div>

      <div style="padding:14px 24px 0;background:var(--card);border-left:1px solid var(--border);border-right:1px solid var(--border)">
        <div class="folder-search-wrap" style="margin-bottom:0">
          <span class="fs-icon">🔍</span>
          <input type="text" class="folder-search-input" id="fileSearch" placeholder="Search files…" oninput="filterInnerFiles(this.value)">
        </div>
      </div>

      <div class="file-list-wrap" id="fvFileList"></div>
    </div>

  </div>

  <!-- CUSTOM DOWNLOAD APPROVAL MODAL -->
<div class="dl-modal-overlay" id="dlModalOverlay">
  <div class="dl-modal">
    <div class="dl-modal-top">
      <div class="dl-modal-lock-ring">🔒</div>
      <div class="dl-modal-top-title">Download Approval Required</div>
    </div>
    <div class="dl-modal-body">
      <div class="dl-modal-label">Requesting file</div>
      <div class="dl-modal-filename">
        <span class="dmf-icon" id="dlModalIcon">📄</span>
        <span id="dlModalTitle">File Name</span>
      </div>
      <div class="dl-modal-info">
        <span class="dmi-icon">⏳</span>
        <span>Your request will be sent to the Super Admin for approval. You'll be redirected to a waiting page until it's approved.</span>
      </div>
      <div class="dl-modal-actions">
        <button class="dl-modal-cancel" onclick="closeDlModal()">Cancel</button>
        <button class="dl-modal-confirm" id="dlModalConfirmBtn">
          <span>🚀</span> Send Request
        </button>
      </div>
    </div>
  </div>
</div>
</main>

<script>
const docsByTeacher    = <?= $docs_by_teacher_json ?>;
const leaveByTeacher   = <?= $leave_by_teacher_json ?>;
const leaveTotalAbsent = <?= $leave_total_json ?>;
const teachersArr      = <?= json_encode($teachers_arr) ?>;
const TOTAL_LEAVE_DAYS = <?= $total_leave_days ?>;

// Is the current user a super_admin? Passed from PHP.
const IS_SUPER_ADMIN   = <?= $isSuperAdmin ? 'true' : 'false' ?>;

const avatarGrads = [
  'background:linear-gradient(135deg,#c8102e,#9b0c22)',
  'background:linear-gradient(135deg,var(--success),#059669)',
  'background:linear-gradient(135deg,#d97706,#ef4444)',
  'background:linear-gradient(135deg,#ec4899,#9b0c22)',
  'background:linear-gradient(135deg,#0072ff,#00c6ff)',
];
const fileIcons = { pdf:'📕',doc:'📘',docx:'📘',xls:'📗',xlsx:'📗',ppt:'📙',pptx:'📙',jpg:'🖼',jpeg:'🖼',png:'🖼',gif:'🖼',zip:'📦',rar:'📦',txt:'📄',csv:'📊' };
const leaveTypeClass = { 'Sick Leave':'leave-sick','Vacation Leave':'leave-vacation','Emergency Leave':'leave-emergency','Maternity Leave':'leave-maternity','Other':'leave-other' };
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function getExt(f) { return (f||'').split('.').pop().toLowerCase(); }
function getIcon(f) { return fileIcons[getExt(f)] || '📄'; }
function fmtSize(b) { if(!b) return '—'; if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }
function fmtDate(s) { if(!s) return '—'; return new Date(s).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}); }
function fmtDateChip(s) { const d=new Date(s+'T00:00:00'); return MONTHS[d.getMonth()].substring(0,3)+' '+d.getDate(); }

let currentFolderTab = 'docs';
let currentInnerTab  = 'docs';
let currentTeacherId = null;

// ── DOWNLOAD REQUEST FUNCTION ─────────────────────────
// ── CUSTOM MODAL LOGIC ────────────────────────────────
let _dlModalCallback = null;

function showDlModal(title, icon, onConfirm) {
  document.getElementById('dlModalTitle').textContent = title;
  document.getElementById('dlModalIcon').textContent  = icon;
  document.getElementById('dlModalConfirmBtn').onclick = () => {
    closeDlModal();
    onConfirm();
  };
  document.getElementById('dlModalOverlay').classList.add('open');
}

function closeDlModal() {
  document.getElementById('dlModalOverlay').classList.remove('open');
}

// Close on backdrop click
document.getElementById('dlModalOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeDlModal();
});

function requestDownload(docId, docTitle, directPath, fileName) {
  if (IS_SUPER_ADMIN) {
    const a = document.createElement('a');
    a.href = directPath; a.download = fileName; a.click();
    return;
  }
  const icon = getIcon(fileName);
  showDlModal(docTitle, icon, () => {
    const form = document.createElement('form');
    form.method = 'GET'; form.action = 'download_file.php';
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'doc_id'; inp.value = docId;
    form.appendChild(inp);
    document.body.appendChild(form);
    form.submit();
  });
}

function requestScanDownload(filePath, fileName) {
  showDlModal(fileName, '📎', () => {
    const form = document.createElement('form');
    form.method = 'GET'; form.action = 'download_scan.php';
    const p = document.createElement('input');
    p.type = 'hidden'; p.name = 'file_path'; p.value = filePath;
    const n = document.createElement('input');
    n.type = 'hidden'; n.name = 'file_name'; n.value = fileName;
    form.appendChild(p); form.appendChild(n);
    document.body.appendChild(form);
    form.submit();
  });
}

// Auto-switch tab from URL ?tab= param
(function() {
  const params = new URLSearchParams(window.location.search);
  if (params.get('tab') === 'leave') {
    const leaveBtn = document.querySelector('.folder-tab.tab-purple');
    if (leaveBtn) switchFolderTab('leave', leaveBtn);
  }
})();

function switchFolderTab(tab, el) {
  currentFolderTab = tab;
  document.querySelectorAll('.folder-tabs .folder-tab').forEach(b=>b.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('docsFolderGrid').style.display  = tab==='docs'  ? '' : 'none';
  document.getElementById('leaveFolderGrid').style.display = tab==='leave' ? '' : 'none';
  document.getElementById('folderSearch').value = '';
  filterFolders('');
}

function openFolder(teacherId, preferTab) {
  currentTeacherId = teacherId;
  const teacher = teachersArr.find(t => parseInt(t.id)===parseInt(teacherId));
  if (!teacher) return;
  const docs   = docsByTeacher[teacherId] || [];
  const leaves = leaveByTeacher[teacherId] || [];
  const totalAbsent = leaveTotalAbsent[teacherId] || 0;
  const leaveLeft   = Math.max(0, TOTAL_LEAVE_DAYS - totalAbsent);
  const av = document.getElementById('fvAvatar');
  av.textContent = teacher.full_name[0].toUpperCase();
  av.style.cssText = avatarGrads[teacher.id % 5];
  document.getElementById('fvName').textContent = teacher.full_name;
  document.getElementById('fvMeta').textContent = (teacher.subject||teacher.department||'Teacher')+' · '+docs.length+' doc'+(docs.length!==1?'s':'')+' · '+leaves.length+' leave record'+(leaves.length!==1?'s':'');
  document.getElementById('innerCountDocs').textContent  = docs.length;
  document.getElementById('innerCountLeave').textContent = leaves.length;
  document.getElementById('lfsAvailable').textContent = leaveLeft;
  document.getElementById('lfsAbsent').textContent    = totalAbsent;
  document.getElementById('lfsRecords').textContent   = leaves.length;
  document.getElementById('folderGrid').style.display = 'none';
  const fc = document.getElementById('folderContents');
  fc.classList.add('active');
  document.getElementById('fileSearch').value = '';
  switchInnerTab(preferTab || currentFolderTab);
  document.querySelector('.main').scrollTop = 0;
}

function switchInnerTab(tab) {
  currentInnerTab = tab;
  document.getElementById('innerTabDocs').classList.toggle('active', tab==='docs');
  document.getElementById('innerTabLeave').classList.toggle('active', tab==='leave');
  document.getElementById('leaveStatsBar').style.display = tab==='leave' ? '' : 'none';
  document.getElementById('fileSearch').value = '';
  if (tab==='docs')  renderDocsInFolder(docsByTeacher[currentTeacherId] || []);
  if (tab==='leave') renderLeaveInFolder(leaveByTeacher[currentTeacherId] || []);
}

function renderDocsInFolder(docs) {
  const list = document.getElementById('fvFileList');
  if (docs.length===0) {
    list.innerHTML=`<div class="empty-folder"><span class="ef-icon">📭</span><p>No documents uploaded yet.</p></div>`;
    return;
  }
  list.innerHTML = docs.map(d => {
    const icon    = getIcon(d.file_name);
    const ext     = getExt(d.file_name).toUpperCase();
    const safeTtl = (d.title||'').replace(/'/g,"\\'").replace(/"/g,'&quot;');
    const safeFnm = (d.file_name||'').replace(/'/g,"\\'");

    // Download button differs by role
    const dlBtn = IS_SUPER_ADMIN
      ? `<button class="btn btn-download btn-sm" onclick="requestDownload(${d.id},'${safeTtl}','${d.file_path}','${safeFnm}')">⬇ Download</button>`
      : `<button class="btn btn-download btn-sm" onclick="requestDownload(${d.id},'${safeTtl}','${d.file_path}','${safeFnm}')">🔒 Request Download</button>`;

    return `<div class="file-row" data-title="${(d.title||'').toLowerCase()}" data-fname="${d.file_name.toLowerCase()}">
      <span class="file-row-icon">${icon}</span>
      <div class="file-row-info">
        <strong>${d.title}</strong>
        <span>${d.file_name} · <span style="color:var(--accent)">${ext}</span> · ${fmtSize(d.file_size)} · ${fmtDate(d.uploaded_at)}</span>
      </div>
      <div class="file-row-actions">
        <a class="btn btn-view btn-sm" href="${d.file_path}" target="_blank">👁 View</a>
        ${dlBtn}
      </div>
    </div>`;
  }).join('');
}

function renderLeaveInFolder(records) {
  const list = document.getElementById('fvFileList');
  if (records.length===0) {
    list.innerHTML=`<div class="empty-folder"><span class="ef-icon">📋</span><p>No leave records filed yet.</p></div>`;
    return;
  }
  list.innerHTML = records.map(r => {
    const dates   = r.absent_dates ? r.absent_dates.split(',').filter(Boolean) : [];
    const ltClass = leaveTypeClass[r.leave_type] || 'leave-other';
    const chips   = dates.map(d=>`<span class="date-chip">${fmtDateChip(d.trim())}</span>`).join('');
    const safePath = (r.scan_file_path||'').replace(/'/g,"\\'");
    const safeName = (r.scan_file_name||'leave_scan').replace(/'/g,"\\'");

    // Scan download also goes through approval for regular admins
    const scanBtn = r.scan_file_path
      ? IS_SUPER_ADMIN
        ? `<a class="btn btn-purple btn-sm" href="${r.scan_file_path}" target="_blank">📎 View Scan</a>
           <a class="btn btn-download btn-sm" href="${r.scan_file_path}" download>⬇ Download</a>`
        : `<a class="btn btn-purple btn-sm" href="${r.scan_file_path}" target="_blank">📎 View Scan</a>
           <button class="btn btn-download btn-sm" onclick="requestScanDownload('${safePath}','${safeName}')">🔒 Request Download</button>`
      : `<span style="color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace">No scan attached</span>`;

    return `<div class="leave-file-row" data-title="${r.leave_type.toLowerCase()}" data-fname="${(r.reason||'').toLowerCase()}">
      <span class="file-row-icon">📋</span>
      <div class="file-row-info" style="flex:1;min-width:0">
        <strong style="display:flex;align-items:center;gap:8px">
          <span class="leave-type-badge ${ltClass}">${r.leave_type}</span>
          <span style="color:var(--danger);font-family:'JetBrains Mono',monospace">${r.days_absent} day${r.days_absent!=1?'s':''}</span>
        </strong>
        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:5px">${chips}</div>
        ${r.reason?`<span style="color:var(--muted);margin-top:3px;display:block">${r.reason}</span>`:''}
        <span>Filed: ${fmtDate(r.filed_at)}</span>
      </div>
      <div class="file-row-actions" style="flex-direction:column;gap:4px;align-items:flex-end">${scanBtn}</div>
    </div>`;
  }).join('');
}


function closeFolder() {
  document.getElementById('folderGrid').style.display = '';
  document.getElementById('folderContents').classList.remove('active');
  document.getElementById('fileSearch').value = '';
}

function filterFolders(query) {
  const q = query.trim().toLowerCase();
  const activeGrid = currentFolderTab==='docs' ? '#docsFolderGrid' : '#leaveFolderGrid';
  let visible = 0;
  document.querySelectorAll(activeGrid+' .folder-card').forEach(card => {
    const show = !q || (card.getAttribute('data-folder-name')||'').includes(q);
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('folderNoResults').style.display = (q && visible===0) ? 'block' : 'none';
}

function filterInnerFiles(query) {
  const q = query.trim().toLowerCase();
  const selector = currentInnerTab==='docs' ? '.file-row' : '.leave-file-row';
  document.querySelectorAll('#fvFileList '+selector).forEach(row => {
    const t = (row.getAttribute('data-title')||'')+(row.getAttribute('data-fname')||'');
    row.style.display = (!q || t.includes(q)) ? '' : 'none';
  });
}
</script>
</body>
</html>