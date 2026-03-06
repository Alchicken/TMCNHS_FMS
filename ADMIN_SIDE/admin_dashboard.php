<?php
// admin_dashboard.php — ADMIN DASHBOARD (Enhanced)
require_once 'admin_shared.php';

$msg = $err = '';

function human_time_diff(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
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

/* ── SIDEBAR FOOTER ── (MODIFIED) ─────────────────────
   Added a credits trigger link above the user chip.
   The .sidebar-footer now stacks two things:
   1. .credits-trigger — small "© Credits" button
   2. .user-chip       — existing logout row
────────────────────────────────────────────────────── */
.sidebar-footer { padding: 12px 12px 16px; border-top: 1px solid rgba(255,255,255,0.15); display: flex; flex-direction: column; gap: 8px; }
.user-chip { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: rgba(255,255,255,0.12); border-radius: 10px; }
.avatar { width: 34px; height: 34px; background: rgba(255,255,255,0.25); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.user-info { flex: 1; overflow: hidden; }
.user-info strong { display: block; font-size: 12px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-info small { color: rgba(255,255,255,0.55); font-size: 10px; font-family: 'JetBrains Mono', monospace; }
.logout-btn { color: rgba(255,255,255,0.55); font-size: 16px; text-decoration: none; }
.logout-btn:hover { color: #fff; }

/* Credits trigger button inside sidebar footer */
.credits-trigger {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  padding: 8px 12px;
  background: rgba(255,255,255,0.07);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
  width: 100%;
}
.credits-trigger:hover {
  background: rgba(255,255,255,0.14);
  border-color: rgba(212,168,75,0.4);
}
.credits-trigger span {
  color: rgba(255,255,255,0.45);
  font-size: 10px;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 1px;
  text-transform: uppercase;
  transition: color 0.2s;
}
.credits-trigger:hover span { color: rgba(212,168,75,0.9); }
.credits-trigger .ct-icon { font-size: 13px; }

/* ── MAIN ────────────────────────────────────────────── */
.main { margin-left: var(--sidebar); flex: 1; min-width: 0; }
.topbar { padding: 16px 32px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: var(--surface); position: sticky; top: 0; z-index: 50; gap: 16px; }
.topbar-left h1 { font-size: 17px; font-weight: 700; }
.topbar-left p  { color: var(--muted); font-size: 12px; margin-top: 1px; }
.topbar-right   { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.datetime-chip  { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 7px 14px; font-size: 12px; font-family: 'JetBrains Mono', monospace; color: var(--muted); white-space: nowrap; }
.datetime-chip span { color: var(--text); font-weight: 600; }
.page { padding: 24px 32px; }

/* ── ATTENTION RIBBON ─────────────────────────────────── */
.ribbon { background: linear-gradient(135deg, #fff7ed, #fef3c7); border: 1px solid #fbbf24; border-radius: 12px; padding: 12px 18px; margin-bottom: 22px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.ribbon-icon { font-size: 20px; flex-shrink: 0; }
.ribbon-text { flex: 1; font-size: 13px; font-weight: 500; color: #92400e; }
.ribbon-text strong { color: #78350f; }
.ribbon-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.rpill { padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.rpill-red    { background: rgba(200,16,46,0.12); color: var(--danger); }
.rpill-orange { background: rgba(217,119,6,0.12); color: var(--warn); }
.rpill-purple { background: rgba(123,31,162,0.12); color: var(--purple); }

/* ── STATS GRID ──────────────────────────────────────── */
.stats-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 22px; }
.stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px 22px; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
.stat-card.red::before    { background: linear-gradient(90deg, var(--accent2), var(--accent)); }
.stat-card.green::before  { background: linear-gradient(90deg, #059669, #10b981); }
.stat-card.blue::before   { background: linear-gradient(90deg, #1d4ed8, #3b82f6); }
.stat-card.orange::before { background: linear-gradient(90deg, #d97706, #f59e0b); }
.stat-card.purple::before { background: linear-gradient(90deg, #7b1fa2, #a855f7); }
.stat-split { display: flex; align-items: flex-start; justify-content: space-between; }
.stat-icon  { font-size: 26px; }
.stat-badge { font-size: 10px; font-family: 'JetBrains Mono', monospace; padding: 3px 9px; border-radius: 20px; }
.stat-badge.green  { background: rgba(5,150,105,0.12); color: var(--success); }
.stat-badge.orange { background: rgba(217,119,6,0.12); color: var(--warn); }
.stat-badge.red    { background: rgba(200,16,46,0.12); color: var(--danger); }
.stat-badge.blue   { background: rgba(29,78,216,0.12); color: #1d4ed8; }
.stat-badge.purple { background: rgba(123,31,162,0.12); color: var(--purple); }
.stat-num   { font-size: 34px; font-weight: 700; font-family: 'JetBrains Mono', monospace; line-height: 1; margin-top: 12px; }
.stat-label { color: var(--muted); font-size: 12px; margin-top: 5px; }
.stat-sub   { font-size: 11px; color: var(--muted); margin-top: 3px; font-family: 'JetBrains Mono', monospace; }

/* ── SECTION HEADER ──────────────────────────────────── */
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.section-head h2 { font-size: 15px; font-weight: 600; }

/* ── CHARTS ROW ──────────────────────────────────────── */
.charts-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 22px; }
.chart-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.chart-card h3 { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
.chart-card p  { font-size: 11px; color: var(--muted); margin-bottom: 16px; }
.chart-inner   { position: relative; }

/* ── QUICK ACTIONS ───────────────────────────────────── */
.actions-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 22px; }
.actions-row-2 { grid-template-columns: repeat(2,1fr); }
.action-btn  { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; cursor: pointer; text-decoration: none; color: var(--text); transition: all 0.2s; }
.action-btn:hover { border-color: var(--accent); background: rgba(200,16,46,0.03); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(200,16,46,0.08); }
.action-btn .ab-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.action-btn .ab-icon.red    { background: rgba(200,16,46,0.12); }
.action-btn .ab-icon.green  { background: rgba(5,150,105,0.12); }
.action-btn .ab-icon.purple { background: rgba(123,31,162,0.12); }
.action-btn strong { display: block; font-size: 13px; font-weight: 600; }
.action-btn span   { display: block; font-size: 11px; color: var(--muted); }

/* ── BOTTOM GRID ─────────────────────────────────────── */
.bottom-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }

/* ── ACTIVITY FEED ───────────────────────────────────── */
.feed-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.feed-item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.feed-item:last-child { border-bottom: none; }
.feed-dot  { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; margin-top: 2px; }
.feed-dot.green  { background: rgba(5,150,105,0.12); }
.feed-dot.red    { background: rgba(200,16,46,0.12); }
.feed-dot.purple { background: rgba(123,31,162,0.12); }
.feed-dot.blue   { background: rgba(29,78,216,0.12); }
.feed-body strong { display: block; font-size: 13px; }
.feed-body span   { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.feed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0 24px; }
.feed-grid .feed-item { border-bottom: 1px solid var(--border); }
.feed-grid .feed-item:nth-last-child(-n+3) { border-bottom: none; }

/* ── PAGINATION ───────────────────────────────────────── */
.pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid var(--border); background: var(--surface); border-radius: 0 0 14px 14px; flex-wrap: wrap; gap: 10px; }
.pagination-info { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.pagination-info span { color: var(--text); font-weight: 600; }
.pagination-btns { display: flex; align-items: center; gap: 4px; }
.pg-btn { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 13px; font-family: 'JetBrains Mono', monospace; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s; font-weight: 500; }
.pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); background: rgba(200,16,46,0.05); }
.pg-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.pg-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.pg-arrow { font-size: 15px; }

/* ── ALERT ITEMS ─────────────────────────────────────── */
.alerts-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 20px; }
.alert-item { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 10px; margin-bottom: 8px; }
.alert-item:last-child { margin-bottom: 0; }
.alert-item.warn-item   { background: #fffbeb; border: 1px solid #fde68a; }
.alert-item.danger-item { background: #fff1f2; border: 1px solid #fecdd3; }
.alert-item.info-item   { background: #f0f9ff; border: 1px solid #bae6fd; }
.alert-item .ai-icon { font-size: 18px; flex-shrink: 0; }
.alert-item .ai-body strong { display: block; font-size: 12px; font-weight: 600; }
.alert-item .ai-body span   { font-size: 11px; color: var(--muted); }

/* ── BUTTONS ─────────────────────────────────────────── */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 18px; border-radius: 9px; font-family: 'DM Sans', sans-serif; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; text-decoration: none; }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.btn-ghost { background: var(--surface); color: var(--text); border: 1px solid var(--border); }
.btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
.btn-view { background: rgba(200,16,46,0.12); color: var(--accent); border: 1px solid rgba(200,16,46,0.3); }
.btn-view:hover { background: rgba(200,16,46,0.2); }
.btn-download { background: rgba(5,150,105,0.15); color: var(--success); border: 1px solid rgba(5,150,105,0.3); }
.btn-download:hover { background: rgba(5,150,105,0.25); }
.btn-purple { background: rgba(167,139,250,0.12); color: var(--purple); border: 1px solid rgba(167,139,250,0.3); }
.btn-accent { background: var(--accent); color: white; }
.btn-accent:hover { background: var(--accent2); }

/* ── TABLE ───────────────────────────────────────────── */
.table-wrap { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead { background: var(--surface); }
th { padding: 11px 14px; text-align: left; font-size: 10.5px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
td { padding: 12px 14px; border-top: 1px solid var(--border); font-size: 13px; }
tr:hover td { background: rgba(200,16,46,0.03); }
.badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.badge-active    { background: rgba(5,150,105,0.15); color: var(--success); }
.badge-inactive  { background: rgba(200,16,46,0.1); color: var(--danger); }
.badge-teaching  { background: rgba(29,78,216,0.1); color: #1d4ed8; }
.badge-nteaching { background: rgba(123,31,162,0.1); color: var(--purple); }
.teacher-name { display: flex; align-items: center; gap: 10px; }
.t-avatar   { width: 34px; height: 34px; background: linear-gradient(135deg,#c8102e,#9b0c22); border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: white; flex-shrink: 0; }
.t-avatar-b { background: linear-gradient(135deg, var(--success), #059669); }
.t-avatar-c { background: linear-gradient(135deg, #d97706, #ef4444); }
.t-info strong { display: block; font-size: 13px; }
.t-info span   { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.teacher-link { cursor: pointer; }
.teacher-link:hover .t-info strong { color: var(--accent); text-decoration: underline; text-underline-offset: 3px; }
.doc-count-badge { display: inline-flex; align-items: center; justify-content: center; background: rgba(200,16,46,0.15); color: var(--accent); border-radius: 20px; font-size: 11px; font-family: 'JetBrains Mono', monospace; padding: 2px 10px; }

/* ── ALERT BANNER ─────────────────────────────────────── */
.alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.alert-success { background: rgba(5,150,105,0.1); border: 1px solid rgba(5,150,105,0.3); color: var(--success); }
.alert-danger  { background: rgba(255,107,107,0.1); border: 1px solid rgba(200,16,46,0.3); color: var(--danger); }

/* ── SEARCH ──────────────────────────────────────────── */
.search-wrap { position: relative; margin-bottom: 14px; }
.search-wrap .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; opacity: 0.5; }
.search-input { width: 100%; padding: 11px 16px 11px 42px; background: var(--card); border: 1px solid var(--border); border-radius: 12px; color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 13.5px; outline: none; transition: border-color 0.2s; }
.search-input::placeholder { color: var(--muted); }
.search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(200,16,46,0.12); }
.search-clear { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); font-size: 16px; cursor: pointer; display: none; }
.search-clear:hover { color: var(--danger); }
.search-results-count { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-bottom: 12px; }
.search-results-count span { color: var(--accent); font-weight: 600; }
.no-results-row td { text-align: center; color: var(--muted); padding: 48px 20px !important; }
.no-results-row .nr-icon { font-size: 32px; display: block; margin-bottom: 8px; }
.hl { background: rgba(200,16,46,0.18); color: var(--accent2); border-radius: 2px; padding: 0 2px; }

/* ── APPOINTMENT BADGE ────────────────────────────────── */
.appt-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10.5px; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
.appt-Regular    { background: rgba(5,150,105,0.12); color: #059669; }
.appt-Newly.Hired { background: rgba(29,78,216,0.12); color: #1d4ed8; }
.appt-Transferee { background: rgba(217,119,6,0.12); color: #d97706; }
.appt-Reinstated { background: rgba(123,31,162,0.12); color: var(--purple); }

/* ── TEACHER PROFILE MODAL ───────────────────────────── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.78); z-index: 1000; align-items: flex-start; justify-content: center; backdrop-filter: blur(5px); padding: 24px 20px; overflow-y: auto; }
.modal-overlay.open { display: flex; }
@keyframes slideIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
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
.pm-close { margin-left: auto; background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; flex-shrink: 0; transition: color 0.2s; padding: 4px 8px; }
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
.leave-log-row { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; margin-bottom: 10px; transition: border-color 0.2s; }
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

/* ══════════════════════════════════════════════════════
   CREDITS MODAL STYLES  ← NEW
   All styles prefixed with .cm- or .credits- so they
   don't collide with any existing class names.
══════════════════════════════════════════════════════ */
.credits-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10,2,4,0.82);
  z-index: 2000;              /* above sidebar (z:100) and teacher modal (z:1000) */
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.credits-overlay.open {
  display: flex;
  animation: creditsOverlayIn 0.25s ease both;
}
@keyframes creditsOverlayIn {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.credits-modal {
  background: #ffffff;
  border-radius: 24px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: creditsModalIn 0.35s cubic-bezier(0.22,1,0.36,1) both;
  box-shadow: 0 32px 80px rgba(0,0,0,0.45), 0 0 0 1px rgba(200,16,46,0.1);
}
@keyframes creditsModalIn {
  from { opacity: 0; transform: translateY(28px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0)    scale(1); }
}
/* Header */
.cm-header {
  background: linear-gradient(135deg, #6b0518 0%, var(--accent2) 45%, var(--accent) 100%);
  padding: 28px 28px 22px;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}
.cm-header::before {
  content: '';
  position: absolute; inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
  background-size: 22px 22px;
  pointer-events: none;
}
.cm-header::after {
  content: '';
  position: absolute;
  top: -40px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(212,168,75,0.15) 0%, transparent 70%);
  pointer-events: none;
}
.cm-header-inner { position: relative; z-index: 1; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
.cm-eyebrow {
  display: inline-flex; align-items: center; gap: 7px;
  background: rgba(212,168,75,0.18); border: 1px solid rgba(212,168,75,0.4);
  border-radius: 999px; padding: 4px 14px;
  color: #f0d080; font-size: 10px; font-family: 'JetBrains Mono', monospace;
  letter-spacing: 2px; text-transform: uppercase; margin-bottom: 8px;
}
.cm-eyebrow::before {
  content: ''; width: 5px; height: 5px; border-radius: 50%;
  background: #d4a84b; box-shadow: 0 0 5px #d4a84b;
  animation: cmPulse 2s ease-in-out infinite;
}
@keyframes cmPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:0.5;transform:scale(0.8)} }
.cm-title { font-family: 'DM Serif Display', serif; color: #fff; font-size: 22px; font-weight: 400; line-height: 1.2; margin-bottom: 4px; }
.cm-subtitle { color: rgba(255,255,255,0.5); font-size: 11.5px; font-family: 'JetBrains Mono', monospace; }
.cm-close-btn {
  background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
  border-radius: 10px; color: rgba(255,255,255,0.8);
  width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
  font-size: 15px; cursor: pointer; transition: all 0.2s; flex-shrink: 0;
}
.cm-close-btn:hover { background: rgba(255,255,255,0.22); color: #fff; }
/* Stats strip inside header */
.cm-stats-strip {
  display: flex; gap: 0;
  margin-top: 18px;
  background: rgba(0,0,0,0.2);
  border-radius: 10px; overflow: hidden;
  position: relative; z-index: 1;
  border: 1px solid rgba(255,255,255,0.08);
}
.cm-stat-item { flex: 1; padding: 9px 12px; text-align: center; border-right: 1px solid rgba(255,255,255,0.08); }
.cm-stat-item:last-child { border-right: none; }
.cm-stat-num   { font-family: 'JetBrains Mono', monospace; font-size: 17px; font-weight: 500; color: #f0d080; line-height: 1; }
.cm-stat-label { font-size: 9px; color: rgba(255,255,255,0.4); margin-top: 3px; text-transform: uppercase; letter-spacing: 1px; }
/* Body */
.cm-body { padding: 24px 28px; overflow-y: auto; flex: 1; }
.cm-body::-webkit-scrollbar { width: 4px; }
.cm-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 999px; }
.cm-section-lbl {
  font-size: 10px; font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase; letter-spacing: 2px; color: var(--muted);
  margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.cm-section-lbl::after { content: ''; flex: 1; height: 1px; background: var(--border); }
/* Dev card */
.dev-card {
  display: flex; align-items: center; gap: 13px;
  padding: 12px 14px;
  background: #fff8f8; border: 1.5px solid var(--border);
  border-radius: 13px; margin-bottom: 9px;
  transition: all 0.2s ease; position: relative; overflow: hidden;
}
.dev-card::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: linear-gradient(180deg, var(--accent), var(--accent2));
  opacity: 0; transition: opacity 0.2s;
}
.dev-card:hover { border-color: rgba(200,16,46,0.28); background: #fff; transform: translateX(3px); box-shadow: 0 3px 14px rgba(200,16,46,0.07); }
.dev-card:hover::before { opacity: 1; }
.dev-av {
  width: 44px; height: 44px; border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700; color: #fff;
  flex-shrink: 0; position: relative;
  font-family: 'DM Serif Display', serif;
}
.dev-av.a1 { background: linear-gradient(135deg,#c8102e,#9b0c22); }
.dev-av.a2 { background: linear-gradient(135deg,#9b0c22,#6b0518); }
.dev-av.a3 { background: linear-gradient(135deg,#d97706,#c8102e); }
.dev-av.a4 { background: linear-gradient(135deg,#7b1fa2,#c8102e); }
.dev-av.a5 { background: linear-gradient(135deg,#059669,#9b0c22); }
.dev-av.a6 { background: linear-gradient(135deg,#1d4ed8,#c8102e); }
.lead-star {
  position: absolute; top: -4px; right: -4px;
  width: 15px; height: 15px; background: #d4a84b;
  border-radius: 50%; display: flex; align-items: center; justify-content: center;
  font-size: 7px; border: 2px solid #fff;
}
.dev-name  { font-size: 13.5px; font-weight: 600; color: var(--text); }
.dev-role  { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
.dev-tags  { display: flex; gap: 5px; flex-wrap: wrap; flex-shrink: 0; margin-left: auto; }
.dtag { padding: 2px 8px; border-radius: 999px; font-size: 10px; font-family: 'JetBrains Mono', monospace; border: 1px solid; }
.dtag-red    { background: rgba(200,16,46,0.07);  color: var(--accent);  border-color: rgba(200,16,46,0.2); }
.dtag-gold   { background: rgba(212,168,75,0.1);  color: #9a6f10;        border-color: rgba(212,168,75,0.3); }
.dtag-blue   { background: rgba(29,78,216,0.07);  color: #1d4ed8;        border-color: rgba(29,78,216,0.2); }
.dtag-green  { background: rgba(5,150,105,0.07);  color: #059669;        border-color: rgba(5,150,105,0.2); }
.dtag-purple { background: rgba(123,31,162,0.07); color: var(--purple);  border-color: rgba(123,31,162,0.2); }
/* Tech stack */
.stack-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin-bottom: 20px; }
.stack-item {
  background: #fff8f8; border: 1px solid var(--border);
  border-radius: 10px; padding: 9px 11px;
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 500; color: var(--text);
  transition: border-color 0.18s;
}
.stack-item:hover { border-color: rgba(200,16,46,0.3); }
.stack-item .si { font-size: 15px; flex-shrink: 0; }
/* Acknowledgement box */
.ack-box {
  background: #fff8f8; border: 1px solid var(--border);
  border-radius: 12px; padding: 13px 15px;
  font-size: 13px; color: var(--muted); line-height: 1.6;
}
.ack-box strong { color: var(--text); }
/* Footer */
.cm-footer {
  padding: 14px 28px 18px;
  border-top: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0; flex-wrap: wrap; gap: 10px;
}
.cm-footer-note { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }
.cm-footer-note strong { color: var(--accent); }
.cm-close-footer {
  padding: 8px 20px; border-radius: 9px;
  background: var(--accent); border: none;
  color: #fff; font-family: 'DM Sans', sans-serif;
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background 0.2s;
}
.cm-close-footer:hover { background: var(--accent2); }
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
    <a class="nav-item active" href="admin_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item" href="manage_personnel.php"><span class="ni">👩‍🏫</span> Manage Personnel</a>
    <a class="nav-item" href="org_management.php"><span class="ni">🏛️</span> Org Management</a>
    <a class="nav-item" href="documents_library.php"><span class="ni">📁</span> Documents</a>
  </nav>

  <!-- ── SIDEBAR FOOTER ── -->
  <div class="sidebar-footer">

    <!-- ★ NEW: Credits trigger button -->
    <button class="credits-trigger" onclick="openCredits()">
      <span class="ct-icon">👥</span>
      <span>Development Team</span>
    </button>

    <!-- Existing user chip + logout (unchanged) -->
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

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Dashboard</h1>
      <p>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
    </div>
    <div class="topbar-right">
      <div class="datetime-chip" id="liveClock">—</div>
    </div>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php
      $low_leave_count   = 0;
      $newly_hired_count = 0;
      $inactive_count    = 0;
      $cutoff_date       = date('Y-m-d', strtotime('-90 days'));
      foreach ($teachers_arr as $t) {
          $absent      = $leave_total_absent[$t['id']] ?? 0;
          $leave_left  = max(0, $total_leave_days - $absent);
          if ($leave_left <= 5)                          $low_leave_count++;
          if ($t['status'] === 'inactive')               $inactive_count++;
          if (!empty($t['date_hired']) && $t['date_hired'] >= $cutoff_date) $newly_hired_count++;
      }
      $attention_total = $low_leave_count + $newly_hired_count + $inactive_count;
      $teaching_count     = 0;
      $non_teaching_count = 0;
      foreach ($teachers_arr as $t) {
          if (($t['personnel_level'] ?? 'Teaching') === 'Teaching') $teaching_count++;
          else $non_teaching_count++;
      }
      $appt_counts = [];
      foreach ($teachers_arr as $t) {
          $s = $t['appointment_status'] ?? 'Regular';
          $appt_counts[$s] = ($appt_counts[$s] ?? 0) + 1;
      }
      $dept_counts = [];
      foreach ($teachers_arr as $t) {
          $d = $t['department'] ?: 'Unknown';
          $dept_counts[$d] = ($dept_counts[$d] ?? 0) + 1;
      }
      $pos_counts = [];
      foreach ($teachers_arr as $t) {
          $p = $t['subject'] ?: 'Unknown';
          $pos_counts[$p] = ($pos_counts[$p] ?? 0) + 1;
      }
      arsort($pos_counts);
      $total_docs = 0;
      foreach ($docs_by_teacher as $docs) $total_docs += count($docs);
      $feed_items = [];
      foreach ($teachers_arr as $t) {
          $feed_items[] = ['type'=>'new_personnel','date'=>$t['created_at'],'label'=>$t['full_name'].' was added','sub'=>$t['department']?:'No Department'];
      }
      foreach ($docs_by_teacher as $tid => $docs) {
          foreach ($docs as $d) {
              $feed_items[] = ['type'=>'doc','date'=>$d['uploaded_at'],'label'=>$d['title'].' uploaded','sub'=>$d['file_name']];
          }
      }
      foreach ($leave_by_teacher as $tid => $leave_rows) {
          foreach ($leave_rows as $r) {
              $feed_items[] = ['type'=>'leave','date'=>$r['filed_at'],'label'=>$r['last_name'].', '.$r['first_name'].' filed leave','sub'=>$r['leave_type'].' · '.$r['days_absent'].' day(s)'];
          }
      }
      usort($feed_items, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
      $feed_items = array_slice($feed_items, 0, 6);
    ?>

    <?php if ($attention_total > 0): ?>
    <div class="ribbon">
      <span class="ribbon-icon">🔔</span>
      <div class="ribbon-text">You have <strong><?= $attention_total ?> item<?= $attention_total !== 1 ? 's' : '' ?> needing attention</strong> today.</div>
      <div class="ribbon-pills">
        <?php if ($low_leave_count > 0):   ?><span class="rpill rpill-red"><?= $low_leave_count ?> low leave<?= $low_leave_count!==1?'s':'' ?></span><?php endif; ?>
        <?php if ($newly_hired_count > 0): ?><span class="rpill rpill-orange"><?= $newly_hired_count ?> newly hired</span><?php endif; ?>
        <?php if ($inactive_count > 0):    ?><span class="rpill rpill-purple"><?= $inactive_count ?> inactive</span><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-card red">
        <div class="stat-split"><span class="stat-icon">👩‍🏫</span><span class="stat-badge blue"><?= $teaching_count ?> Teaching</span></div>
        <div class="stat-num"><?= $total_teachers ?></div>
        <div class="stat-label">Total Personnel</div>
        <div class="stat-sub"><?= $non_teaching_count ?> Non-Teaching</div>
      </div>
      <div class="stat-card green">
        <div class="stat-split"><span class="stat-icon">✅</span><span class="stat-badge green">Active</span></div>
        <div class="stat-num"><?= $active_teachers ?></div>
        <div class="stat-label">Active Personnel</div>
        <div class="stat-sub"><?= $inactive_count ?> Inactive</div>
      </div>
      <div class="stat-card orange">
        <div class="stat-split"><span class="stat-icon">📋</span><span class="stat-badge orange"><?= count($appt_counts) ?> types</span></div>
        <div class="stat-num"><?= $appt_counts['Newly Hired'] ?? 0 ?></div>
        <div class="stat-label">Newly Hired</div>
        <div class="stat-sub"><?= $appt_counts['Regular'] ?? 0 ?> Regular · <?= $appt_counts['Transferee'] ?? 0 ?> Transferee</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-split"><span class="stat-icon">📄</span><span class="stat-badge purple">Docs</span></div>
        <div class="stat-num"><?= $total_docs ?></div>
        <div class="stat-label">Total Documents</div>
        <div class="stat-sub"><?= count($docs_by_teacher) ?> personnel with files</div>
      </div>
    </div>

    <div class="section-head"><h2>Quick Actions</h2></div>
    <div class="actions-row actions-row-2" style="margin-bottom:22px">
      <a href="manage_personnel.php?action=add" class="action-btn">
        <div class="ab-icon red">➕</div>
        <div><strong>Add Personnel</strong><span>Register new staff member</span></div>
      </a>
      <a href="documents_library.php?tab=leave" class="action-btn">
        <div class="ab-icon purple">📅</div>
        <div><strong>View Leave Reports</strong><span>See all filed absences</span></div>
      </a>
    </div>

    <div class="charts-row" style="grid-template-columns: 1fr 1fr; align-items: stretch;">
      <div class="chart-card" style="display:flex; flex-direction:column;">
        <h3>Department Distribution</h3>
        <p>Personnel per department</p>
        <div class="chart-inner" style="flex:1; min-height:300px;"><canvas id="deptChart"></canvas></div>
      </div>
      <div class="chart-card" style="display:flex; flex-direction:column;">
        <h3>Positions Breakdown</h3>
        <p>Headcount by position</p>
        <div class="chart-inner" style="flex:1; min-height:300px;"><canvas id="top5Chart"></canvas></div>
      </div>
    </div>

    <div class="feed-card" style="margin-bottom:22px">
      <div class="section-head" style="margin-bottom:14px"><h2>🕐 Recent Activity</h2></div>
      <div class="feed-grid">
        <?php if (empty($feed_items)): ?>
          <div class="no-content"><span class="no-content-icon">📭</span><p>No recent activity.</p></div>
        <?php else: ?>
          <?php foreach ($feed_items as $fi):
            $icon  = ['new_personnel'=>'👤','doc'=>'📄','leave'=>'📋'][$fi['type']] ?? '📌';
            $color = ['new_personnel'=>'green','doc'=>'blue','leave'=>'purple'][$fi['type']] ?? 'red';
            $ago   = human_time_diff(strtotime($fi['date']));
          ?>
          <div class="feed-item">
            <div class="feed-dot <?= $color ?>"><?= $icon ?></div>
            <div class="feed-body">
              <strong><?= htmlspecialchars($fi['label']) ?></strong>
              <span><?= htmlspecialchars($fi['sub']) ?> · <?= $ago ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="section-head">
      <h2>Registered Personnel</h2>
      <a class="btn btn-ghost btn-sm" href="manage_personnel.php">View All →</a>
    </div>

    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" class="search-input" id="dashSearch" placeholder="Search personnel by name, subject, or department…">
      <button class="search-clear" id="dashSearchClear" onclick="clearSearch('dashSearch')">✕</button>
    </div>
    <div class="search-results-count" id="dashCount"></div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Personnel</th><th>Position</th><th>Type</th><th>Department</th>
            <th>Docs</th><th>Leave Left</th><th>Appointment</th><th>Action</th>
          </tr>
        </thead>
        <tbody id="dashTbody">
          <?php foreach ($teachers_arr as $t):
            $colors = ['t-avatar','t-avatar-b','t-avatar-c'];
            $cl = $colors[$t['id'] % 3];
            $tdocs = count($docs_by_teacher[$t['id']] ?? []);
            $t_absent = $leave_total_absent[$t['id']] ?? 0;
            $t_leave_left = max(0, $total_leave_days - $t_absent);
            $level = $t['personnel_level'] ?? 'Teaching';
          ?>
          <tr data-name="<?= htmlspecialchars(strtolower($t['full_name'])) ?>"
              data-subject="<?= htmlspecialchars(strtolower($t['subject'] ?? '')) ?>"
              data-dept="<?= htmlspecialchars(strtolower($t['department'] ?? '')) ?>"
              data-username="<?= htmlspecialchars(strtolower($t['username'])) ?>">
            <td>
              <div class="teacher-name teacher-link" onclick='openTeacherProfile(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>
                <div class="t-avatar <?= $cl ?>"><?= mb_strtoupper(mb_substr($t['full_name'],0,1)) ?></div>
                <div class="t-info">
                  <strong class="searchable-name"><?= htmlspecialchars($t['full_name']) ?></strong>
                  <span>@<?= htmlspecialchars($t['username']) ?></span>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($t['subject'] ?: '—') ?></td>
            <td>
              <span class="badge <?= $level === 'Teaching' ? 'badge-teaching' : 'badge-nteaching' ?>">
                <?= $level === 'Teaching' ? '🎓 Teaching' : '🏢 Non-Teaching' ?>
              </span>
            </td>
            <td><?= htmlspecialchars($t['department'] ?: '—') ?></td>
            <td><span class="doc-count-badge">📄 <?= $tdocs ?></span></td>
            <td>
              <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:<?= $t_leave_left<=5?'var(--danger)':($t_leave_left<=10?'var(--warn)':'var(--success)') ?>">
                <?= $t_leave_left ?>
              </span>
              <span style="color:var(--muted);font-size:11px"> / <?= $total_leave_days ?> days</span>
            </td>
            <td>
              <span class="appt-badge appt-<?= str_replace(' ','',$t['appointment_status']) ?>">
                <?= htmlspecialchars($t['appointment_status']) ?>
              </span>
            </td>
            <td>
              <button class="btn btn-view btn-sm" onclick='openTeacherProfile(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>👁 View</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="pagination-wrap" id="paginationWrap">
        <div class="pagination-info" id="pgInfo"></div>
        <div class="pagination-btns" id="pgBtns"></div>
      </div>
    </div>
  </div>
</main>

<!-- TEACHER PROFILE MODAL (unchanged) -->
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
      <div class="info-grid">
        <div class="info-item"><label>Full Name</label><span id="pmFullName"></span></div>
        <div class="info-item"><label>Username</label><span id="pmUsername" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Email</label><span id="pmEmailInfo"></span></div>
        <div class="info-item"><label>Phone</label><span id="pmPhone"></span></div>
        <div class="info-item"><label>Subject / Position</label><span id="pmSubject"></span></div>
        <div class="info-item"><label>Department</label><span id="pmDepartment"></span></div>
        <div class="info-item"><label>Date Hired</label><span id="pmDateHired" style="font-family:'JetBrains Mono',monospace"></span></div>
        <div class="info-item"><label>Status</label><span id="pmStatus"></span></div>
        <div class="info-item" style="grid-column:1/-1"><label>Address</label><span id="pmAddress"></span></div>
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


<div class="credits-overlay" id="creditsModal" onclick="if(event.target===this)closeCredits()">
  <div class="credits-modal">

    <!-- Header -->
    <div class="cm-header">
      <div class="cm-header-inner">
        <div>
          <div class="cm-eyebrow">Development Team</div>
          <div class="cm-title">Built with dedication</div>
          <div class="cm-subtitle">TMCNHS School Management System · v1.0.0</div>
        </div>
        <button class="cm-close-btn" onclick="closeCredits()">✕</button>
      </div>
      <div class="cm-stats-strip">
        <div class="cm-stat-item">
          <div class="cm-stat-num">3</div>          <!-- ← change to your team size -->
          <div class="cm-stat-label">Developers</div>
        </div>
        <div class="cm-stat-item">
          <div class="cm-stat-num">2026</div>
          <div class="cm-stat-label">Year</div>
        </div>
        <div class="cm-stat-item">
          <div class="cm-stat-num">1.0</div>
          <div class="cm-stat-label">Version</div>
        </div>
        <div class="cm-stat-item">
          <div class="cm-stat-num">PHP</div>
          <div class="cm-stat-label">Stack</div>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div class="cm-body">

      <!-- ── DEVELOPER CARDS — edit names/roles/tags below ── -->
      <div class="cm-section-lbl">👨‍💻 Developers</div>

      <div class="dev-card">
        <div class="dev-av a1">AS<span class="lead-star">★</span></div>
        <div>
          <div class="dev-name">Aldikhen Sarip</div>
          <div class="dev-role">Full Stack</div>
        </div>
        <div class="dev-tags">
          <span class="dtag dtag-gold">Lead</span>
          <span class="dtag dtag-red">Backend</span>
          <span class="dtag dtag-blue">Frontend</span>
        </div>
      </div>

      <div class="dev-card">
        <div class="dev-av a2">JA</div>
        <div>
          <div class="dev-name">Jyrus Agrao</div>
          <div class="dev-role">UI/UX</div>
        </div>
        <div class="dev-tags">
          <span class="dtag dtag-blue">Frontend</span>
          <span class="dtag dtag-purple">UI/UX</span>
        </div>
      </div>

      <div class="dev-card">
        <div class="dev-av a3">JM</div>
        <div>
          <div class="dev-name">Joshua Macatangay</div>
          <div class="dev-role">Backend Developer · Database</div>
        </div>
        <div class="dev-tags">
          <span class="dtag dtag-red">Backend</span>
          <span class="dtag dtag-green">Database</span>
        </div>
      </div>

      <!-- Tech stack -->
      <div class="cm-section-lbl" style="margin-top:20px">🛠️ Built With</div>
      <div class="stack-grid">
        <div class="stack-item"><span class="si">🐘</span> PHP 8</div>
        <div class="stack-item"><span class="si">🗄️</span> MySQL</div>
        <div class="stack-item"><span class="si">🎨</span> CSS3</div>
        <div class="stack-item"><span class="si">⚡</span> Vanilla JS</div>
        <div class="stack-item"><span class="si">📊</span> Chart.js</div>
        <div class="stack-item"><span class="si">🔤</span> Google Fonts</div>
      </div>

      <!-- Acknowledgement -->
      <div class="cm-section-lbl">🙏 Acknowledgements</div>
      <div class="ack-box">
        Developed as a project for <strong>Trece Martires City National High School</strong>.
        Special thanks to the school administration and faculty for their guidance and support throughout the development process.
      </div>

    </div>

    <!-- Footer -->
    <div class="cm-footer">
      <div class="cm-footer-note"><strong>TMCNHS</strong> Management System · © 2026</div>
      <button class="cm-close-footer" onclick="closeCredits()">Close</button>
    </div>

  </div>
</div>


<script>
const docsByTeacher    = <?= $docs_by_teacher_json ?>;
const leaveByTeacher   = <?= $leave_by_teacher_json ?>;
const leaveTotalAbsent = <?= $leave_total_json ?>;
const TOTAL_LEAVE_DAYS = <?= $total_leave_days ?>;
const deptData   = <?= json_encode(array_values($dept_counts)) ?>;
const deptLabels = <?= json_encode(array_keys($dept_counts)) ?>;
const top5Labels = <?= json_encode(array_keys($pos_counts)) ?>;
const top5Data   = <?= json_encode(array_values($pos_counts)) ?>;

/* ── LIVE CLOCK ── */
function updateClock() {
  const now = new Date();
  const opts = { weekday:'short', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true };
  document.getElementById('liveClock').innerHTML = now.toLocaleString('en-PH', opts).replace(',','');
}
updateClock();
setInterval(updateClock, 1000);

/* ── CHARTS ── */
Chart.defaults.font  = { family: "'DM Sans', sans-serif", size: 11 };
Chart.defaults.color = '#9c7b7e';
const deptColors = ['#c8102e','#059669','#1d4ed8','#d97706','#7b1fa2','#0891b2','#dc2626','#0d9488'];
new Chart(document.getElementById('deptChart'), {
  type: 'doughnut',
  data: { labels: deptLabels, datasets: [{ data: deptData, backgroundColor: deptColors.slice(0, deptLabels.length), borderWidth: 2, borderColor: '#fff' }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, pointStyleWidth: 8, boxHeight: 8 } } } }
});
const posBarColors    = ['rgba(200,16,46,0.8)','rgba(5,150,105,0.8)','rgba(29,78,216,0.8)','rgba(217,119,6,0.8)','rgba(123,31,162,0.8)','rgba(8,145,178,0.8)','rgba(220,38,38,0.8)','rgba(13,148,136,0.8)'];
const posBorderColors = ['#c8102e','#059669','#1d4ed8','#d97706','#7b1fa2','#0891b2','#dc2626','#0d9488'];
new Chart(document.getElementById('top5Chart'), {
  type: 'bar',
  data: { labels: top5Labels, datasets: [{ label: 'Personnel', data: top5Data, backgroundColor: top5Labels.map((_,i)=>posBarColors[i%posBarColors.length]), borderColor: top5Labels.map((_,i)=>posBorderColors[i%posBorderColors.length]), borderWidth: 2, borderRadius: 6 }] },
  options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.y} personnel` } } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(240,212,212,0.5)' } }, x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 30 } } } }
});

/* ── TEACHER PROFILE MODAL ── */
const avatarColors = ['pm-avatar-a','pm-avatar-b','pm-avatar-c'];
const fileIcons = { pdf:'📕',doc:'📘',docx:'📘',xls:'📗',xlsx:'📗',ppt:'📙',pptx:'📙',jpg:'🖼',jpeg:'🖼',png:'🖼',gif:'🖼',zip:'📦',rar:'📦',txt:'📄',csv:'📊' };
const leaveTypeClass = { 'Sick Leave':'leave-sick','Vacation Leave':'leave-vacation','Emergency Leave':'leave-emergency','Maternity Leave':'leave-maternity','Other':'leave-other' };
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
function getExt(f) { return (f||'').split('.').pop().toLowerCase(); }
function getIcon(f) { return fileIcons[getExt(f)] || '📄'; }
function fmtSize(b) { if(!b) return '—'; if(b<1024) return b+' B'; if(b<1048576) return (b/1024).toFixed(1)+' KB'; return (b/1048576).toFixed(1)+' MB'; }
function fmtDate(s) { if(!s) return '—'; return new Date(s).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}); }
function fmtDateChip(s) { const d=new Date(s+'T00:00:00'); return MONTHS[d.getMonth()].substring(0,3)+' '+d.getDate(); }

document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if(e.target===o) o.classList.remove('open'); });
});

function openTeacherProfile(t) {
  const av = document.getElementById('pmAvatar');
  av.textContent = t.full_name[0].toUpperCase();
  av.className = 'pm-avatar ' + avatarColors[t.id % 3];
  document.getElementById('pmName').textContent  = t.full_name;
  document.getElementById('pmEmail').textContent = t.email;
  const tags = document.getElementById('pmTags');
  tags.innerHTML = '';
  if (t.subject)    tags.innerHTML += `<span class="tag tag-blue">${t.subject}</span>`;
  if (t.department) tags.innerHTML += `<span class="tag tag-green">${t.department}</span>`;
  const totalAbsent = leaveTotalAbsent[t.id] || 0;
  const leaveLeft   = Math.max(0, TOTAL_LEAVE_DAYS - totalAbsent);
  tags.innerHTML += leaveLeft<=5 ? `<span class="tag tag-red">⚠ ${leaveLeft} leave days left</span>` : `<span class="tag tag-green">🏖 ${leaveLeft} leave days left</span>`;
  if (totalAbsent>0) tags.innerHTML += `<span class="tag tag-red">❌ ${totalAbsent} days absent</span>`;
  document.getElementById('pmFullName').textContent   = t.full_name;
  document.getElementById('pmUsername').textContent   = '@'+t.username;
  document.getElementById('pmEmailInfo').textContent  = t.email;
  document.getElementById('pmPhone').textContent      = t.phone || '—';
  document.getElementById('pmSubject').textContent    = t.subject || '—';
  document.getElementById('pmDepartment').textContent = t.department || '—';
  document.getElementById('pmDateHired').textContent  = t.date_hired || '—';
  document.getElementById('pmAddress').textContent    = t.address || '—';
  document.getElementById('pmStatus').innerHTML       = `<span class="badge badge-${t.status}">${t.status.charAt(0).toUpperCase()+t.status.slice(1)}</span>`;
  const docs = docsByTeacher[t.id] || [];
  document.getElementById('pmDocTabCount').textContent = docs.length;
  document.getElementById('pmDocCount').textContent    = docs.length + (docs.length===1?' file':' files');
  const docList = document.getElementById('pmDocsList');
  docList.innerHTML = docs.length===0 ? `<div class="no-content"><span class="no-content-icon">📭</span><p>No documents uploaded yet.</p></div>` :
    docs.map(d => `<div class="doc-row"><span class="doc-icon">${getIcon(d.file_name)}</span><div class="doc-info"><strong>${d.title}</strong><span>${d.file_name} · ${fmtSize(d.file_size)} · ${fmtDate(d.uploaded_at)}</span></div><div class="doc-actions"><a class="btn btn-view btn-sm" href="${d.file_path}" target="_blank">👁 View</a><a class="btn btn-download btn-sm" href="${d.file_path}" download="${d.file_name}">⬇</a></div></div>`).join('');
  const leaveRecords = leaveByTeacher[t.id] || [];
  document.getElementById('pmCreditTabCount').textContent = leaveRecords.length;
  renderServiceCreditPanel(t.id, leaveRecords, totalAbsent, leaveLeft);
  document.getElementById('teacherProfileModal').classList.add('open');
  switchPmTab('info', document.querySelectorAll('.pm-tab')[0]);
}

function renderServiceCreditPanel(tid, records, totalAbsent, leaveLeft) {
  const pct = Math.min(100, Math.round((totalAbsent/TOTAL_LEAVE_DAYS)*100));
  const barClass = pct>=80?'danger-fill':pct>=50?'warn-fill':'';
  document.getElementById('pmScStats').innerHTML = `
    <div class="sc-stat green"><div class="sc-icon">🏖️</div><div class="sc-num">${TOTAL_LEAVE_DAYS}</div><div class="sc-label">Total Allocation</div></div>
    <div class="sc-stat ${leaveLeft<=5?'danger':'blue'}"><div class="sc-icon">${leaveLeft<=5?'⚠️':'✅'}</div><div class="sc-num" style="color:${leaveLeft<=5?'var(--danger)':leaveLeft<=10?'var(--warn)':'var(--accent)'}">${leaveLeft}</div><div class="sc-label">Leave Days Available</div></div>
    <div class="sc-stat danger"><div class="sc-icon">📅</div><div class="sc-num" style="color:var(--danger)">${totalAbsent}</div><div class="sc-label">Days Absent</div></div>
    <div class="sc-stat purple"><div class="sc-icon">📝</div><div class="sc-num" style="color:var(--purple)">${records.length}</div><div class="sc-label">Reports Filed</div></div>`;
  document.getElementById('pmScProgressLabel').textContent = `${totalAbsent} / ${TOTAL_LEAVE_DAYS} days (${pct}%)`;
  const fill = document.getElementById('pmScProgressFill');
  fill.style.width = pct+'%';
  fill.className = 'progress-bar-fill'+(barClass?' '+barClass:'');
  const leaveList = document.getElementById('pmLeaveList');
  if (records.length===0) { leaveList.innerHTML=`<div class="no-content"><span class="no-content-icon">📋</span><p>No leave records filed yet.</p></div>`; return; }
  leaveList.innerHTML = records.map(r => {
    const dates = r.absent_dates?r.absent_dates.split(',').filter(Boolean):[];
    const ltClass = leaveTypeClass[r.leave_type]||'leave-other';
    const chips = dates.map(d=>`<span class="date-chip">${fmtDateChip(d.trim())}</span>`).join('');
    const scanBtn = r.scan_file_path?`<a class="btn btn-purple btn-sm" href="${r.scan_file_path}" target="_blank">📎 Scan</a>`:`<span style="color:var(--muted);font-size:11px">No scan</span>`;
    return `<div class="leave-log-row"><div class="leave-log-header"><strong>${r.last_name}, ${r.first_name}</strong><div class="leave-log-meta"><span class="leave-type-badge ${ltClass}">${r.leave_type}</span><span style="color:var(--danger);font-weight:700;font-family:'JetBrains Mono',monospace">${r.days_absent} day${r.days_absent!=1?'s':''}</span></div></div>${dates.length?`<div class="leave-log-dates">${chips}</div>`:''} ${r.reason?`<div style="font-size:12px;color:var(--muted);margin-top:6px">${r.reason}</div>`:''}<div class="leave-log-footer"><small>Filed: ${fmtDate(r.filed_at)}</small>${scanBtn}</div></div>`;
  }).join('');
}

function switchPmTab(tab, el) {
  document.querySelectorAll('#teacherProfileModal .pm-tab').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('#teacherProfileModal .pm-panel').forEach(p=>p.classList.remove('active'));
  el.classList.add('active');
  document.getElementById({info:'pmPanelInfo',docs:'pmPanelDocs',credit:'pmPanelCredit'}[tab]).classList.add('active');
}

/* ── CREDITS MODAL FUNCTIONS ── ★ NEW ── */
function openCredits()  { document.getElementById('creditsModal').classList.add('open'); }
function closeCredits() { document.getElementById('creditsModal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCredits(); });

/* ── PAGINATION + SEARCH ── */
const ROWS_PER_PAGE = 10;
let currentPage = 1;
let filteredRows = [];
function escapeRegex(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
function highlightText(el, q) {
  const orig = el.getAttribute('data-original') || el.textContent;
  el.setAttribute('data-original', orig);
  el.innerHTML = q ? orig.replace(new RegExp('('+escapeRegex(q)+')','gi'), '<span class="hl">$1</span>') : orig;
}
function renderPage() {
  const tbody   = document.getElementById('dashTbody');
  const allRows = Array.from(tbody.querySelectorAll('tr[data-name]'));
  const q       = document.getElementById('dashSearch').value.trim().toLowerCase();
  filteredRows = allRows.filter(row => !q || ['name','subject','dept','username'].some(a => (row.getAttribute('data-'+a)||'').includes(q)));
  allRows.forEach(row => {
    row.style.display = 'none';
    const el = row.querySelector('.searchable-name');
    if (el) highlightText(el, q);
  });
  const totalPages = Math.max(1, Math.ceil(filteredRows.length / ROWS_PER_PAGE));
  if (currentPage > totalPages) currentPage = totalPages;
  const start = (currentPage - 1) * ROWS_PER_PAGE;
  filteredRows.slice(start, start + ROWS_PER_PAGE).forEach(r => r.style.display = '');
  tbody.querySelector('.no-results-row')?.remove();
  if (filteredRows.length === 0) {
    const cols = tbody.closest('table').querySelectorAll('thead th').length;
    const nr = document.createElement('tr'); nr.className = 'no-results-row';
    nr.innerHTML = `<td colspan="${cols}"><span class="nr-icon">🔍</span>No personnel match "<strong>${q}</strong>"</td>`;
    tbody.appendChild(nr);
  }
  const infoEl = document.getElementById('pgInfo');
  if (filteredRows.length === 0) { infoEl.innerHTML = `No results`; }
  else { const from = start + 1, to = Math.min(start + ROWS_PER_PAGE, filteredRows.length); infoEl.innerHTML = `Showing <span>${from}–${to}</span> of <span>${filteredRows.length}</span> personnel`; }
  const countEl = document.getElementById('dashCount');
  countEl.innerHTML = (q && filteredRows.length > 0) ? `Showing <span>${filteredRows.length}</span> of ${allRows.length} personnel` : '';
  renderPagination(totalPages);
}
function renderPagination(totalPages) {
  const btnsEl = document.getElementById('pgBtns');
  btnsEl.innerHTML = '';
  const mkBtn = (label, page, isActive, disabled, isArrow) => {
    const btn = document.createElement('button');
    btn.className = 'pg-btn' + (isActive ? ' active' : '') + (isArrow ? ' pg-arrow' : '');
    btn.textContent = label; btn.disabled = disabled;
    if (!disabled) btn.onclick = () => { currentPage = page; renderPage(); };
    return btn;
  };
  btnsEl.appendChild(mkBtn('‹', currentPage - 1, false, currentPage === 1, true));
  let pages = [];
  if (totalPages <= 5) { pages = Array.from({length: totalPages}, (_,i) => i+1); }
  else {
    pages = [1];
    if (currentPage > 3) pages.push('…');
    for (let p = Math.max(2, currentPage-1); p <= Math.min(totalPages-1, currentPage+1); p++) pages.push(p);
    if (currentPage < totalPages - 2) pages.push('…');
    pages.push(totalPages);
  }
  pages.forEach(p => {
    if (p === '…') { const sep = document.createElement('span'); sep.textContent = '…'; sep.style.cssText = 'padding:0 4px;color:var(--muted);font-size:13px;'; btnsEl.appendChild(sep); }
    else { btnsEl.appendChild(mkBtn(p, p, p === currentPage, false, false)); }
  });
  btnsEl.appendChild(mkBtn('›', currentPage + 1, false, currentPage === totalPages, true));
}
function filterTable(inputId) {
  currentPage = 1;
  const q = document.getElementById(inputId).value.trim();
  const clearBtn = document.getElementById(inputId + 'Clear');
  if (clearBtn) clearBtn.style.display = q ? 'block' : 'none';
  renderPage();
}
function clearSearch(inputId) {
  const input = document.getElementById(inputId);
  input.value = ''; currentPage = 1;
  const clearBtn = document.getElementById(inputId + 'Clear');
  if (clearBtn) clearBtn.style.display = 'none';
  document.querySelectorAll('#dashTbody .searchable-name').forEach(el => { const o = el.getAttribute('data-original'); if (o) el.innerHTML = o; });
  renderPage(); input.focus();
}
document.getElementById('dashSearch').addEventListener('input', () => filterTable('dashSearch'));
renderPage();
</script>

</body>
</html>