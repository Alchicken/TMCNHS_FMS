<?php
// teacher_documents.php — MY DOCUMENTS
require_once 'teacher_shared.php';

$msg = $err = '';
$success_type = $success_doc_title = $success_doc_name = '';

// Root of the project e.g. C:/xampp/htdocs/TMCNHS-SYSTEM
$_PROJECT_ROOT = dirname(__DIR__);

// Base URL e.g. /TMCNHS-SYSTEM
$_BASE = rtrim(str_replace(
    str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']),
    '',
    str_replace('\\', '/', $_PROJECT_ROOT)
), '/');

function file_url($path) {
    global $_BASE;
    if (empty($path)) return '';
    $path = str_replace('\\', '/', $path);
    $root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    if (strpos($path, $root) === 0) $path = substr($path, strlen($root));
    return $_BASE . '/' . ltrim($path, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // UPLOAD
    if ($_POST['action'] === 'upload_doc') {
        $title = trim($_POST['doc_title'] ?? '');
        if (!empty($_FILES['doc_file']['name']) && $title) {
            $upload_dir = $_PROJECT_ROOT . '/uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            $orig = basename($_FILES['doc_file']['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png','zip','txt']))
                $err = 'File type not allowed.';
            elseif ($_FILES['doc_file']['size'] > 10*1024*1024)
                $err = 'File must be under 10MB.';
            else {
                $fname = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                $fpath = 'uploads/documents/'.$fname;
                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $upload_dir.$fname)) {
                    $s = $conn->prepare("INSERT INTO documents (teacher_id,title,file_name,file_path,file_size,file_type) VALUES (?,?,?,?,?,?)");
                    $fs = $_FILES['doc_file']['size']; $ft = $_FILES['doc_file']['type'];
                    $s->bind_param('isssss', $teacher_id, $title, $fname, $fpath, $fs, $ft);
                    $s->execute();
                    $success_type = 'doc'; $success_doc_title = $title; $success_doc_name = $fname;
                    // Re-fetch into array after upload
                    $doc_rows = [];
                    $res = $conn->query("SELECT * FROM documents WHERE teacher_id=$teacher_id ORDER BY uploaded_at DESC");
                    if ($res) { while ($dr = $res->fetch_assoc()) $doc_rows[] = $dr; $res->free(); }
                    $doc_count = count($doc_rows);
                } else $err = 'Upload failed. Check folder permissions.';
            }
        } else $err = 'Please provide a title and select a file.';
    }

    // DELETE
    if ($_POST['action'] === 'delete_doc') {
        $did = (int)$_POST['doc_id'];
        $r = $conn->query("SELECT * FROM documents WHERE id=$did AND teacher_id=$teacher_id")->fetch_assoc();
        if ($r) {
            $physical = $_PROJECT_ROOT . '/' . ltrim($r['file_path'], '/');
            if (file_exists($physical)) unlink($physical);
            $conn->query("DELETE FROM documents WHERE id=$did");
            $msg = 'Document deleted.';
            // Re-fetch into array after delete
            $doc_rows = [];
            $res = $conn->query("SELECT * FROM documents WHERE teacher_id=$teacher_id ORDER BY uploaded_at DESC");
            if ($res) { while ($dr = $res->fetch_assoc()) $doc_rows[] = $dr; $res->free(); }
            $doc_count = count($doc_rows);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Documents — TMCNHS Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="../uploads/logo/TMCNHS-Logo.png">
<style><?php readfile(__DIR__.'/teacher_style.css.php'); ?></style>
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
    <a class="nav-item" href="teacher_dashboard.php"><span class="ni">&#128202;</span> Dashboard</a>
    <a class="nav-item active" href="teacher_documents.php"><span class="ni">&#128193;</span> My Documents</a>
    <a class="nav-item" href="teacher_service_credit.php"><span class="ni">&#128203;</span> Service Credit</a>
    <a class="nav-item" href="teacher_profile.php"><span class="ni">&#128100;</span> My Profile</a>
  </nav>
  <div class="sidebar-footer"><div class="user-chip">
    <div class="avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($teacher['full_name']) ?></strong><small>Teacher</small></div>
    <a href="../logout.php" class="logout-btn" title="Logout" style="font-size: 1.5rem;">&#9167;</a>
  </div></div>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>My Documents</h1><p><?= $doc_count ?> document<?= $doc_count!=1?'s':'' ?> uploaded</p></div>
    <div class="topbar-actions">
      <button class="btn btn-blue" onclick="openUploadModal()">&#128228; Upload Document</button>
    </div>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">&#9989; <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">&#9888;&#65039; <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Document</th><th>Type</th><th>Size</th><th>Uploaded</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($doc_rows)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:56px">
            <div style="font-size:40px;margin-bottom:12px">&#128141;</div>
            <div>No documents yet.</div>
            <div style="font-size:11px;margin-top:4px">Click "Upload Document" to get started.</div>
          </td></tr>
        <?php else: foreach ($doc_rows as $d):
          $ext  = strtoupper(pathinfo($d['file_name'], PATHINFO_EXTENSION));
          $size = $d['file_size'] ? fmtSize((int)$d['file_size']) : '—';
          $url  = file_url($d['file_path']);
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
            <td>
              <div style="display:flex;gap:6px">
                <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($url) ?>" target="_blank">&#128065; View</a>
                <a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($url) ?>" download>&#11015; Download</a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this document?')">
                  <input type="hidden" name="action" value="delete_doc">
                  <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">&#128465;</button>
                </form>
              </div>
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
    <div class="success-icon-wrap doc-icon">&#128196;<div class="check-badge">&#10003;</div></div>
    <div class="success-title">File Uploaded Successfully!</div>
    <div class="success-subtitle">Your document is now available in this list.</div>
    <div class="success-detail-pill doc-pill">&#128206; <span id="docSuccessName"></span></div>
    <button class="success-close-btn doc-close" onclick="closeDocSuccess()">&#10003; Got it!</button>
  </div>
</div>

<!-- SUCCESS MODAL: LEAVE placeholder -->
<div class="success-modal-overlay" id="leaveSuccessModal" style="display:none">
  <div class="success-modal leave-success">
    <div class="confetti-container" id="leaveConfetti"></div>
    <div class="success-icon-wrap leave-icon">&#128203;<div class="check-badge">&#10003;</div></div>
    <div class="success-title">Leave Filed!</div><div class="success-subtitle"></div>
    <div class="success-detail-pill leave-pill">&#127958;&#65039; <span id="leaveSuccessInfo"></span></div>
    <button class="success-close-btn leave-close" onclick="closeLeaveSuccess()">&#10003; Got it!</button>
  </div>
</div>

<!-- UPLOAD MODAL -->
<div class="modal-overlay" id="uploadModal">
  <div class="modal">
    <h2>&#128228; Upload Document</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_doc">
      <div class="form-group"><label>Document Title *</label><input type="text" name="doc_title" placeholder="e.g. Lesson Plan Q1" required></div>
      <div class="drop-zone" onclick="document.getElementById('fileInput').click()" id="dropZone">
        <span>&#128194;</span><p>Click to browse or drag &amp; drop</p>
        <p style="font-size:11px;color:var(--muted);margin-top:4px">PDF, DOC, XLS, PPT, Images, ZIP — Max 10MB</p>
        <div id="fileNameDisplay" style="color:var(--accent);font-size:12px;font-family:'JetBrains Mono',monospace;margin-top:6px"></div>
      </div>
      <input type="file" id="fileInput" name="doc_file" style="display:none" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.txt">
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeUploadModal()">Cancel</button>
        <button type="submit" class="btn btn-blue">Upload &#8594;</button>
      </div>
    </form>
  </div>
</div>
<div class="modal-overlay" id="leaveModal"></div>

<script>
const SUCCESS_TYPE = <?= json_encode($success_type) ?>;
const SUCCESS_DOC_TITLE = <?= json_encode($success_doc_title) ?>;
const SUCCESS_DOC_NAME  = <?= json_encode($success_doc_name) ?>;
</script>
<script src="teacher_js.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (SUCCESS_TYPE === 'doc') setTimeout(() => openDocSuccess(SUCCESS_DOC_TITLE || SUCCESS_DOC_NAME), 300);
});
</script>
</body>
</html>