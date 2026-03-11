<?php
// admin_notifications.php — ADMIN: ID Notification Center (Real-Time Enhanced)
require_once 'admin_shared.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS admin_notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT NOT NULL,
    type         ENUM('id_updated','expiring_soon','expired') NOT NULL,
    id_kind      ENUM('prc','other') NOT NULL,
    message      TEXT NOT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_personnel (personnel_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
)");

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── AJAX: poll for new notifications ─────────────────────────────────────────
if ($action === 'poll') {
    $since = $_GET['since'] ?? date('Y-m-d H:i:s', strtotime('-1 second'));
    $since = $conn->real_escape_string($since);
    $r = $conn->query("
        SELECT n.*, p.full_name, p.subject, p.profile_image
        FROM admin_notifications n
        LEFT JOIN personnels p ON p.id = n.personnel_id
        WHERE n.created_at > '{$since}'
        ORDER BY n.created_at DESC
        LIMIT 15
    ");
    $new_notifs = [];
    while ($row = $r->fetch_assoc()) $new_notifs[] = $row;
    $unread = (int)($conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetch_row()[0]);
    $counts = [];
    foreach (['all','id_updated','expiring_soon','expired'] as $t) {
        $w = ($t !== 'all') ? "WHERE type='{$t}'" : '';
        $rr = $conn->query("SELECT COUNT(*) FROM admin_notifications {$w}");
        $counts[$t] = $rr ? (int)$rr->fetch_row()[0] : 0;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'notifications' => $new_notifs,
        'unread_count'  => $unread,
        'counts'        => $counts,
        'server_time'   => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($action === 'mark_read' && !empty($_POST['id'])) {
    $nid = (int)$_POST['id'];
    $conn->query("UPDATE admin_notifications SET is_read=1 WHERE id={$nid}");
    $unread = (int)($conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetch_row()[0]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'unread_count' => $unread]);
    exit;
}
if ($action === 'mark_all_read') {
    $conn->query("UPDATE admin_notifications SET is_read=1");
    header('Location: admin_notifications.php?marked=1'); exit;
}
if ($action === 'delete' && !empty($_POST['id'])) {
    $nid = (int)$_POST['id'];
    $conn->query("DELETE FROM admin_notifications WHERE id={$nid}");
    $unread = (int)($conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetch_row()[0]);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'unread_count' => $unread]);
    exit;
}
if ($action === 'clear_read') {
    $conn->query("DELETE FROM admin_notifications WHERE is_read=1");
    header('Location: admin_notifications.php?cleared=1'); exit;
}

$filter_type = $_GET['type'] ?? 'all';
$filter_read = $_GET['read'] ?? 'all';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;

$where = [];
if ($filter_type !== 'all') $where[] = "n.type = '" . $conn->real_escape_string($filter_type) . "'";
if ($filter_read === 'unread') $where[] = "n.is_read = 0";
if ($filter_read === 'read')   $where[] = "n.is_read = 1";
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total_q = $conn->query("SELECT COUNT(*) FROM admin_notifications n {$whereSQL}");
$total   = $total_q ? (int)$total_q->fetch_row()[0] : 0;
$pages   = max(1, ceil($total / $per_page));

$rows = $conn->query("
    SELECT n.*, p.full_name, p.subject, p.profile_image
    FROM admin_notifications n
    LEFT JOIN personnels p ON p.id = n.personnel_id
    {$whereSQL}
    ORDER BY n.is_read ASC, n.created_at DESC
    LIMIT {$per_page} OFFSET {$offset}
");

$cnt = [];
foreach (['all','id_updated','expiring_soon','expired'] as $t) {
    $w = ($t !== 'all') ? "WHERE type='{$t}'" : '';
    $r = $conn->query("SELECT COUNT(*) FROM admin_notifications {$w}");
    $cnt[$t] = $r ? (int)$r->fetch_row()[0] : 0;
}
$unread_total = (int)($conn->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read=0")->fetch_row()[0]);
$server_time  = date('Y-m-d H:i:s');

function ftab_url($type, $read, $p = 1) {
    return "?type={$type}&read={$read}&page={$p}";
}
function human_time_diff(int $ts): string {
    $d = time() - $ts;
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d/60).'m ago';
    if ($d < 86400)  return floor($d/3600).'h ago';
    if ($d < 604800) return floor($d/86400).'d ago';
    return date('M j', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ID Notifications — TMCNHS Admin</title>
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══ RESET & TOKENS ═══ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f5f0f0;
  --surface:#fff;
  --card:#fff;
  --border:#eedada;
  --border-soft:#f7eeee;
  --red:#c8102e;--red2:#9b0c22;--red3:#6b0518;
  --text:#180608;--muted:#9a7a7d;--muted2:#c4a8ab;
  --ok:#0a8a5c;--ok-bg:rgba(10,138,92,.09);--ok-bd:rgba(10,138,92,.22);
  --warn:#c97a06;--warn-bg:rgba(201,122,6,.09);--warn-bd:rgba(201,122,6,.22);
  --err:#c8102e;--err-bg:rgba(200,16,46,.07);--err-bd:rgba(200,16,46,.2);
  --sidebar:264px;--topbar:72px;
  --r2xl:24px;--rxl:18px;--rlg:14px;--rmd:10px;--rsm:7px;
  --sh-sm:0 1px 4px rgba(155,12,34,.06);
  --sh-md:0 4px 22px rgba(155,12,34,.1);
  --sh-lg:0 14px 44px rgba(155,12,34,.15);
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;line-height:1.5}

/* ═══ SIDEBAR ═══ */
.sidebar{
  width:var(--sidebar);
  background:linear-gradient(168deg,#820018 0%,var(--red2) 52%,var(--red) 100%);
  display:flex;flex-direction:column;
  position:fixed;top:0;left:0;bottom:0;z-index:200;
  box-shadow:8px 0 44px rgba(107,5,24,.28);
}
.sidebar::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:radial-gradient(rgba(255,255,255,.06) 1px,transparent 1px);background-size:18px 18px}
.sidebar::after{content:'';position:absolute;bottom:0;left:0;right:0;height:200px;pointer-events:none;background:linear-gradient(to top,rgba(0,0,0,.18),transparent)}
.s-logo{padding:26px 20px 22px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;align-items:center;gap:12px;position:relative;z-index:1}
.s-ring{width:78px;height:78px;border-radius:50%;background:#fff;padding:7px;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 28px rgba(0,0,0,.28),0 0 0 3px rgba(255,255,255,.16),0 0 0 6px rgba(255,255,255,.05);flex-shrink:0}
.s-ring img{width:100%;height:100%;object-fit:contain}
.s-text{text-align:center}
.s-text h2{color:#fff;font-size:12px;font-weight:700;font-family:'DM Serif Display',serif;line-height:1.5}
.s-text span{color:rgba(255,255,255,.36);font-size:9px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:2.5px;display:block;margin-top:3px}
.s-nav{padding:14px 12px;flex:1;overflow-y:auto;position:relative;z-index:1}
.s-nav::-webkit-scrollbar{width:3px}
.s-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:99px}
.nav-sect{color:rgba(255,255,255,.3);font-size:9px;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:2.5px;padding:14px 14px 6px;margin-top:4px}
.nav-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;color:rgba(255,255,255,.6);font-size:13px;font-weight:500;transition:all .2s;margin-bottom:3px;text-decoration:none;position:relative}
.nav-item:hover{background:rgba(255,255,255,.1);color:#fff}
.nav-item.active{background:rgba(255,255,255,.16);color:#fff;font-weight:600;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
.nav-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:20px;background:#fff;border-radius:0 3px 3px 0}
.ni{font-size:15px;width:22px;text-align:center;flex-shrink:0}
.n-badge{margin-left:auto;flex-shrink:0;background:#fff;color:var(--red);border-radius:20px;font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:700;padding:2px 8px;min-width:22px;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,.2);animation:b-breathe 2.4s ease-in-out infinite}
@keyframes b-breathe{0%,100%{box-shadow:0 2px 8px rgba(0,0,0,.2)}50%{box-shadow:0 2px 8px rgba(0,0,0,.2),0 0 0 4px rgba(255,255,255,.15)}}
.s-foot{padding:14px 14px 18px;border-top:1px solid rgba(255,255,255,.1);position:relative;z-index:1}
.u-chip{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.1);border-radius:12px;border:1px solid rgba(255,255,255,.08)}
.u-av{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.u-info{flex:1;overflow:hidden}
.u-info strong{display:block;font-size:12px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.u-info small{color:rgba(255,255,255,.4);font-size:10px;font-family:'JetBrains Mono',monospace}
.logout{color:rgba(255,255,255,.42);font-size:18px;text-decoration:none;transition:color .2s}
.logout:hover{color:#fff}

/* ═══ MAIN ═══ */
.main{margin-left:var(--sidebar);flex:1;min-width:0;display:flex;flex-direction:column;min-height:100vh}
.topbar{height:var(--topbar);padding:0 36px;border-bottom:1px solid var(--border);background:rgba(255,255,255,.9);backdrop-filter:blur(14px);position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;gap:14px;box-shadow:var(--sh-sm)}
.tb-left{display:flex;align-items:center;gap:14px}
.tb-icon{width:44px;height:44px;border-radius:var(--rmd);background:linear-gradient(135deg,var(--red2),var(--red));display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 14px rgba(200,16,46,.3);flex-shrink:0}
.tb-titles h1{font-size:18px;font-weight:700;font-family:'DM Serif Display',serif;line-height:1.1;display:flex;align-items:center;gap:10px}
.tb-titles p{color:var(--muted);font-size:11px;margin-top:1px;font-family:'JetBrains Mono',monospace}
.unread-pill{background:linear-gradient(135deg,var(--red2),var(--red));color:#fff;border-radius:20px;padding:3px 12px;font-size:11px;font-weight:700;font-family:'JetBrains Mono',monospace;box-shadow:0 3px 10px rgba(200,16,46,.35);display:inline-flex;align-items:center;gap:5px}
.unread-pill::before{content:'';width:6px;height:6px;border-radius:50%;background:#fff;animation:p-pulse 2s ease-in-out infinite}
@keyframes p-pulse{0%,100%{opacity:1}50%{opacity:.35}}
.tb-actions{display:flex;gap:8px;align-items:center;flex-shrink:0}

/* ═══ PAGE ═══ */
.page{padding:28px 36px;flex:1}

/* LIVE RIBBON */
.live-ribbon{
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
  background:var(--surface);border:1px solid var(--border);border-radius:var(--rlg);
  padding:13px 20px;margin-bottom:24px;box-shadow:var(--sh-sm);
  position:relative;overflow:hidden;
}
.live-ribbon::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--ok);border-radius:4px 0 0 4px;transition:background .5s}
.live-ribbon.conn::before{background:var(--warn)}
.live-ribbon.err::before{background:var(--err)}
.lr-left{display:flex;align-items:center;gap:12px}
.lr-dot-wrap{position:relative;width:14px;height:14px;flex-shrink:0}
.lr-dot{width:14px;height:14px;border-radius:50%;background:var(--ok);transition:background .4s}
.lr-ring{position:absolute;inset:-4px;border-radius:50%;border:2px solid var(--ok);opacity:.5;animation:ring-out 2s ease-out infinite;transition:border-color .4s}
@keyframes ring-out{0%{transform:scale(.7);opacity:.7}100%{transform:scale(2.2);opacity:0}}
.live-ribbon.conn .lr-dot{background:var(--warn)}
.live-ribbon.conn .lr-ring{border-color:var(--warn)}
.live-ribbon.err  .lr-dot{background:var(--err)}
.live-ribbon.err  .lr-ring{animation:none;opacity:0}
.lr-text{font-size:12px;font-family:'JetBrains Mono',monospace}
.lr-text strong{color:var(--text)}
.lr-text span{color:var(--muted)}
.lr-right{display:flex;align-items:center;gap:20px;font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--muted)}
.lr-right strong{color:var(--text)}
.poll-track{height:3px;background:var(--border-soft);border-radius:99px;overflow:hidden;width:110px;flex-shrink:0}
.poll-fill{height:100%;background:linear-gradient(90deg,var(--ok),#06d68f);border-radius:99px;width:0%;transition:width linear}

/* NEW BANNER */
.new-banner{display:none;align-items:center;justify-content:space-between;gap:12px;background:var(--ok-bg);border:1px solid var(--ok-bd);border-radius:var(--rmd);padding:12px 18px;margin-bottom:16px}
.new-banner.on{display:flex;animation:banner-slide .35s cubic-bezier(.22,1,.36,1)}
@keyframes banner-slide{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.nb-left{display:flex;align-items:center;gap:10px;font-size:13px;font-weight:600;color:var(--ok)}
.nb-badge{background:var(--ok);color:#fff;border-radius:20px;padding:1px 9px;font-size:11px;font-family:'JetBrains Mono',monospace;font-weight:700}
.nb-btn{padding:7px 16px;border-radius:var(--rsm);background:var(--ok);color:#fff;border:none;font-family:'DM Sans',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:opacity .2s;display:flex;align-items:center;gap:6px}
.nb-btn:hover{opacity:.85}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.s-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--rxl);padding:20px 22px;position:relative;overflow:hidden;transition:transform .2s,box-shadow .2s;cursor:default}
.s-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md)}
.s-card::after{content:'';position:absolute;bottom:-20px;right:-20px;width:90px;height:90px;border-radius:50%;opacity:.07}
.c-all::after{background:var(--red)}.c-upd::after{background:var(--ok)}.c-exp::after{background:var(--warn)}.c-xpd::after{background:var(--err)}
.s-bar{position:absolute;top:0;left:0;right:0;height:3px}
.c-all .s-bar{background:linear-gradient(90deg,var(--red3),var(--red))}
.c-upd .s-bar{background:linear-gradient(90deg,#047857,var(--ok))}
.c-exp .s-bar{background:linear-gradient(90deg,#a06010,var(--warn))}
.c-xpd .s-bar{background:linear-gradient(90deg,var(--red2),var(--err))}
.s-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.s-ico{width:42px;height:42px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:19px}
.c-all .s-ico{background:var(--err-bg)}.c-upd .s-ico{background:var(--ok-bg)}.c-exp .s-ico{background:var(--warn-bg)}.c-xpd .s-ico{background:var(--err-bg)}
.s-num{font-size:34px;font-weight:700;font-family:'JetBrains Mono',monospace;line-height:1;transition:all .3s}
.c-all .s-num{color:var(--red)}.c-upd .s-num{color:var(--ok)}.c-exp .s-num{color:var(--warn)}.c-xpd .s-num{color:var(--err)}
.s-label{font-size:11px;color:var(--muted);margin-top:4px;font-weight:500}
.s-delta{font-size:10px;font-family:'JetBrains Mono',monospace;font-weight:700;padding:2px 8px;border-radius:20px;background:var(--ok-bg);color:var(--ok);display:none}
.s-delta.on{display:inline-block;animation:delta-pop .3s cubic-bezier(.22,1,.36,1)}
@keyframes delta-pop{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}

/* TOOLBAR */
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.tab-group{display:flex;gap:6px;flex-wrap:wrap}
.ftab{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:var(--rmd);font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);text-decoration:none;transition:all .2s}
.ftab:hover{color:var(--text);border-color:#ddc8c8;background:#fdf7f7}
.ftab.on{color:#fff;border-color:transparent;box-shadow:0 3px 12px rgba(0,0,0,.15)}
.ftab.on.t-a{background:linear-gradient(135deg,var(--red3),var(--red))}
.ftab.on.t-u{background:linear-gradient(135deg,#047857,var(--ok))}
.ftab.on.t-e{background:linear-gradient(135deg,#a06010,var(--warn))}
.ftab.on.t-x{background:linear-gradient(135deg,var(--red2),var(--err))}
.ftab-ct{background:rgba(0,0,0,.12);border-radius:20px;padding:1px 8px;font-size:10px;font-family:'JetBrains Mono',monospace;transition:all .3s}
.ftab.on .ftab-ct{background:rgba(255,255,255,.22)}
.read-pills{display:flex;gap:5px}
.rpill{padding:6px 14px;border-radius:var(--rsm);font-size:11px;font-weight:600;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);text-decoration:none;font-family:'JetBrains Mono',monospace;transition:all .18s}
.rpill:hover{color:var(--text);border-color:#ddc8c8}
.rpill.on{background:var(--red);color:#fff;border-color:transparent;box-shadow:0 2px 8px rgba(200,16,46,.28)}

/* NOTIF SHELL */
.notif-shell{background:var(--surface);border:1px solid var(--border);border-radius:var(--r2xl);overflow:hidden;box-shadow:var(--sh-sm)}
.notif-list{padding:16px;display:flex;flex-direction:column;gap:10px}

/* NOTIFICATION CARD */
.nc{
  display:grid;grid-template-columns:auto auto 1fr auto;align-items:center;gap:14px;
  background:var(--surface);border:1.5px solid var(--border);border-radius:var(--rxl);
  padding:16px 18px;position:relative;overflow:hidden;
  transition:transform .18s,box-shadow .18s,border-color .18s;
}
.nc:hover{transform:translateX(3px);box-shadow:var(--sh-md);border-color:#e4c2c2}
.nc::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:4px 0 0 4px;opacity:0;transition:opacity .2s}
.nc:hover::before{opacity:1}
.nc.type-id_updated::before{background:var(--ok)}
.nc.type-expiring_soon::before{background:var(--warn)}
.nc.type-expired::before{background:var(--err)}
.nc.unread{background:linear-gradient(108deg,rgba(200,16,46,.025),var(--surface) 58%)}
.nc.unread.type-id_updated{background:linear-gradient(108deg,rgba(10,138,92,.025),var(--surface) 58%)}
.nc.unread.type-expiring_soon{background:linear-gradient(108deg,rgba(201,122,6,.025),var(--surface) 58%)}
.nc.arrive{animation:card-in .45s cubic-bezier(.22,1,.36,1) both}
@keyframes card-in{from{opacity:0;transform:translateY(-20px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
/* Avatar */
.nc-av{width:46px;height:46px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;color:#fff;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.14)}
.nc-av img{width:100%;height:100%;object-fit:cover}
.av0{background:linear-gradient(135deg,var(--red2),var(--red))}
.av1{background:linear-gradient(135deg,#047857,var(--ok))}
.av2{background:linear-gradient(135deg,#a06010,var(--warn))}
.av3{background:linear-gradient(135deg,#5b2080,#8b3cc4)}
.av4{background:linear-gradient(135deg,#1640b0,#3a7ef5)}
/* Type icon */
.nc-ico{width:38px;height:38px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:17px}
.nc-ico.id_updated{background:var(--ok-bg);border:1px solid var(--ok-bd)}
.nc-ico.expiring_soon{background:var(--warn-bg);border:1px solid var(--warn-bd)}
.nc-ico.expired{background:var(--err-bg);border:1px solid var(--err-bd)}
/* Body */
.nc-body{min-width:0}
.nc-name{font-size:13.5px;font-weight:700;margin-bottom:4px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.new-pill{font-size:10px;background:var(--err-bg);color:var(--err);padding:1px 7px;border-radius:20px;font-family:'JetBrains Mono',monospace;font-weight:700;letter-spacing:.3px}
.nc-msg{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:8px}
.nc-meta{display:flex;gap:7px;flex-wrap:wrap;align-items:center}
.nc-time{font-size:11px;color:var(--muted2);font-family:'JetBrains Mono',monospace;display:flex;align-items:center;gap:4px}
.nc-tag{display:inline-flex;align-items:center;gap:3px;padding:2px 9px;border-radius:6px;font-size:10px;font-weight:700;font-family:'JetBrains Mono',monospace;letter-spacing:.3px}
.nc-tag.prc{background:rgba(107,47,160,.08);color:#6b2fa0;border:1px solid rgba(107,47,160,.18)}
.nc-tag.other{background:rgba(14,116,144,.08);color:#0e7490;border:1px solid rgba(14,116,144,.18)}
.nc-tag.id_updated{background:var(--ok-bg);color:var(--ok);border:1px solid var(--ok-bd)}
.nc-tag.expiring_soon{background:var(--warn-bg);color:var(--warn);border:1px solid var(--warn-bd)}
.nc-tag.expired{background:var(--err-bg);color:var(--err);border:1px solid var(--err-bd)}
.nc-pos{font-size:11px;color:var(--muted2);font-family:'JetBrains Mono',monospace;max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* Right */
.nc-right{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0}
.u-dot{width:9px;height:9px;border-radius:50%;background:var(--red);box-shadow:0 0 0 3px rgba(200,16,46,.18);animation:dot-throb 2.5s ease-in-out infinite}
@keyframes dot-throb{0%,100%{box-shadow:0 0 0 3px rgba(200,16,46,.18)}50%{box-shadow:0 0 0 6px rgba(200,16,46,.04)}}
.nc-acts{display:flex;gap:5px}
.nc-btn{padding:6px 13px;border-radius:var(--rsm);font-size:11px;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);font-family:'DM Sans',sans-serif;transition:all .16s;display:inline-flex;align-items:center;gap:4px}
.nc-btn:hover{color:var(--text);background:#fdf7f7;border-color:#ddc8c8}
.nc-btn.read:hover{color:var(--ok);border-color:var(--ok-bd);background:var(--ok-bg)}
.nc-btn.del:hover{color:var(--err);border-color:var(--err-bd);background:var(--err-bg)}
.nc-btn:disabled{opacity:.4;pointer-events:none}

/* EMPTY */
.empty-area{padding:80px 24px;text-align:center}
.empty-emo{font-size:58px;display:block;margin-bottom:18px;filter:drop-shadow(0 4px 12px rgba(155,12,34,.1))}
.empty-area h3{font-size:22px;font-weight:700;font-family:'DM Serif Display',serif;margin-bottom:10px}
.empty-area p{color:var(--muted);font-size:13.5px;line-height:1.7;max-width:340px;margin:0 auto 18px}
.empty-area a{color:var(--red);font-weight:600;text-decoration:none}
.empty-area a:hover{text-decoration:underline}

/* PAGINATION */
.pager{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:16px 22px;border-top:1px solid var(--border);background:linear-gradient(to bottom,transparent,rgba(245,240,240,.5))}
.pager-info{font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace}
.pager-info strong{color:var(--text)}
.pager-btns{display:flex;gap:4px}
.pg{min-width:34px;height:34px;border-radius:var(--rsm);border:1.5px solid var(--border);background:var(--surface);color:var(--text);font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .16s;text-decoration:none;padding:0 8px}
.pg:hover:not(.off){border-color:var(--red);color:var(--red);background:#fdf7f7}
.pg.on{background:linear-gradient(135deg,var(--red2),var(--red));border-color:transparent;color:#fff;box-shadow:0 3px 10px rgba(200,16,46,.28)}
.pg.off{opacity:.35;pointer-events:none}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:var(--rmd);font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;white-space:nowrap}
.btn-sm{padding:7px 14px;font-size:12px}
.btn-ghost{background:var(--surface);color:var(--text);border:1.5px solid var(--border)}
.btn-ghost:hover{border-color:var(--red);color:var(--red)}
.btn-ok{background:linear-gradient(135deg,#047857,var(--ok));color:#fff;box-shadow:0 3px 12px rgba(10,138,92,.25)}
.btn-ok:hover{opacity:.9;transform:translateY(-1px)}
.btn-del{background:var(--err-bg);color:var(--err);border:1.5px solid var(--err-bd)}
.btn-del:hover{background:rgba(200,16,46,.14)}

/* FLASH */
.flash{padding:13px 18px;border-radius:var(--rmd);font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:9px;font-weight:500}
.flash-ok{background:var(--ok-bg);border:1px solid var(--ok-bd);color:var(--ok)}

/* TOAST */
#toasts{position:fixed;bottom:28px;right:28px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.t-item{background:var(--surface);border:1px solid var(--border);border-radius:var(--rlg);padding:13px 18px;font-size:13px;font-weight:600;box-shadow:var(--sh-lg);opacity:0;transform:translateY(14px) scale(.96);transition:all .32s cubic-bezier(.22,1,.36,1);pointer-events:auto;display:flex;align-items:center;gap:10px;min-width:200px}
.t-item.show{opacity:1;transform:translateY(0) scale(1)}
.t-item.ok {border-left:4px solid var(--ok)}
.t-item.err{border-left:4px solid var(--err)}
.t-item.inf{border-left:4px solid var(--warn)}

/* MODAL */
.mbg{display:none;position:fixed;inset:0;background:rgba(12,2,4,.75);z-index:3000;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px)}
.mbg.open{display:flex;animation:mfade .22s ease}
@keyframes mfade{from{opacity:0}to{opacity:1}}
.mbox{background:var(--surface);border-radius:var(--r2xl);padding:36px;max-width:390px;width:100%;text-align:center;animation:mbox-in .34s cubic-bezier(.22,1,.36,1);box-shadow:var(--sh-lg),0 0 0 1px rgba(200,16,46,.08)}
@keyframes mbox-in{from{opacity:0;transform:scale(.88) translateY(18px)}to{opacity:1;transform:scale(1) translateY(0)}}
.m-emo{font-size:44px;display:block;margin-bottom:14px}
.mbox h3{font-size:18px;font-weight:700;font-family:'DM Serif Display',serif;margin-bottom:8px}
.mbox p{color:var(--muted);font-size:13px;line-height:1.65;margin-bottom:26px}
.m-btns{display:flex;gap:10px;justify-content:center}

.nav-label {color: rgba(255, 255, 255, 0.45);
    font-size: 10px;
    font-family: 'JetBrains Mono', monospace;
    text-transform: uppercase;
    letter-spacing: 2px;
    padding: 12px 12px 6px;}
</style>
</head>
<body>

<!-- ─── SIDEBAR ─────────────────────────────────────── -->
<aside class="sidebar">
  <div class="s-logo">
    <div class="s-ring">
      <img src="../uploads/logo/TMCNHS-Logo.png" alt="TMCNHS" onerror="this.style.display='none'">
    </div>
    <div class="s-text">
      <h2>Trece Martires City<br>National High School</h2>
      <span>Administrator Portal</span>
    </div>
  </div>
  <nav class="s-nav">
    <div class="nav-sect">Main</div>
    <a class="nav-item" href="admin_dashboard.php"><span class="ni">📊</span>Dashboard</a>
    <a class="nav-item" href="manage_personnel.php"><span class="ni">👩‍🏫</span>Manage Personnel</a>
    <a class="nav-item" href="org_management.php"><span class="ni">🏛️</span>Org Management</a>
    <a class="nav-item" href="documents_library.php"><span class="ni">📁</span>Documents</a>
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
    <a class="nav-item active" href="admin_notifications.php">
      <span class="ni">🔔</span>ID Notifications
      <?php if ($unread_total > 0): ?>
        <span class="n-badge" id="sidebarBadge"><?= $unread_total ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <div class="s-foot">
    <div class="u-chip">
      <div class="u-av">🛡️</div>
      <div class="u-info">
        <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
        <small>Administrator</small>
      </div>
      <a href="../logout.php" class="logout" title="Logout">⏏</a>
    </div>
  </div>
</aside>

<!-- ─── MAIN ────────────────────────────────────────── -->
<main class="main">
  <div class="topbar">
    <div class="tb-left">
      <div class="tb-icon">🔔</div>
      <div class="tb-titles">
        <h1>
          ID Notifications
          <?php if ($unread_total > 0): ?>
            <span class="unread-pill" id="topbarPill"><?= $unread_total ?> unread</span>
          <?php endif; ?>
        </h1>
        <p>PRC licenses · Professional IDs · Expiry tracking · Real-time alerts</p>
      </div>
    </div>
    <div class="tb-actions">
      <?php if ($unread_total > 0): ?>
        <form method="POST" style="margin:0">
          <input type="hidden" name="action" value="mark_all_read">
          <button type="submit" class="btn btn-ok btn-sm">✓ Mark all read</button>
        </form>
      <?php endif; ?>
      <form method="POST" style="margin:0" onsubmit="return confirmClear()">
        <input type="hidden" name="action" value="clear_read">
        <button type="submit" class="btn btn-del btn-sm">🗑 Clear read</button>
      </form>
    </div>
  </div>

  <div class="page">
    <?php if (isset($_GET['marked'])): ?>
      <div class="flash flash-ok">✅ All notifications marked as read.</div>
    <?php endif; ?>
    <?php if (isset($_GET['cleared'])): ?>
      <div class="flash flash-ok">🗑 Read notifications cleared.</div>
    <?php endif; ?>

    <!-- LIVE RIBBON -->
    <div class="live-ribbon conn" id="liveRibbon">
      <div class="lr-left">
        <div class="lr-dot-wrap">
          <div class="lr-dot"></div>
          <div class="lr-ring"></div>
        </div>
        <div class="lr-text">
          <strong id="liveStatusTxt">Connecting…</strong>
          <span id="liveSubTxt"></span>
        </div>
      </div>
      <div class="lr-right">
        <span>Last checked: <strong id="lastChecked">—</strong></span>
        <div class="poll-track"><div class="poll-fill" id="pollFill"></div></div>
      </div>
    </div>

    <!-- NEW BANNER -->
    <div class="new-banner" id="newBanner">
      <div class="nb-left">
        🆕
        <span id="nbText">New notifications arrived</span>
        <span class="nb-badge" id="nbCount">0</span>
      </div>
      <button class="nb-btn" onclick="window.location.href=window.location.href">↻ Refresh</button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
      <div class="s-card c-all">
        <div class="s-bar"></div>
        <div class="s-top"><div class="s-ico">📋</div><span class="s-delta" id="deltaAll">+0</span></div>
        <div class="s-num" id="statAll"><?= $cnt['all'] ?></div>
        <div class="s-label">Total Notifications</div>
      </div>
      <div class="s-card c-upd">
        <div class="s-bar"></div>
        <div class="s-top"><div class="s-ico">✅</div></div>
        <div class="s-num" id="statUpd"><?= $cnt['id_updated'] ?></div>
        <div class="s-label">ID Updates</div>
      </div>
      <div class="s-card c-exp">
        <div class="s-bar"></div>
        <div class="s-top"><div class="s-ico">⚠️</div></div>
        <div class="s-num" id="statExp"><?= $cnt['expiring_soon'] ?></div>
        <div class="s-label">Expiring Soon</div>
      </div>
      <div class="s-card c-xpd">
        <div class="s-bar"></div>
        <div class="s-top"><div class="s-ico">🚨</div></div>
        <div class="s-num" id="statXpd"><?= $cnt['expired'] ?></div>
        <div class="s-label">Already Expired</div>
      </div>
    </div>

    <!-- TOOLBAR -->
    <div class="toolbar">
      <div class="tab-group">
        <a class="ftab t-a <?= $filter_type==='all'          ?'on':'' ?>" href="<?= ftab_url('all',          $filter_read) ?>">📋 All <span class="ftab-ct" id="fb-all"><?= $cnt['all'] ?></span></a>
        <a class="ftab t-u <?= $filter_type==='id_updated'   ?'on':'' ?>" href="<?= ftab_url('id_updated',   $filter_read) ?>">✅ Updated <span class="ftab-ct"><?= $cnt['id_updated'] ?></span></a>
        <a class="ftab t-e <?= $filter_type==='expiring_soon'?'on':'' ?>" href="<?= ftab_url('expiring_soon',$filter_read) ?>">⚠️ Expiring <span class="ftab-ct"><?= $cnt['expiring_soon'] ?></span></a>
        <a class="ftab t-x <?= $filter_type==='expired'      ?'on':'' ?>" href="<?= ftab_url('expired',      $filter_read) ?>">🚨 Expired <span class="ftab-ct"><?= $cnt['expired'] ?></span></a>
      </div>
      <div class="read-pills">
        <a class="rpill <?= $filter_read==='all'   ?'on':'' ?>" href="<?= ftab_url($filter_type,'all')    ?>">All</a>
        <a class="rpill <?= $filter_read==='unread'?'on':'' ?>" href="<?= ftab_url($filter_type,'unread') ?>">Unread</a>
        <a class="rpill <?= $filter_read==='read'  ?'on':'' ?>" href="<?= ftab_url($filter_type,'read')   ?>">Read</a>
      </div>
    </div>

    <!-- NOTIFICATION LIST -->
    <div class="notif-shell">
      <?php
        $TICONS  = ['id_updated'=>'✅','expiring_soon'=>'⚠️','expired'=>'🚨'];
        $TLABELS = ['id_updated'=>'Updated','expiring_soon'=>'Expiring Soon','expired'=>'Expired'];
        $AVCS    = ['av0','av1','av2','av3','av4'];
      ?>

      <?php if ($rows && $rows->num_rows > 0): ?>
        <div class="notif-list" id="notifList">
          <?php $idx=0; while($n=$rows->fetch_assoc()):
            $init   = mb_strtoupper(mb_substr($n['full_name']??'?',0,1));
            $avc    = $AVCS[$n['personnel_id'] % 5];
            $unread = !$n['is_read'];
            $ago    = human_time_diff(strtotime($n['created_at']));
            $delay  = 'animation-delay:'.($idx*0.04).'s';
            $idx++;
          ?>
          <div class="nc type-<?= $n['type'] ?> <?= $unread?'unread':'' ?> arrive"
               id="nc-<?= $n['id'] ?>" style="<?= $delay ?>">

            <div class="nc-av <?= $avc ?>">
              <?php if(!empty($n['profile_image'])): ?>
                <img src="../<?= htmlspecialchars($n['profile_image']) ?>"
                     alt="" onerror="this.parentElement.textContent='<?= $init ?>'">
              <?php else: ?><?= $init ?><?php endif; ?>
            </div>

            <div class="nc-ico <?= $n['type'] ?>"><?= $TICONS[$n['type']]??'🔔' ?></div>

            <div class="nc-body">
              <div class="nc-name">
                <?= htmlspecialchars($n['full_name']??'Unknown') ?>
                <?php if($unread): ?><span class="new-pill">NEW</span><?php endif; ?>
              </div>
              <div class="nc-msg"><?= htmlspecialchars($n['message']) ?></div>
              <div class="nc-meta">
                <span class="nc-time">🕐 <?= date('M d, Y · h:i A', strtotime($n['created_at'])) ?> · <?= $ago ?></span>
                <span class="nc-tag <?= $n['id_kind'] ?>"><?= $n['id_kind']==='prc'?'🪪 PRC':'🏷 Other ID' ?></span>
                <span class="nc-tag <?= $n['type'] ?>"><?= $TLABELS[$n['type']]??$n['type'] ?></span>
                <?php if(!empty($n['subject'])): ?><span class="nc-pos"><?= htmlspecialchars($n['subject']) ?></span><?php endif; ?>
              </div>
            </div>

            <div class="nc-right">
              <?php if($unread): ?><div class="u-dot" id="dot-<?= $n['id'] ?>"></div><?php endif; ?>
              <div class="nc-acts">
                <?php if($unread): ?>
                  <button class="nc-btn read" id="rb-<?= $n['id'] ?>"
                          onclick="markRead(<?= $n['id'] ?>)">✓ Read</button>
                <?php endif; ?>
                <button class="nc-btn del" onclick="delNotif(<?= $n['id'] ?>)">🗑</button>
              </div>
            </div>
          </div>
          <?php endwhile; ?>
        </div>

        <div class="pager">
          <div class="pager-info">
            Showing <strong><?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?></strong>
            of <strong><?= $total ?></strong> notification<?= $total!==1?'s':'' ?>
          </div>
          <div class="pager-btns">
            <a class="pg <?= $page<=1?'off':'' ?>" href="<?= ftab_url($filter_type,$filter_read,max(1,$page-1)) ?>">‹</a>
            <?php for($pi=max(1,$page-2);$pi<=min($pages,$page+2);$pi++): ?>
              <a class="pg <?= $pi===$page?'on':'' ?>" href="<?= ftab_url($filter_type,$filter_read,$pi) ?>"><?= $pi ?></a>
            <?php endfor; ?>
            <a class="pg <?= $page>=$pages?'off':'' ?>" href="<?= ftab_url($filter_type,$filter_read,min($pages,$page+1)) ?>">›</a>
          </div>
        </div>

      <?php else: ?>
        <div class="empty-area">
          <span class="empty-emo"><?= $filter_type==='expired'?'🎉':($filter_type==='expiring_soon'?'✅':'📭') ?></span>
          <h3><?= $filter_type==='expired'?'No expired IDs — great!':($filter_type==='expiring_soon'?'Nothing expiring soon':'No notifications yet') ?></h3>
          <p>
            <?php if($filter_type!=='all'||$filter_read!=='all'): ?>
              Nothing matches this filter. <a href="admin_notifications.php">Clear all filters</a>
            <?php else: ?>
              Notifications appear automatically when personnel save IDs or licenses approach expiry.
            <?php endif; ?>
          </p>
        </div>
      <?php endif; ?>
    </div>
  </div><!-- /page -->
</main>

<!-- CONFIRM MODAL -->
<div class="mbg" id="modal">
  <div class="mbox">
    <span class="m-emo" id="mEmo">🗑️</span>
    <h3 id="mTitle">Delete?</h3>
    <p id="mBody">This cannot be undone.</p>
    <div class="m-btns">
      <button class="btn btn-ghost" onclick="closeModal()">Cancel</button>
      <button class="btn btn-del"  id="mOk">Delete</button>
    </div>
  </div>
</div>

<!-- TOAST ROOT -->
<div id="toasts"></div>

<script>
/* ─── TOAST ───────────────────────────────────────── */
function toast(msg, type='ok') {
  const icons = {ok:'✅',err:'❌',inf:'💡'};
  const el = document.createElement('div');
  el.className = `t-item ${type}`;
  el.innerHTML = `<span>${icons[type]||'ℹ️'}</span><span>${msg}</span>`;
  document.getElementById('toasts').appendChild(el);
  requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
  setTimeout(() => { el.classList.remove('show'); setTimeout(()=>el.remove(),380); }, 3200);
}

/* ─── MODAL ───────────────────────────────────────── */
let _cb = null;
function openModal(emo,title,body,cb){
  document.getElementById('mEmo').textContent=emo;
  document.getElementById('mTitle').textContent=title;
  document.getElementById('mBody').textContent=body;
  _cb=cb;
  document.getElementById('modal').classList.add('open');
}
function closeModal(){ document.getElementById('modal').classList.remove('open'); _cb=null; }
document.getElementById('mOk').addEventListener('click',()=>{ if(_cb)_cb(); closeModal(); });
document.getElementById('modal').addEventListener('click',e=>{ if(e.target===e.currentTarget)closeModal(); });
function confirmClear(){
  openModal('🧹','Clear read notifications?','All read notifications will be permanently deleted.',
    ()=>document.querySelector('[name="action"][value="clear_read"]').closest('form').submit()
  );
  return false;
}

/* ─── BADGE SYNC ──────────────────────────────────── */
function syncBadges(n){
  const sb=document.getElementById('sidebarBadge');
  const tp=document.getElementById('topbarPill');
  if(n>0){ if(sb)sb.textContent=n; if(tp)tp.innerHTML=`${n} unread`; }
  else    { sb?.remove(); tp?.remove(); }
}

/* ─── MARK READ ───────────────────────────────────── */
async function markRead(id){
  const btn=document.getElementById(`rb-${id}`);
  if(btn){btn.disabled=true;btn.textContent='…';}
  try{
    const res=await fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=mark_read&id=${id}`});
    const d=await res.json(); if(!d.ok) throw 0;
    const card=document.getElementById(`nc-${id}`);
    card?.classList.remove('unread');
    document.getElementById(`dot-${id}`)?.remove();
    card?.querySelector('.new-pill')?.remove();
    btn?.remove();
    syncBadges(d.unread_count);
    toast('Marked as read');
  } catch{ toast('Failed to mark read','err'); if(btn){btn.disabled=false;btn.textContent='✓ Read';} }
}

/* ─── DELETE ──────────────────────────────────────── */
function delNotif(id){
  openModal('🗑️','Delete this notification?','This cannot be undone.',async()=>{
    try{
      const res=await fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:`action=delete&id=${id}`});
      const d=await res.json(); if(!d.ok) throw 0;
      const card=document.getElementById(`nc-${id}`);
      if(card){
        card.style.transition='all .3s ease';
        card.style.opacity='0';card.style.transform='translateX(24px)';
        card.style.maxHeight=card.offsetHeight+'px';
        setTimeout(()=>{card.style.maxHeight='0';card.style.padding='0';card.style.margin='0';card.style.border='none';},160);
        setTimeout(()=>card.remove(),480);
      }
      syncBadges(d.unread_count);
      toast('Deleted','inf');
    } catch{ toast('Failed to delete','err'); }
  });
}

/* ─── REAL-TIME POLLING ───────────────────────────── */
const POLL_MS   = 15000;
const TICOS     = {id_updated:'✅',expiring_soon:'⚠️',expired:'🚨'};
const TLBLS     = {id_updated:'Updated',expiring_soon:'Expiring Soon',expired:'Expired'};
const AVCS      = ['av0','av1','av2','av3','av4'];

let serverTime  = <?= json_encode($server_time) ?>;
let pollTimer   = null;
let barTimer    = null;
let newCount    = 0;
let retries     = 0;

const ribbon    = document.getElementById('liveRibbon');
const statusTxt = document.getElementById('liveStatusTxt');
const subTxt    = document.getElementById('liveSubTxt');
const lastEl    = document.getElementById('lastChecked');
const fillEl    = document.getElementById('pollFill');
const banner    = document.getElementById('newBanner');

function setStatus(s){
  ribbon.className='live-ribbon '+s;
  if(s==='live'){ statusTxt.textContent='Live'; subTxt.textContent=' · Polls every 15s'; }
  else if(s==='conn'){ statusTxt.textContent='Connecting…'; subTxt.textContent=''; }
  else { statusTxt.textContent='Connection issue'; subTxt.textContent=' · Retrying…'; }
}
function nowStr(){ return new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
function startBar(){
  clearInterval(barTimer); let pct=0;
  fillEl.style.transition='none'; fillEl.style.width='0%';
  barTimer=setInterval(()=>{
    pct+=100/(POLL_MS/300); if(pct>=100){pct=100;clearInterval(barTimer);}
    fillEl.style.transition='width .3s linear'; fillEl.style.width=pct+'%';
  },300);
}
function buildCard(n){
  const init=(n.full_name||'?')[0].toUpperCase();
  const avc=AVCS[(parseInt(n.personnel_id)||0)%5];
  const unrd=!n.is_read;
  const img=n.profile_image
    ?`<img src="../${n.profile_image}" alt="" onerror="this.parentElement.textContent='${init}'">`
    :init;
  return `<div class="nc type-${n.type} ${unrd?'unread':''} arrive" id="nc-${n.id}">
    <div class="nc-av ${avc}">${img}</div>
    <div class="nc-ico ${n.type}">${TICOS[n.type]||'🔔'}</div>
    <div class="nc-body">
      <div class="nc-name">${(n.full_name||'Unknown').replace(/</g,'&lt;')}${unrd?'<span class="new-pill">NEW</span>':''}</div>
      <div class="nc-msg">${(n.message||'').replace(/</g,'&lt;')}</div>
      <div class="nc-meta">
        <span class="nc-time">🕐 just now</span>
        <span class="nc-tag ${n.id_kind}">${n.id_kind==='prc'?'🪪 PRC':'🏷 Other ID'}</span>
        <span class="nc-tag ${n.type}">${TLBLS[n.type]||n.type}</span>
        ${n.subject?`<span class="nc-pos">${(n.subject||'').replace(/</g,'&lt;')}</span>`:''}
      </div>
    </div>
    <div class="nc-right">
      ${unrd?`<div class="u-dot" id="dot-${n.id}"></div>`:''}
      <div class="nc-acts">
        ${unrd?`<button class="nc-btn read" id="rb-${n.id}" onclick="markRead(${n.id})">✓ Read</button>`:''}
        <button class="nc-btn del" onclick="delNotif(${n.id})">🗑</button>
      </div>
    </div>
  </div>`;
}

async function poll(){
  setStatus('conn');
  try{
    const res=await fetch(`admin_notifications.php?action=poll&since=${encodeURIComponent(serverTime)}`);
    if(!res.ok) throw new Error('http');
    const d=await res.json();
    retries=0; serverTime=d.server_time;
    setStatus('live'); lastEl.textContent=nowStr(); startBar();

    if(d.notifications && d.notifications.length){
      const filterType = <?= json_encode($filter_type) ?>;
      const filterRead = <?= json_encode($filter_read) ?>;
      const list = document.getElementById('notifList');
      // Only inject cards live when showing unfiltered "all" view on page 1
      if(list && filterType === 'all' && filterRead !== 'read' && <?= $page ?> === 1){
        d.notifications.forEach(n=>{
          if(document.getElementById('nc-'+n.id)) return;
          list.insertAdjacentHTML('afterbegin',buildCard(n));
        });
      }
      newCount+=d.notifications.length;
      banner.classList.add('on');
      document.getElementById('nbText').textContent=`${newCount} new notification${newCount!==1?'s':''} arrived`;
      document.getElementById('nbCount').textContent=newCount;
      document.querySelector('.nb-btn').onclick = () => { window.location.href = window.location.href; };
    }

    if(d.counts){
      const old=parseInt(document.getElementById('statAll').textContent||'0');
      const neo=d.counts['all'];
      if(neo>old){
        const delta=document.getElementById('deltaAll');
        delta.textContent=`+${neo-old}`; delta.classList.add('on');
        setTimeout(()=>delta.classList.remove('on'),4000);
      }
      document.getElementById('statAll').textContent = neo;
      document.getElementById('statUpd').textContent = d.counts['id_updated'];
      document.getElementById('statExp').textContent = d.counts['expiring_soon'];
      document.getElementById('statXpd').textContent = d.counts['expired'];
    }
    syncBadges(d.unread_count);
  } catch{
    retries++;
    setStatus('err');
    if(retries>=5){ clearInterval(pollTimer); toast('Real-time connection lost. Refresh to reconnect.','err'); }
  }
}

// Boot: first poll after 2 s
setTimeout(()=>{
  setStatus('live'); lastEl.textContent=nowStr(); startBar();
  poll(); pollTimer=setInterval(poll,POLL_MS);
},2000);

// Pause on hidden tab, resume on focus
document.addEventListener('visibilitychange',()=>{
  if(document.hidden){ clearInterval(pollTimer); clearInterval(barTimer); }
  else{ poll(); pollTimer=setInterval(poll,POLL_MS); }
});
</script>
</body>
</html>