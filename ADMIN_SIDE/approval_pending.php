<?php
// ADMIN_SIDE/approval_pending.php
// Admin lands here after submitting an action request.
// The page polls every 10 seconds to check if approved.
require_once '../Config.php';
require_once '../helpers/ActionRequest.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../index.php'); exit;
}

$requestId  = (int)($_GET['req'] ?? 0);
$returnUrl  = $_GET['return'] ?? 'admin_dashboard.php'; // where to go after approval

if (!$requestId) { header('Location: admin_dashboard.php'); exit; }

// Fetch the request
$stmt = $conn->prepare("SELECT * FROM action_requests WHERE id = ? AND admin_user_id = ?");
$stmt->bind_param('ii', $requestId, $_SESSION['user_id']);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

if (!$req) { header('Location: admin_dashboard.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Awaiting Approval — TMCNHS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --red: #c8102e; --red-dark: #9b0c22;
  --gold: #d4a84b; --gold-light: #f0d080;
  --cream: #fdf8f0; --text: #1a0608; --muted: #7a5a5e;
  --border: #e8cdd0; --white: #ffffff;
  --green: #1a7a45; --green-bg: #f0faf4;
  --orange: #b85c00; --orange-bg: #fff7ed;
}
body { font-family: 'DM Sans', sans-serif; background: var(--cream); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }

.card {
  background: var(--white); border: 1.5px solid var(--border);
  border-radius: 24px; padding: 48px 44px; max-width: 500px; width: 100%;
  text-align: center; box-shadow: 0 8px 40px rgba(200,16,46,0.07);
}

/* SPINNER */
.spinner-ring {
  width: 80px; height: 80px;
  border-radius: 50%;
  border: 4px solid var(--border);
  border-top-color: var(--red);
  animation: spin 1s linear infinite;
  margin: 0 auto 28px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* APPROVED state */
.check-ring {
  width: 80px; height: 80px; border-radius: 50%;
  background: var(--green-bg); border: 3px solid #a3d9b8;
  display: none; align-items: center; justify-content: center;
  margin: 0 auto 28px; font-size: 36px;
  animation: popIn 0.4s cubic-bezier(0.22,1,0.36,1);
}
@keyframes popIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

/* REJECTED state */
.x-ring {
  width: 80px; height: 80px; border-radius: 50%;
  background: #fff0f2; border: 3px solid #ffc9d0;
  display: none; align-items: center; justify-content: center;
  margin: 0 auto 28px; font-size: 36px;
}

.status-label {
  display: inline-flex; align-items: center; gap: 7px;
  background: var(--orange-bg); color: var(--orange);
  border: 1px solid #fddcb0; border-radius: 999px;
  padding: 5px 18px; font-size: 12px; font-family: 'JetBrains Mono', monospace;
  letter-spacing: 1px; text-transform: uppercase; margin-bottom: 20px;
}
.status-label.approved { background: var(--green-bg); color: var(--green); border-color: #a3d9b8; }
.status-label.rejected { background: #fff0f2; color: var(--red); border-color: #ffc9d0; }

h1 { font-family: 'DM Serif Display', serif; font-size: 26px; color: var(--text); margin-bottom: 10px; }
p  { color: var(--muted); font-size: 14px; line-height: 1.6; }

.action-box {
  background: #fdf5f6; border: 1.5px solid var(--border);
  border-radius: 12px; padding: 16px 20px; margin: 22px 0;
  text-align: left;
}
.action-box .alabel { font-size: 11px; font-family: 'JetBrains Mono', monospace; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
.action-box .aval { font-size: 15px; font-weight: 600; color: var(--text); }
.action-box .ameta { font-size: 12px; color: var(--muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }

.timer { font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--muted); margin-top: 16px; }
.timer span { color: var(--red); font-weight: 600; }

.btn {
  display: inline-block; padding: 13px 28px; border-radius: 12px;
  font-size: 15px; font-weight: 600; cursor: pointer; border: none;
  font-family: 'DM Sans', sans-serif; text-decoration: none;
  transition: all 0.2s;
}
.btn-go { background: linear-gradient(135deg, var(--red), var(--red-dark)); color: #fff; box-shadow: 0 4px 18px rgba(200,16,46,0.25); display: none; margin-top: 20px; }
.btn-go:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(200,16,46,0.3); }
.btn-back { background: var(--border); color: var(--muted); margin-top: 14px; display: inline-block; }

.poll-note { font-size: 11px; color: #c5a5aa; margin-top: 20px; font-family: 'JetBrains Mono', monospace; }
</style>
</head>
<body>
<div class="card" id="mainCard">

  <div class="spinner-ring" id="spinnerEl"></div>
  <div class="check-ring" id="checkEl">✓</div>
  <div class="x-ring" id="xEl">✕</div>

  <div class="status-label" id="statusLabel">⏳ Awaiting Approval</div>

  <h1 id="mainTitle">Waiting for Super Admin</h1>
  <p id="mainDesc">Your request has been submitted. The Super Admin will review and approve or reject it shortly.</p>

  <div class="action-box">
    <div class="alabel">Requested Action</div>
    <div class="aval"><?= htmlspecialchars($req['action_label']) ?></div>
    <div class="ameta">Request #<?= $req['id'] ?> · <?= date('M j, Y g:i A', strtotime($req['requested_at'])) ?></div>
  </div>

  <div class="timer" id="timerEl">Checking for approval in <span id="countdown">10</span>s…</div>

  <a href="<?= htmlspecialchars($returnUrl) ?>" id="proceedBtn" class="btn btn-go">
    ✓ Proceed with Action →
  </a>
  <br>
  <a href="admin_dashboard.php" class="btn btn-back">← Back to Dashboard</a>

  <div class="poll-note" id="pollNote">This page checks for approval every 10 seconds automatically.</div>
</div>

<script>
const requestId = <?= $requestId ?>;
const returnUrl = <?= json_encode($returnUrl) ?>;
let countdown = 10;
let pollTimer;

function updateCountdown() {
  document.getElementById('countdown').textContent = countdown;
  if (countdown <= 0) {
    clearInterval(pollTimer);
    checkStatus();
    countdown = 10;
    pollTimer = setInterval(tickCountdown, 1000);
  }
  countdown--;
}

function tickCountdown() {
  countdown--;
  updateCountdown();
}

async function checkStatus() {
  try {
    const res  = await fetch(`check_request_status.php?req=${requestId}`);
    const data = await res.json();

    if (data.status === 'approved') {
      showApproved(data);
    } else if (data.status === 'rejected') {
      showRejected(data);
    } else if (data.status === 'expired') {
      showExpired();
    }
  } catch (e) {
    console.error('Poll error', e);
  }
}

function showApproved(data) {
  clearInterval(pollTimer);
  document.getElementById('spinnerEl').style.display = 'none';
  document.getElementById('checkEl').style.display = 'flex';
  document.getElementById('statusLabel').textContent = '✓ Approved';
  document.getElementById('statusLabel').classList.add('approved');
  document.getElementById('mainTitle').textContent = 'Action Approved!';
  document.getElementById('mainDesc').textContent = 'The Super Admin has approved your request. You may now proceed. This approval expires in 15 minutes.';
  document.getElementById('timerEl').style.display = 'none';
  document.getElementById('pollNote').style.display = 'none';
  document.getElementById('proceedBtn').style.display = 'inline-block';
  // Append request ID to return URL so the action handler can validate
  const url = new URL(returnUrl, window.location.href);
  url.searchParams.set('approved_req', requestId);
  document.getElementById('proceedBtn').href = url.toString();
}

function showRejected(data) {
  clearInterval(pollTimer);
  document.getElementById('spinnerEl').style.display = 'none';
  document.getElementById('xEl').style.display = 'flex';
  document.getElementById('statusLabel').textContent = '✕ Rejected';
  document.getElementById('statusLabel').classList.add('rejected');
  document.getElementById('mainTitle').textContent = 'Request Rejected';
  document.getElementById('mainDesc').textContent = data.rejection_note
    ? `Reason: "${data.rejection_note}". Please contact the Super Admin if you believe this is an error.`
    : 'Your request was rejected. Please contact the Super Admin for details.';
  document.getElementById('timerEl').style.display = 'none';
  document.getElementById('pollNote').style.display = 'none';
}

function showExpired() {
  clearInterval(pollTimer);
  document.getElementById('mainTitle').textContent = 'Request Expired';
  document.getElementById('mainDesc').textContent = 'This request has expired. Please submit a new request.';
  document.getElementById('timerEl').style.display = 'none';
}

// Check immediately on load if already approved
checkStatus();
pollTimer = setInterval(tickCountdown, 1000);
</script>
</body>
</html>