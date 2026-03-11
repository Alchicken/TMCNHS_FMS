<?php
// ADMIN_SIDE/personnel_request_pending.php
// Shown to admin after submitting an "add personnel" request.
// Polls every 10 seconds for super admin's decision.
require_once 'admin_shared.php';

$requestId = (int)($_GET['req'] ?? 0);
if (!$requestId) { header('Location: manage_personnel.php'); exit; }

// Fetch request to show personnel name
$stmt = $conn->prepare("SELECT * FROM action_requests WHERE id = ? AND admin_user_id = ? AND action_type = 'add_personnel'");
$stmt->bind_param('ii', $requestId, $_SESSION['user_id']);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
if (!$req) { header('Location: manage_personnel.php'); exit; }

$meta = json_decode($req['meta_json'] ?? '{}', true) ?? [];
$personnelName = trim(($meta['first_name'] ?? '') . ' ' . ($meta['last_name'] ?? '')) ?: 'New Personnel';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Awaiting Approval — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root { --red:#c8102e; --red-dark:#9b0c22; --cream:#fdf8f0; --text:#1a0608; --muted:#7a5a5e; --border:#e8cdd0; --white:#fff; --green:#1a7a45; --green-bg:#f0faf4; --orange:#b85c00; --orange-bg:#fff7ed; }
body { font-family: 'DM Sans', sans-serif; background: var(--cream); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }

.card { background: var(--white); border: 1.5px solid var(--border); border-radius: 24px; padding: 48px 44px; max-width: 520px; width: 100%; text-align: center; box-shadow: 0 8px 40px rgba(200,16,46,0.07); }

.spinner-ring { width: 80px; height: 80px; border-radius: 50%; border: 4px solid var(--border); border-top-color: var(--red); animation: spin 1s linear infinite; margin: 0 auto 28px; }
@keyframes spin { to { transform: rotate(360deg); } }
.check-ring { width: 80px; height: 80px; border-radius: 50%; background: var(--green-bg); border: 3px solid #a3d9b8; display: none; align-items: center; justify-content: center; margin: 0 auto 28px; font-size: 36px; animation: popIn 0.4s cubic-bezier(0.22,1,0.36,1); }
.x-ring     { width: 80px; height: 80px; border-radius: 50%; background: #fff0f2; border: 3px solid #ffc9d0; display: none; align-items: center; justify-content: center; margin: 0 auto 28px; font-size: 36px; }
@keyframes popIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }

.status-label { display: inline-flex; align-items: center; gap: 7px; background: var(--orange-bg); color: var(--orange); border: 1px solid #fddcb0; border-radius: 999px; padding: 5px 18px; font-size: 12px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 20px; }
.status-label.approved { background: var(--green-bg); color: var(--green); border-color: #a3d9b8; }
.status-label.rejected { background: #fff0f2; color: var(--red); border-color: #ffc9d0; }
h1 { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--text); margin-bottom: 10px; }
p  { color: var(--muted); font-size: 14px; line-height: 1.6; }

.info-box { background: #fdf5f6; border: 1.5px solid var(--border); border-radius: 12px; padding: 16px 20px; margin: 22px 0; text-align: left; }
.info-box .label { font-size: 11px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.info-box .val   { font-size: 15px; font-weight: 600; color: var(--text); }
.info-box .sub   { font-size: 12px; color: var(--muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
.pp-mini { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; margin-top: 10px; }
.pp-mini-item label { font-size: 10px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; color: var(--muted); display: block; margin-bottom: 2px; }
.pp-mini-item span  { font-size: 12px; font-weight: 500; }

.timer { font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--muted); margin-top: 16px; }
.timer span { color: var(--red); font-weight: 600; }

.rejection-box { margin-top: 16px; background: #fff0f2; border: 1.5px solid #ffc9d0; border-radius: 12px; padding: 14px 18px; text-align: left; font-size: 14px; color: var(--red); display: none; }
.rejection-box strong { display: block; font-size: 11px; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; color: var(--red); }

.btn { display: inline-block; padding: 13px 28px; border-radius: 12px; font-size: 15px; font-weight: 600; cursor: pointer; border: none; font-family: 'DM Sans', sans-serif; text-decoration: none; transition: all 0.2s; margin-top: 20px; }
.btn-go   { background: linear-gradient(135deg, var(--red), var(--red-dark)); color: #fff; box-shadow: 0 4px 18px rgba(200,16,46,0.25); display: none; }
.btn-back { background: var(--border); color: var(--muted); display: inline-block; margin-top: 14px; }
.btn-requests { background: rgba(200,16,46,0.1); color: var(--red); border: 1px solid rgba(200,16,46,0.3); display: none; }

.poll-note { font-size: 11px; color: #c5a5aa; margin-top: 20px; font-family: 'JetBrains Mono', monospace; }
</style>
</head>
<body>
<div class="card">
  <div class="spinner-ring" id="spinnerEl"></div>
  <div class="check-ring"   id="checkEl">✓</div>
  <div class="x-ring"       id="xEl">✕</div>

  <div class="status-label" id="statusLabel">⏳ Awaiting Approval</div>

  <h1 id="mainTitle">Waiting for Super Admin</h1>
  <p id="mainDesc">Your request to add a new personnel has been submitted. The Super Admin will review it shortly.</p>

  <div class="info-box">
    <div class="label">👤 Personnel to Add</div>
    <div class="val"><?= htmlspecialchars($personnelName) ?></div>
    <div class="sub">
      <?= htmlspecialchars($meta['subject'] ?? '') ?>
      <?php if (!empty($meta['department'])): ?> · <?= htmlspecialchars($meta['department']) ?><?php endif; ?>
    </div>
    <?php if (!empty($meta['email'])): ?>
    <div class="pp-mini">
      <div class="pp-mini-item"><label>Email</label><span><?= htmlspecialchars($meta['email']) ?></span></div>
      <div class="pp-mini-item"><label>Username</label><span>@<?= htmlspecialchars($meta['username'] ?? '—') ?></span></div>
      <div class="pp-mini-item"><label>Employment</label><span><?= htmlspecialchars($meta['employment_type'] ?? '—') ?></span></div>
      <div class="pp-mini-item"><label>Date Hired</label><span><?= htmlspecialchars($meta['date_hired'] ?? '—') ?></span></div>
    </div>
    <?php endif; ?>
    <div class="sub" style="margin-top:10px">Request #<?= $requestId ?> · Submitted: <?= date('M j, Y g:i A', strtotime($req['requested_at'])) ?></div>
  </div>

  <div class="rejection-box" id="rejectionBox">
    <strong>Rejection Reason</strong>
    <span id="rejectionNote"></span>
  </div>

  <div class="timer" id="timerEl">Checking for approval in <span id="countdown">10</span>s…</div>

  <a href="manage_personnel.php" id="proceedBtn" class="btn btn-go">✓ View Personnel List →</a>
  <br>
  <a href="admin_my_requests.php" id="viewRequestsBtn" class="btn btn-requests">📋 View My Requests</a>
  <br>
  <a href="manage_personnel.php" class="btn btn-back">← Back to Personnel</a>

  <div class="poll-note" id="pollNote">This page checks for approval every 10 seconds automatically.</div>
</div>

<script>
const requestId = <?= $requestId ?>;
let countdown = 10;
let pollTimer;
let done = false;

function tickCountdown() {
  countdown--;
  document.getElementById('countdown').textContent = countdown;
  if (countdown <= 0) {
    clearInterval(pollTimer);
    checkStatus();
    countdown = 10;
    pollTimer = setInterval(tickCountdown, 1000);
  }
}

async function checkStatus() {
  if (done) return;
  try {
    const res  = await fetch(`process_personnel_request.php?req=${requestId}`);
    const data = await res.json();

    if (data.status === 'approved' || data.status === 'done') {
      // Token consumed and personnel inserted — redirect
      done = true;
      clearInterval(pollTimer);
      showApproved();
      // Auto-redirect after 2 seconds
      setTimeout(() => {
        window.location.href = data.redirect || `manage_personnel.php?added=1`;
      }, 2000);
    } else if (data.status === 'rejected') {
      done = true;
      clearInterval(pollTimer);
      showRejected(data.rejection_note);
    } else if (data.status === 'expired') {
      done = true;
      clearInterval(pollTimer);
      showExpired();
    } else if (data.status === 'error') {
      done = true;
      clearInterval(pollTimer);
      showError(data.message);
    }
    // else still pending — keep polling
  } catch (e) {
    console.error('Poll error', e);
  }
}

function showApproved() {
  document.getElementById('spinnerEl').style.display = 'none';
  document.getElementById('checkEl').style.display   = 'flex';
  document.getElementById('statusLabel').textContent  = '✓ Approved';
  document.getElementById('statusLabel').classList.add('approved');
  document.getElementById('mainTitle').textContent    = 'Personnel Added!';
  document.getElementById('mainDesc').textContent     = 'The Super Admin approved your request. The personnel account has been created. Redirecting…';
  document.getElementById('timerEl').style.display    = 'none';
  document.getElementById('pollNote').style.display   = 'none';
  document.getElementById('proceedBtn').style.display = 'inline-block';
}

function showRejected(note) {
  document.getElementById('spinnerEl').style.display   = 'none';
  document.getElementById('xEl').style.display         = 'flex';
  document.getElementById('statusLabel').textContent   = '✕ Rejected';
  document.getElementById('statusLabel').classList.add('rejected');
  document.getElementById('mainTitle').textContent     = 'Request Rejected';
  document.getElementById('mainDesc').textContent      = 'The Super Admin rejected this request.';
  document.getElementById('timerEl').style.display     = 'none';
  document.getElementById('pollNote').style.display    = 'none';
  document.getElementById('viewRequestsBtn').style.display = 'inline-block';
  if (note) {
    const box = document.getElementById('rejectionBox');
    document.getElementById('rejectionNote').textContent = note;
    box.style.display = 'block';
  }
}

function showExpired() {
  document.getElementById('mainTitle').textContent = 'Request Expired';
  document.getElementById('mainDesc').textContent  = 'This approval window has expired. Please resubmit.';
  document.getElementById('timerEl').style.display = 'none';
  document.getElementById('viewRequestsBtn').style.display = 'inline-block';
}

function showError(msg) {
  document.getElementById('spinnerEl').style.display = 'none';
  document.getElementById('xEl').style.display       = 'flex';
  document.getElementById('statusLabel').textContent  = '⚠ Error';
  document.getElementById('statusLabel').classList.add('rejected');
  document.getElementById('mainTitle').textContent    = 'Something went wrong';
  document.getElementById('mainDesc').textContent     = msg || 'An unexpected error occurred.';
  document.getElementById('timerEl').style.display    = 'none';
  document.getElementById('viewRequestsBtn').style.display = 'inline-block';
}

// Start polling immediately then every 10s
checkStatus();
pollTimer = setInterval(tickCountdown, 1000);
</script>
</body>
</html>