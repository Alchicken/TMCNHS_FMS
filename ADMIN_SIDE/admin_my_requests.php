<?php
// ADMIN_SIDE/admin_my_requests.php
// Shows the logged-in admin all their submitted requests and their status.
require_once 'admin_shared.php';
require_once '../helpers/ActionRequest.php';

$ar = new ActionRequest($conn);
$ar->expireOldRequests();

// Mark all admin_request_notifications as read for this user
$conn->query("UPDATE admin_request_notifications SET is_read = 1 WHERE admin_user_id = {$_SESSION['user_id']} AND is_read = 0");

// Fetch this admin's requests
$stmt = $conn->prepare("
    SELECT * FROM action_requests
    WHERE admin_user_id = ?
    ORDER BY requested_at DESC
    LIMIT 100
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Unread notification count for sidebar badge
$unreadNotifRes = $conn->query("SELECT COUNT(*) AS c FROM admin_request_notifications WHERE admin_user_id = {$_SESSION['user_id']} AND is_read = 0");
$unreadNotif = (int)($unreadNotifRes->fetch_assoc()['c'] ?? 0);

// ID notifications for sidebar bell
$id_notifs = [];
$notif_result = $conn->query("SELECT n.*, p.full_name, p.subject FROM admin_notifications n JOIN personnels p ON p.id = n.personnel_id WHERE n.type IN ('expired','expiring_soon') ORDER BY n.type ASC, n.created_at DESC LIMIT 20");
if ($notif_result) while ($row = $notif_result->fetch_assoc()) $id_notifs[] = $row;
$notif_unread = count(array_filter($id_notifs, fn($n) => !$n['is_read']));

$statusColors = [
    'pending'  => ['bg'=>'rgba(217,119,6,0.12)',  'color'=>'#d97706', 'label'=>'⏳ Pending'],
    'approved' => ['bg'=>'rgba(5,150,105,0.12)',  'color'=>'#059669', 'label'=>'✅ Approved'],
    'rejected' => ['bg'=>'rgba(200,16,46,0.1)',   'color'=>'#c8102e', 'label'=>'❌ Rejected'],
    'expired'  => ['bg'=>'rgba(156,123,126,0.1)', 'color'=>'#9c7b7e', 'label'=>'⏰ Expired'],
];

$actionIcons = [
    'add_personnel' => '👤',
    'download_file' => '📥',
    'delete_record' => '🗑️',
    'export_data'   => '📤',
    'edit_personnel'=> '✏️',
    'create_admin'  => '🛡️',
    'deactivate_user'=>'🚫',
    'other'         => '📌',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Requests — TMCNHS Admin</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg: #f7f2f2; --surface: #fff; --card: #fff; --border: #f0d4d4;
  --accent: #c8102e; --accent2: #9b0c22; --text: #1a0608; --muted: #9c7b7e;
  --success: #059669; --danger: #c8102e; --warn: #d97706;
  --red: #c8102e; --red-dark: #9b0c22; --sidebar: 260px;
}
body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

.sidebar { width: var(--sidebar); background: linear-gradient(135deg, var(--red), var(--red-dark)); display: flex; flex-direction: column; position: fixed; top: 0; left: 0; bottom: 0; z-index: 100; box-shadow: 4px 0 20px rgba(155,12,34,0.15); }
.sidebar-logo { padding: 24px 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.12); display: flex; flex-direction: column; align-items: center; gap: 12px; }
.sidebar-logo-img-wrap { width: 76px; height: 76px; border-radius: 50%; background: #fff; padding: 6px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 18px rgba(0,0,0,0.3), 0 0 0 3px rgba(255,255,255,0.18); }
.sidebar-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
.sidebar-logo-text { text-align: center; }
.sidebar-logo-text h2 { color: #fff; font-size: 12.5px; font-weight: 700; font-family: 'DM Serif Display', serif; line-height: 1.45; }
.sidebar-logo-text span { color: rgba(255,255,255,0.45); font-size: 9px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1.8px; display: block; margin-top: 4px; }
.nav { padding: 16px 12px; flex: 1; overflow-y: auto; min-height: 0; }
.nav-label { color: rgba(255,255,255,0.45); font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 2px; padding: 12px 12px 6px; }
.nav-item { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border-radius: 10px; cursor: pointer; color: rgba(255,255,255,0.65); font-size: 13px; font-weight: 500; transition: all 0.2s; margin-bottom: 2px; text-decoration: none; }
.nav-item:hover { background: rgba(255,255,255,0.12); color: #fff; }
.nav-item.active { background: rgba(255,255,255,0.18); color: #fff; font-weight: 600; }
.nav-item .ni { font-size: 16px; }
.nav-badge { margin-left: auto; min-width: 20px; height: 20px; border-radius: 50%; background: #ff3b3b; color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.sidebar-footer { padding: 16px 12px; border-top: 1px solid rgba(255,255,255,0.15); flex-shrink: 0; }
.user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,0.12); border-radius: 10px; }
.avatar { width: 34px; height: 34px; background: rgba(255,255,255,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.user-info { flex: 1; overflow: hidden; }
.user-info strong { display: block; font-size: 12px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-info small { color: rgba(255,255,255,0.55); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
.logout-btn { color: rgba(255,255,255,0.55); font-size: 1.5rem; text-decoration: none; }
.logout-btn:hover { color: #fff; }

.main { margin-left: var(--sidebar); flex: 1; }
.topbar { padding: 20px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--surface); position: sticky; top: 0; z-index: 50; }
.topbar h1 { font-size: 18px; font-weight: 700; }
.topbar p { color: var(--muted); font-size: 12px; margin-top: 2px; }
.page { padding: 28px 32px; }

/* STATS */
.stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--card); border: 1.5px solid var(--border); border-radius: 16px; padding: 20px 22px; }
.stat-card .label { font-size: 11px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
.stat-card .value { font-size: 30px; font-weight: 700; }

/* REQUEST CARDS */
.requests-list { display: flex; flex-direction: column; gap: 14px; }
.req-card { background: var(--card); border: 1.5px solid var(--border); border-radius: 16px; padding: 20px 24px; display: flex; align-items: flex-start; gap: 16px; transition: box-shadow 0.2s; }
.req-card:hover { box-shadow: 0 4px 20px rgba(200,16,46,0.08); }
.req-card.status-rejected { border-left: 4px solid var(--danger); }
.req-card.status-approved { border-left: 4px solid var(--success); }
.req-card.status-pending  { border-left: 4px solid var(--warn); }
.req-card.status-expired  { border-left: 4px solid var(--muted); opacity: 0.75; }
.req-icon { font-size: 32px; flex-shrink: 0; }
.req-body { flex: 1; min-width: 0; }
.req-title { font-weight: 600; font-size: 15px; margin-bottom: 5px; }
.req-meta { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.req-rejection { margin-top: 10px; background: rgba(200,16,46,0.06); border: 1px solid rgba(200,16,46,0.2); border-radius: 10px; padding: 10px 14px; font-size: 13px; color: var(--danger); }
.req-rejection strong { display: block; font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.status-badge { display: inline-flex; align-items: center; padding: 3px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
.req-meta-item { display: flex; align-items: center; gap: 4px; }

/* Personnel preview inside card */
.personnel-preview { margin-top: 12px; background: rgba(200,16,46,0.03); border: 1px solid rgba(200,16,46,0.12); border-radius: 10px; padding: 12px 16px; }
.personnel-preview .pp-label { font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); margin-bottom: 8px; }
.pp-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px 16px; }
.pp-item label { font-size: 10px; font-family: 'JetBrains Mono', monospace; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 1px; }
.pp-item span { font-size: 12px; font-weight: 500; }

/* Empty state */
.empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }
.empty-state .icon { font-size: 48px; margin-bottom: 14px; display: block; }
.empty-state h3 { font-family: 'DM Serif Display', serif; font-size: 22px; color: var(--text); margin-bottom: 8px; }
.empty-state p { font-size: 14px; }

/* Filter tabs */
.filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-tab { padding: 7px 16px; border-radius: 999px; font-size: 12px; font-weight: 600; cursor: pointer; border: 1.5px solid var(--border); color: var(--muted); background: var(--card); transition: all 0.2s; }
.filter-tab:hover { border-color: var(--accent); color: var(--accent); }
.filter-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.filter-tab .count { background: rgba(255,255,255,0.25); border-radius: 999px; padding: 0 6px; font-size: 10px; margin-left: 4px; }
.filter-tab:not(.active) .count { background: rgba(200,16,46,0.1); color: var(--accent); }
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
    <a class="nav-item active" href="admin_my_requests.php">
      <span class="ni">📋</span> My Requests
      <?php if ($unreadNotif > 0): ?>
        <span class="nav-badge"><?= $unreadNotif ?></span>
      <?php endif; ?>
    </a>
    <a class="nav-item" href="admin_notifications.php">
      <span class="ni">🔔</span> ID Notifications
      <?php if ($notif_unread > 0): ?>
        <span class="nav-badge"><?= $notif_unread ?></span>
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
      <a href="../logout.php" class="logout-btn" title="Logout">⏏</a>
    </div>
  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div>
      <h1>📋 My Requests</h1>
      <p>Track the status of all your submitted approval requests</p>
    </div>
  </div>
  <div class="page">

    <?php
      $counts = ['all'=>count($requests), 'pending'=>0, 'approved'=>0, 'rejected'=>0, 'expired'=>0];
      foreach ($requests as $r) $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
    ?>

    <!-- Stats -->
    <div class="stats-row">
      <div class="stat-card">
        <div class="label">Total</div>
        <div class="value"><?= $counts['all'] ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Pending</div>
        <div class="value" style="color:var(--warn)"><?= $counts['pending'] ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Approved</div>
        <div class="value" style="color:var(--success)"><?= $counts['approved'] ?></div>
      </div>
      <div class="stat-card">
        <div class="label">Rejected</div>
        <div class="value" style="color:var(--danger)"><?= $counts['rejected'] ?></div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
      <button class="filter-tab active" onclick="filterRequests('all',this)">All <span class="count"><?= $counts['all'] ?></span></button>
      <button class="filter-tab" onclick="filterRequests('pending',this)">⏳ Pending <span class="count"><?= $counts['pending'] ?></span></button>
      <button class="filter-tab" onclick="filterRequests('approved',this)">✅ Approved <span class="count"><?= $counts['approved'] ?></span></button>
      <button class="filter-tab" onclick="filterRequests('rejected',this)">❌ Rejected <span class="count"><?= $counts['rejected'] ?></span></button>
      <button class="filter-tab" onclick="filterRequests('expired',this)">⏰ Expired <span class="count"><?= $counts['expired'] ?></span></button>
    </div>

    <?php if (empty($requests)): ?>
      <div class="empty-state">
        <span class="icon">📭</span>
        <h3>No requests yet</h3>
        <p>When you submit an action that requires Super Admin approval, it will appear here.</p>
      </div>
    <?php else: ?>
    <div class="requests-list" id="requestsList">
      <?php foreach ($requests as $req):
        $sc = $statusColors[$req['status']] ?? $statusColors['expired'];
        $icon = $actionIcons[$req['action_type']] ?? '📌';
        $meta = $req['meta_json'] ? json_decode($req['meta_json'], true) : [];
        $isPersonnel = $req['action_type'] === 'add_personnel';
      ?>
      <div class="req-card status-<?= $req['status'] ?>" data-status="<?= $req['status'] ?>">
        <div class="req-icon"><?= $icon ?></div>
        <div class="req-body">
          <div class="req-title"><?= htmlspecialchars($req['action_label']) ?></div>
          <div class="req-meta">
            <span class="status-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>"><?= $sc['label'] ?></span>
            <span class="req-meta-item">🕐 <?= date('M j, Y g:i A', strtotime($req['requested_at'])) ?></span>
            <span class="req-meta-item" style="font-size:10px;opacity:0.6">Request #<?= $req['id'] ?></span>
            <?php if ($req['reviewed_at']): ?>
              <span class="req-meta-item">✍️ Reviewed: <?= date('M j, Y g:i A', strtotime($req['reviewed_at'])) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($req['status'] === 'rejected' && $req['rejection_note']): ?>
            <div class="req-rejection">
              <strong>Rejection Reason</strong>
              <?= htmlspecialchars($req['rejection_note']) ?>
            </div>
          <?php endif; ?>

          <?php if ($isPersonnel && !empty($meta)): ?>
            <div class="personnel-preview">
              <div class="pp-label">👤 Personnel Details</div>
              <div class="pp-grid">
                <div class="pp-item"><label>Full Name</label><span><?= htmlspecialchars(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')) ?></span></div>
                <div class="pp-item"><label>Position</label><span><?= htmlspecialchars($meta['subject'] ?? '—') ?></span></div>
                <div class="pp-item"><label>Department</label><span><?= htmlspecialchars($meta['department'] ?? '—') ?></span></div>
                <div class="pp-item"><label>Email</label><span><?= htmlspecialchars($meta['email'] ?? '—') ?></span></div>
                <div class="pp-item"><label>Employment</label><span><?= htmlspecialchars($meta['employment_type'] ?? '—') ?></span></div>
                <div class="pp-item"><label>Date Hired</label><span><?= htmlspecialchars($meta['date_hired'] ?? '—') ?></span></div>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($req['status'] === 'rejected'): ?>
            <div style="margin-top:10px">
              <a href="manage_personnel.php?resubmit=<?= $req['id'] ?>" class="btn" style="display:inline-flex;align-items:center;gap:6px;padding:7px 16px;background:rgba(200,16,46,0.1);color:var(--danger);border:1px solid rgba(200,16,46,0.3);border-radius:9px;font-size:12px;font-weight:600;text-decoration:none;font-family:'DM Sans',sans-serif">
                🔄 Resubmit Request
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</main>

<script>
function filterRequests(status, el) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.req-card').forEach(card => {
    card.style.display = (status === 'all' || card.getAttribute('data-status') === status) ? '' : 'none';
  });
}
</script>
</body>
</html>