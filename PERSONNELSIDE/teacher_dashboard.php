<?php
// teacher_dashboard.php — PERSONNEL DASHBOARD
require_once 'teacher_shared.php';

$msg = $err = '';
$success_type = $success_doc_title = $success_doc_name = '';

// ── HANDLE UPLOAD ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_doc') {
    $title = trim($_POST['doc_title'] ?? '');
    if (!empty($_FILES['doc_file']['name']) && $title) {
        $upload_dir = 'uploads/documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
        $orig = basename($_FILES['doc_file']['name']);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip','txt']))
            $err = 'File type not allowed.';
        elseif ($_FILES['doc_file']['size'] > 10*1024*1024)
            $err = 'File must be under 10MB.';
        else {
            $fname = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
            $fpath = $upload_dir.$fname;
            if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $fpath)) {
                $s = $conn->prepare("INSERT INTO documents (teacher_id,title,file_name,file_path,file_size,file_type) VALUES (?,?,?,?,?,?)");
                $fs = $_FILES['doc_file']['size']; $ft = $_FILES['doc_file']['type'];
                $s->bind_param('isssss', $teacher_id, $title, $fname, $fpath, $fs, $ft);
                $s->execute();
                $success_type = 'doc'; $success_doc_title = $title; $success_doc_name = $fname;

                $doc_rows = [];
                $res = $conn->query("SELECT * FROM documents WHERE teacher_id=$teacher_id ORDER BY uploaded_at DESC");
                if ($res) { while ($dr = $res->fetch_assoc()) $doc_rows[] = $dr; $res->free(); }
                $doc_count = count($doc_rows);
            } else $err = 'Upload failed. Check folder permissions.';
        }
    } else $err = 'Please provide a title and select a file.';
}

$recent_docs = array_slice($doc_rows, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dashboard — TMCNHS Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<style><?php readfile(__DIR__.'/teacher_style.css.php'); ?></style>
<style>
/* ══════════════════════════════════════════════════════
   CREDITS TRIGGER — sidebar footer button
   (same pattern as admin_dashboard.php)
══════════════════════════════════════════════════════ */

/* ★ NEW: stack the footer vertically so the credits
   button sits above the existing user-chip */
.sidebar-footer {
  padding: 12px 12px 16px !important;
  border-top: 1px solid rgba(255,255,255,0.15);
  display: flex !important;
  flex-direction: column !important;
  gap: 8px !important;
}

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

/* ══════════════════════════════════════════════════════
   CREDITS MODAL STYLES
   Prefixed .cm- / .credits- / .dev- to avoid collisions
══════════════════════════════════════════════════════ */
.credits-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(10,2,4,0.82);
  z-index: 2000;
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
@keyframes creditsOverlayIn { from{opacity:0} to{opacity:1} }

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
  from { opacity:0; transform:translateY(28px) scale(0.97); }
  to   { opacity:1; transform:translateY(0)    scale(1); }
}

/* Header */
.cm-header {
  background: linear-gradient(135deg, #6b0518 0%, #9b0c22 45%, #c8102e 100%);
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
.cm-body::-webkit-scrollbar-thumb { background: #f0d4d4; border-radius: 999px; }
.cm-section-lbl {
  font-size: 10px; font-family: 'JetBrains Mono', monospace;
  text-transform: uppercase; letter-spacing: 2px; color: #9c7b7e;
  margin-bottom: 12px; display: flex; align-items: center; gap: 10px;
}
.cm-section-lbl::after { content: ''; flex: 1; height: 1px; background: #f0d4d4; }

/* Dev card */
.dev-card {
  display: flex; align-items: center; gap: 13px;
  padding: 12px 14px;
  background: #fff8f8; border: 1.5px solid #f0d4d4;
  border-radius: 13px; margin-bottom: 9px;
  transition: all 0.2s ease; position: relative; overflow: hidden;
}
.dev-card::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: linear-gradient(180deg, #c8102e, #9b0c22);
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
.dev-name  { font-size: 13.5px; font-weight: 600; color: #1a0608; }
.dev-role  { font-size: 11px; color: #9c7b7e; font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
.dev-tags  { display: flex; gap: 5px; flex-wrap: wrap; flex-shrink: 0; margin-left: auto; }
.dtag { padding: 2px 8px; border-radius: 999px; font-size: 10px; font-family: 'JetBrains Mono', monospace; border: 1px solid; }
.dtag-red    { background: rgba(200,16,46,0.07);  color: #c8102e;  border-color: rgba(200,16,46,0.2); }
.dtag-gold   { background: rgba(212,168,75,0.1);  color: #9a6f10;  border-color: rgba(212,168,75,0.3); }
.dtag-blue   { background: rgba(29,78,216,0.07);  color: #1d4ed8;  border-color: rgba(29,78,216,0.2); }
.dtag-green  { background: rgba(5,150,105,0.07);  color: #059669;  border-color: rgba(5,150,105,0.2); }
.dtag-purple { background: rgba(123,31,162,0.07); color: #7b1fa2;  border-color: rgba(123,31,162,0.2); }

/* Tech stack */
.stack-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin-bottom: 20px; }
.stack-item {
  background: #fff8f8; border: 1px solid #f0d4d4;
  border-radius: 10px; padding: 9px 11px;
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; font-weight: 500; color: #1a0608;
  transition: border-color 0.18s;
}
.stack-item:hover { border-color: rgba(200,16,46,0.3); }
.stack-item .si { font-size: 15px; flex-shrink: 0; }

/* Acknowledgement */
.ack-box {
  background: #fff8f8; border: 1px solid #f0d4d4;
  border-radius: 12px; padding: 13px 15px;
  font-size: 13px; color: #9c7b7e; line-height: 1.6;
}
.ack-box strong { color: #1a0608; }

/* Footer */
.cm-footer {
  padding: 14px 28px 18px;
  border-top: 1px solid #f0d4d4;
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0; flex-wrap: wrap; gap: 10px;
}
.cm-footer-note { font-size: 11px; color: #9c7b7e; font-family: 'JetBrains Mono', monospace; }
.cm-footer-note strong { color: #c8102e; }
.cm-close-footer {
  padding: 8px 20px; border-radius: 9px;
  background: #c8102e; border: none;
  color: #fff; font-family: 'DM Sans', sans-serif;
  font-size: 12px; font-weight: 600; cursor: pointer;
  transition: background 0.2s;
}
.cm-close-footer:hover { background: #9b0c22; }
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
    <a class="nav-item active" href="teacher_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item" href="teacher_documents.php"><span class="ni">📁</span> My Documents</a>
    <a class="nav-item" href="teacher_service_credit.php"><span class="ni">📋</span> Service Credit</a>
    <a class="nav-item" href="teacher_profile.php"><span class="ni">👤</span> My Profile</a>
  </nav>

  <!-- ── SIDEBAR FOOTER ── -->
  <div class="sidebar-footer">

    <!-- ★ NEW: Credits trigger button -->
    <button class="credits-trigger" onclick="openCredits()">
      <span class="ct-icon">👥</span>
      <span>Development Team</span>
    </button>

    <!-- Existing user chip (unchanged) -->
    <div class="user-chip">
      <div class="avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
      <div class="user-info">
        <strong><?= htmlspecialchars($teacher['full_name']) ?></strong>
        <small>Teacher</small>
      </div>
      <a href="../logout.php" class="logout-btn" title="Logout" style="font-size: 1.5rem;">⏏</a>
    </div>

  </div>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>Dashboard</h1><p>Welcome back, <?= htmlspecialchars($teacher['full_name']) ?>!</p></div>
    <div class="topbar-actions">
      <button class="btn btn-blue" onclick="openUploadModal()">📤 Upload Document</button>
    </div>
  </div>

  <div class="page">
    <?php if ($err): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card green">
        <div class="stat-icon">📄</div>
        <div class="stat-num"><?= $doc_count ?></div>
        <div class="stat-label">Documents Uploaded</div>
      </div>
      <div class="stat-card <?= $leave_available <= 5 ? 'danger' : ($leave_available <= 10 ? 'warn' : 'blue') ?>">
        <div class="stat-icon"><?= $leave_available <= 5 ? '⚠️' : '🏖️' ?></div>
        <div class="stat-num" style="color:<?= $leave_available<=5?'var(--danger)':($leave_available<=10?'var(--warn)':'inherit') ?>"><?= $leave_available ?></div>
        <div class="stat-label">Leave Days Available</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon">📋</div>
        <div class="stat-num"><?= count($leave_rows) ?></div>
        <div class="stat-label">Leave Reports Filed</div>
      </div>
    </div>

    <!-- PROFILE HERO -->
    <div class="profile-hero">
      <div class="prof-avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
      <div class="prof-info">
        <h2><?= htmlspecialchars($teacher['full_name']) ?></h2>
        <p><?= htmlspecialchars($teacher['email']) ?></p>
        <div class="prof-tags">
          <?php if ($teacher['subject']): ?><span class="tag tag-green"><?= htmlspecialchars($teacher['subject']) ?></span><?php endif; ?>
          <?php if ($teacher['department']): ?><span class="tag tag-blue"><?= htmlspecialchars($teacher['department']) ?></span><?php endif; ?>
          <?php if ($teacher['phone']): ?><span class="tag tag-blue">📞 <?= htmlspecialchars($teacher['phone']) ?></span><?php endif; ?>
          <span class="tag tag-purple">🏖 <?= $leave_available ?> leave days left</span>
          <?php if ($total_days_absent > 0): ?><span class="tag tag-red">❌ <?= $total_days_absent ?> days absent</span><?php endif; ?>
        </div>
      </div>
      <div style="margin-left:auto"><a class="btn btn-ghost btn-sm" href="teacher_profile.php">✏️ Edit Profile</a></div>
    </div>

    <!-- RECENT DOCUMENTS -->
    <div class="section-head">
      <h2>Recent Documents</h2>
      <a class="btn btn-ghost btn-sm" href="teacher_documents.php">View All →</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($recent_docs)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:48px">
            <div style="font-size:36px;margin-bottom:10px">📭</div>No documents yet. Upload your first one!
          </td></tr>
        <?php else: foreach ($recent_docs as $d):
          $size = $d['file_size'] ? fmtSize((int)$d['file_size']) : '—';
          $ext  = strtoupper(pathinfo($d['file_name'], PATHINFO_EXTENSION));
        ?>
          <tr>
            <td><div style="display:flex;align-items:center;gap:10px">
              <span class="file-icon"><?= fileIcon($d['file_name']) ?></span>
              <div><div><?= htmlspecialchars($d['title']) ?></div>
              <div style="color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace"><?= htmlspecialchars($d['file_name']) ?></div></div>
            </div></td>
            <td><span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--accent)"><?= $ext ?></span></td>
            <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:11px"><?= $size ?></td>
            <td style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= date('M d, Y', strtotime($d['uploaded_at'])) ?></td>
            <td style="display:flex;gap:6px;align-items:center">
              <a class="btn btn-ghost btn-sm" href="../<?= htmlspecialchars($d['file_path']) ?>" target="_blank">👁 View</a>
              <a class="btn btn-ghost btn-sm" href="../<?= htmlspecialchars($d['file_path']) ?>" download="<?= htmlspecialchars($d['file_name']) ?>">⬇ Download</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- SUCCESS MODAL: DOC -->
<div class="success-modal-overlay" id="docSuccessModal">
  <div class="success-modal doc-success">
    <div class="confetti-container" id="docConfetti"></div>
    <div class="success-icon-wrap doc-icon">📄<div class="check-badge">✓</div></div>
    <div class="success-title">File Uploaded!</div>
    <div class="success-subtitle">Your document is now saved in <strong>My Documents</strong>.</div>
    <div class="success-detail-pill doc-pill">📎 <span id="docSuccessName"></span></div>
    <button class="success-close-btn doc-close" onclick="closeDocSuccess()">✓ Got it!</button>
  </div>
</div>

<!-- SUCCESS MODAL: LEAVE -->
<div class="success-modal-overlay" id="leaveSuccessModal">
  <div class="success-modal leave-success">
    <div class="confetti-container" id="leaveConfetti"></div>
    <div class="success-icon-wrap leave-icon">📋<div class="check-badge">✓</div></div>
    <div class="success-title">Leave Filed!</div>
    <div class="success-subtitle">Your leave report is reflected in <strong>Service Credit</strong>.</div>
    <div class="success-detail-pill leave-pill">🏖️ <span id="leaveSuccessInfo"></span></div>
    <button class="success-close-btn leave-close" onclick="closeLeaveSuccess()">✓ Got it!</button>
  </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal-overlay" id="uploadModal">
  <div class="modal">
    <h2>📤 Upload Document</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_doc">
      <div class="form-group"><label>Document Title *</label><input type="text" name="doc_title" placeholder="e.g. Lesson Plan Q1" required></div>
      <div class="drop-zone" onclick="document.getElementById('fileInput').click()" id="dropZone">
        <span>📂</span><p>Click to browse or drag & drop</p>
        <p style="font-size:11px;color:var(--muted);margin-top:4px">PDF, DOC, XLS, PPT, Images, ZIP — Max 10MB</p>
        <div id="fileNameDisplay" style="color:var(--accent);font-size:12px;font-family:'JetBrains Mono',monospace;margin-top:6px"></div>
      </div>
      <input type="file" id="fileInput" name="doc_file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.txt">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeUploadModal()">Cancel</button>
        <button type="submit" class="btn btn-blue">Upload →</button>
      </div>
    </form>
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
const SUCCESS_TYPE      = <?= json_encode($success_type) ?>;
const SUCCESS_DOC_TITLE = <?= json_encode($success_doc_title) ?>;
const SUCCESS_DOC_NAME  = <?= json_encode($success_doc_name) ?>;
</script>
<script src="teacher_js.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (SUCCESS_TYPE === 'doc') setTimeout(() => openDocSuccess(SUCCESS_DOC_TITLE || SUCCESS_DOC_NAME), 300);
});

/* ── CREDITS MODAL ── ★ NEW ── */
function openCredits()  { document.getElementById('creditsModal').classList.add('open'); }
function closeCredits() { document.getElementById('creditsModal').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCredits(); });
</script>
</body>
</html>