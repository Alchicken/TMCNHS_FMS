<?php
require_once 'Config.php';

$error = '';

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: ADMIN_SIDE/admin_dashboard.php'); exit;
    } elseif ($_SESSION['role'] === 'teacher') {
        header('Location: PERSONNELSIDE/teacher_dashboard.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = $_POST['role'] ?? '';

    if ($username && $password && $role) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
        $stmt->bind_param('ss', $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: ADMIN_SIDE/admin_dashboard.php');
            } else {
                header('Location: PERSONNELSIDE/teacher_dashboard.php');
            }
            exit;
        } else {
            $error = 'Invalid credentials or role selected.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TMCNHS — Management Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --red:       #c8102e;
  --red-dark:  #9b0c22;
  --red-deep:  #6b0518;
  --red-glow:  rgba(200,16,46,0.35);
  --gold:      #d4a84b;
  --gold-light:#f0d080;
  --cream:     #fdf8f0;
  --text:      #1a0608;
  --muted:     #7a5a5e;
  --border:    #e8cdd0;
  --surface:   #fff8f8;
  --white:     #ffffff;
}

html, body {
  height: 100%;
  font-family: 'DM Sans', sans-serif;
  background: var(--cream);
  overflow: hidden;
}

/* ── SPLIT LAYOUT ───────────────────────────────────── */
.layout {
  display: flex;
  height: 100vh;
  width: 100vw;
}

/* ── LEFT PANEL ─────────────────────────────────────── */
.left-panel {
  width: 48%;
  background: linear-gradient(160deg, var(--red-deep) 0%, var(--red-dark) 40%, var(--red) 100%);
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 52px;
  flex-shrink: 0;
}

/* Geometric background shapes */
.left-panel::before {
  content: '';
  position: absolute;
  top: -120px; right: -120px;
  width: 480px; height: 480px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.07) 0%, transparent 70%);
  pointer-events: none;
}
.left-panel::after {
  content: '';
  position: absolute;
  bottom: -80px; left: -80px;
  width: 360px; height: 360px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(212,168,75,0.12) 0%, transparent 70%);
  pointer-events: none;
}

/* Gold decorative lines */
.deco-lines {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
  pointer-events: none;
  overflow: hidden;
}
.deco-lines::before {
  content: '';
  position: absolute;
  top: 32px; left: 32px; right: 32px; bottom: 32px;
  border: 1px solid rgba(212,168,75,0.2);
  border-radius: 2px;
}
.deco-lines::after {
  content: '';
  position: absolute;
  top: 44px; left: 44px; right: 44px; bottom: 44px;
  border: 1px solid rgba(255,255,255,0.06);
  border-radius: 2px;
}

/* Dot grid pattern */
.dot-grid {
  position: absolute;
  inset: 0;
  background-image: radial-gradient(circle, rgba(255,255,255,0.08) 1px, transparent 1px);
  background-size: 28px 28px;
  pointer-events: none;
}

.left-content {
  position: relative;
  z-index: 2;
  text-align: center;
  animation: fadeLeft 0.7s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes fadeLeft {
  from { opacity: 0; transform: translateX(-24px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* Logo circle */
.logo-ring {
  width: 130px; height: 130px;
  border-radius: 50%;
  background: rgba(255,255,255,0.96);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 32px;
  box-shadow:
    0 0 0 8px rgba(255,255,255,0.1),
    0 0 0 16px rgba(255,255,255,0.05),
    0 20px 60px rgba(0,0,0,0.4);
  position: relative;
}

.logo-ring img {
  width: 88px; height: 88px;
  object-fit: contain;
  display: block;
}

/* Gold ring accent */
.logo-ring::before {
  content: '';
  position: absolute;
  inset: -6px;
  border-radius: 50%;
  border: 2px solid rgba(212,168,75,0.5);
}
.logo-ring::after {
  content: '';
  position: absolute;
  inset: -12px;
  border-radius: 50%;
  border: 1px solid rgba(212,168,75,0.2);
}

.school-name {
  font-family: 'DM Serif Display', serif;
  color: #ffffff;
  font-size: 22px;
  line-height: 1.35;
  letter-spacing: 0.02em;
  margin-bottom: 10px;
  text-shadow: 0 2px 12px rgba(0,0,0,0.3);
}

.gold-divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 18px auto;
  width: 200px;
}
.gold-divider span {
  flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--gold), transparent);
}
.gold-divider em {
  color: var(--gold);
  font-style: normal;
  font-size: 14px;
}

.portal-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(212,168,75,0.15);
  border: 1px solid rgba(212,168,75,0.4);
  border-radius: 999px;
  padding: 7px 20px;
  color: var(--gold-light);
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 2px;
  text-transform: uppercase;
}

.portal-badge::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--gold);
  box-shadow: 0 0 6px var(--gold);
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.8); }
}

.left-footer {
  position: absolute;
  bottom: 28px;
  color: rgba(255,255,255,0.3);
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
  letter-spacing: 0.5px;
}

/* ── RIGHT PANEL ─────────────────────────────────────── */
.right-panel {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--cream);
  position: relative;
  padding: 40px;
  overflow-y: auto;
}

/* Subtle background texture */
.right-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 80% 20%, rgba(200,16,46,0.04) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 20% 80%, rgba(212,168,75,0.04) 0%, transparent 60%);
  pointer-events: none;
}

.form-container {
  width: 100%;
  max-width: 400px;
  position: relative;
  z-index: 1;
  animation: fadeRight 0.7s 0.15s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes fadeRight {
  from { opacity: 0; transform: translateX(24px); }
  to   { opacity: 1; transform: translateX(0); }
}

.form-header {
  margin-bottom: 32px;
}

.form-header h2 {
  font-family: 'DM Serif Display', serif;
  font-size: 30px;
  font-weight: 400;
  color: var(--text);
  letter-spacing: -0.5px;
  line-height: 1.2;
  margin-bottom: 6px;
}

.form-header p {
  color: var(--muted);
  font-size: 14px;
}

/* Error */
.error-msg {
  background: #fff0f2;
  border: 1.5px solid #ffc9d0;
  border-radius: 12px;
  color: var(--red);
  padding: 12px 16px;
  font-size: 13px;
  margin-bottom: 22px;
  display: flex;
  align-items: center;
  gap: 9px;
  font-weight: 500;
  animation: shake 0.4s ease;
}

@keyframes shake {
  0%,100% { transform: translateX(0); }
  20%,60% { transform: translateX(-6px); }
  40%,80% { transform: translateX(6px); }
}

/* Role selector */
.role-section-label {
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--muted);
  margin-bottom: 10px;
}

.role-toggle {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-bottom: 28px;
}

.role-btn {
  padding: 14px 12px;
  border: 1.5px solid var(--border);
  border-radius: 14px;
  background: var(--white);
  cursor: pointer;
  transition: all 0.22s ease;
  text-align: center;
  position: relative;
  overflow: hidden;
}

.role-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, var(--red), var(--red-dark));
  opacity: 0;
  transition: opacity 0.22s ease;
}

.role-btn .rb-icon {
  font-size: 22px;
  margin-bottom: 5px;
  display: block;
  position: relative;
  z-index: 1;
  transition: transform 0.22s ease;
}

.role-btn .rb-label {
  display: block;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--muted);
  position: relative;
  z-index: 1;
  transition: color 0.22s ease;
}

.role-btn .rb-sub {
  display: block;
  font-size: 10px;
  font-family: 'JetBrains Mono', monospace;
  color: #b0909a;
  position: relative;
  z-index: 1;
  margin-top: 2px;
  transition: color 0.22s ease;
}

.role-btn:hover {
  border-color: rgba(200,16,46,0.3);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(200,16,46,0.1);
}

.role-btn:hover .rb-icon { transform: scale(1.1); }

.role-btn.active::before { opacity: 1; }
.role-btn.active {
  border-color: var(--red);
  box-shadow: 0 6px 20px rgba(200,16,46,0.25), 0 0 0 3px rgba(200,16,46,0.1);
}
.role-btn.active .rb-label { color: #fff; }
.role-btn.active .rb-sub { color: rgba(255,255,255,0.65); }

/* Form fields */
.field-group {
  margin-bottom: 18px;
}

.field-group label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--text);
  letter-spacing: 0.3px;
  margin-bottom: 8px;
}

.field-wrap {
  position: relative;
}

.field-wrap .field-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 15px;
  pointer-events: none;
  opacity: 0.45;
}

.field-wrap input {
  width: 100%;
  padding: 13px 14px 13px 44px;
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: 12px;
  color: var(--text);
  font-family: 'DM Sans', sans-serif;
  font-size: 14px;
  outline: none;
  transition: all 0.2s ease;
}

.field-wrap input::placeholder { color: #c5a5aa; }

.field-wrap input:focus {
  border-color: var(--red);
  background: #fff;
  box-shadow: 0 0 0 4px rgba(200,16,46,0.08);
}

/* Password toggle */
.pw-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 16px;
  opacity: 0.4;
  transition: opacity 0.2s;
  padding: 0;
  line-height: 1;
}
.pw-toggle:hover { opacity: 0.8; }

/* Submit button */
.btn-login {
  width: 100%;
  padding: 16px;
  margin-top: 8px;
  background: linear-gradient(135deg, var(--red), var(--red-dark));
  border: none;
  border-radius: 13px;
  color: white;
  font-family: 'DM Sans', sans-serif;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.22s ease;
  letter-spacing: 0.2px;
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  box-shadow: 0 4px 20px rgba(200,16,46,0.3);
}

.btn-login::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
  transition: left 0.5s ease;
}

.btn-login:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 32px rgba(200,16,46,0.4);
}
.btn-login:hover::before { left: 100%; }
.btn-login:active { transform: translateY(0); box-shadow: 0 4px 12px rgba(200,16,46,0.25); }

.btn-login .arrow {
  display: inline-block;
  transition: transform 0.22s ease;
}
.btn-login:hover .arrow { transform: translateX(4px); }

/* Footer */
.form-footer {
  text-align: center;
  margin-top: 24px;
  color: var(--muted);
  font-size: 12px;
}

.form-footer strong { color: var(--red); font-weight: 600; }

/* Divider */
.divider {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 22px 0;
}
.divider hr {
  flex: 1;
  border: none;
  height: 1px;
  background: var(--border);
}
.divider span {
  color: var(--muted);
  font-size: 11px;
  font-family: 'JetBrains Mono', monospace;
  white-space: nowrap;
}

/* ── RESPONSIVE ─────────────────────────────────────── */
@media (max-width: 820px) {
  .layout { flex-direction: column; overflow-y: auto; }
  .left-panel { width: 100%; min-height: 280px; padding: 40px 32px 36px; }
  .left-panel::before, .left-panel::after { display: none; }
  .logo-ring { width: 90px; height: 90px; margin-bottom: 20px; }
  .logo-ring img { width: 62px; height: 62px; }
  .school-name { font-size: 17px; }
  .right-panel { padding: 32px 24px; overflow-y: visible; }
  html, body { overflow: auto; }
}
</style>
</head>
<body>
<div class="layout">

  <!-- ── LEFT PANEL ─────────────────────── -->
  <div class="left-panel">
    <div class="dot-grid"></div>
    <div class="deco-lines"></div>

    <div class="left-content">
      <div class="logo-ring">
        <img src="uploads/logo/TMCNHS-Logo.png" alt="TMCNHS Logo"
             onerror="this.parentElement.innerHTML='<span style=\'font-size:52px\'>🏫</span>'">
      </div>

      <div class="school-name">
        Trece Martires City<br>National High School
      </div>

      <div class="gold-divider">
        <span></span><em>✦</em><span></span>
      </div>

      <div class="portal-badge">Management Portal</div>
    </div>

    <div class="left-footer">SchoolSync © 2025 — TMCNHS</div>
  </div>

  <!-- ── RIGHT PANEL ────────────────────── -->
  <div class="right-panel">
    <div class="form-container">

      <div class="form-header">
        <h2>Welcome back</h2>
        <p>Sign in to your account to continue</p>
      </div>

      <?php if ($error): ?>
      <div class="error-msg">
        <span>⚠️</span> <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="role-section-label">Sign in as</div>
      <div class="role-toggle">
        <button type="button" class="role-btn <?= (empty($_POST['role']) || ($_POST['role'] ?? '') === 'admin') ? 'active' : '' ?>"
                id="roleAdmin" onclick="setRole('admin')">
          <span class="rb-icon">🛡️</span>
          <span class="rb-label">Administrator</span>
          <span class="rb-sub">Full access</span>
        </button>
        <button type="button" class="role-btn <?= (($_POST['role'] ?? '') === 'teacher') ? 'active' : '' ?>"
                id="roleTeacher" onclick="setRole('teacher')">
          <span class="rb-icon">👩‍🏫</span>
          <span class="rb-label">Personnel</span>
          <span class="rb-sub">Staff access</span>
        </button>
      </div>

      <form method="POST" id="loginForm" autocomplete="on">
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? 'admin') ?>">

        <div class="field-group">
          <label for="username">Username</label>
          <div class="field-wrap">
            <span class="field-icon">👤</span>
            <input type="text" id="username" name="username"
                   placeholder="Enter your username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   autocomplete="username" required>
          </div>
        </div>

        <div class="field-group">
          <label for="password">Password</label>
          <div class="field-wrap">
            <span class="field-icon">🔒</span>
            <input type="password" id="password" name="password"
                   placeholder="••••••••••"
                   autocomplete="current-password" required>
            <button type="button" class="pw-toggle" onclick="togglePw()" id="pwToggle" title="Show/hide password">👁</button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          Sign In <span class="arrow">→</span>
        </button>
      </form>

      <div class="form-footer">
        Powered by <strong>SchoolSync</strong> · TMCNHS © 2025
      </div>

    </div>
  </div>
</div>
<div id="creditsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(6px)" 
     onclick="if(event.target===this)this.classList.remove('open')">
  <!-- use JS to toggle display via .open class -->
</div>

<script>
function setRole(role) {
  document.getElementById('roleInput').value = role;
  document.getElementById('roleAdmin').classList.toggle('active', role === 'admin');
  document.getElementById('roleTeacher').classList.toggle('active', role === 'teacher');
}

function togglePw() {
  const pw = document.getElementById('password');
  const btn = document.getElementById('pwToggle');
  if (pw.type === 'password') {
    pw.type = 'text';
    btn.textContent = '🙈';
  } else {
    pw.type = 'password';
    btn.textContent = '👁';
  }
}

// Keyboard shortcut: Alt+A / Alt+P for role switch
document.addEventListener('keydown', e => {
  if (e.altKey && e.key === 'a') setRole('admin');
  if (e.altKey && e.key === 'p') setRole('teacher');
});
</script>
</body>
</html>