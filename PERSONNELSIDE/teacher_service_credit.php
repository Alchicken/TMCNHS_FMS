<?php
// teacher_service_credit.php — SERVICE CREDIT & LEAVE TRACKING
require_once 'teacher_shared.php';

$msg = $err = '';
$success_type = '';
$success_leave_type = '';
$success_leave_days = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // ── FILE LEAVE REPORT ──────────────────────────────────────────────────────
    if ($_POST['action'] === 'file_leave') {
        $dates_json  = trim($_POST['absent_dates'] ?? '');
        $days_absent = (int)($_POST['days_absent'] ?? 0);
        $leave_type  = trim($_POST['leave_type'] ?? 'Sick Leave');
        $reason      = trim($_POST['reason'] ?? '');

        $absent_dates = json_decode($dates_json, true);
        if (!is_array($absent_dates)) $absent_dates = [];

        // Name from teacher record
        $parts      = explode(' ', trim($teacher['full_name']));
        $first_name = $parts[0] ?? '';
        $last_name  = count($parts) > 1 ? end($parts) : '';

        // Optional scan upload
        $scan_path = $scan_name = '';
        if (!empty($_FILES['scan_file']['name'])) {
            $upload_dir = 'uploads/leave_reports/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
            $orig = basename($_FILES['scan_file']['name']);
            $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','jpg','jpeg','png'])) {
                $scan_name = time().'_leave_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$orig);
                $scan_path = $upload_dir.$scan_name;
                if (!move_uploaded_file($_FILES['scan_file']['tmp_name'], $scan_path))
                    $scan_path = $scan_name = '';
            }
        }

        if ($days_absent > 0 && !empty($absent_dates)) {
            $dates_str  = implode(',', $absent_dates);
            $s = $conn->prepare("INSERT INTO leave_reports (teacher_id,last_name,first_name,absent_dates,days_absent,leave_type,reason,scan_file_path,scan_file_name) VALUES (?,?,?,?,?,?,?,?,?)");
            $s->bind_param('isssissss', $teacher_id, $last_name, $first_name, $dates_str, $days_absent, $leave_type, $reason, $scan_path, $scan_name);
            if ($s->execute()) {
                $success_type       = 'leave';
                $success_leave_type = $leave_type;
                $success_leave_days = $days_absent;
                // Re-fetch leave data
                $leave_logs        = $conn->query("SELECT * FROM leave_reports WHERE teacher_id=$teacher_id ORDER BY filed_at DESC");
                $total_days_absent = 0; $leave_rows = [];
                while ($lr = $leave_logs->fetch_assoc()) { $total_days_absent += (int)$lr['days_absent']; $leave_rows[] = $lr; }
                $leave_available = max(0, $total_leave_days - $total_days_absent);
            } else $err = 'Failed to file leave report: '.$conn->error;
        } else $err = 'Please select at least one absent date.';
    }

    // ── DELETE LEAVE REPORT ────────────────────────────────────────────────────
    if ($_POST['action'] === 'delete_leave') {
        $lid = (int)$_POST['leave_id'];
        $lr  = $conn->query("SELECT * FROM leave_reports WHERE id=$lid AND teacher_id=$teacher_id")->fetch_assoc();
        if ($lr) {
            if ($lr['scan_file_path'] && file_exists($lr['scan_file_path'])) unlink($lr['scan_file_path']);
            $conn->query("DELETE FROM leave_reports WHERE id=$lid");
            $msg = 'Leave record deleted.';
            $leave_logs        = $conn->query("SELECT * FROM leave_reports WHERE teacher_id=$teacher_id ORDER BY filed_at DESC");
            $total_days_absent = 0; $leave_rows = [];
            while ($lr2 = $leave_logs->fetch_assoc()) { $total_days_absent += (int)$lr2['days_absent']; $leave_rows[] = $lr2; }
            $leave_available = max(0, $total_leave_days - $total_days_absent);
        }
    }
}

$pct       = $total_leave_days > 0 ? min(100, round(($total_days_absent / $total_leave_days) * 100)) : 0;
$bar_class = $pct >= 80 ? 'danger-fill' : ($pct >= 50 ? 'warn-fill' : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Service Credit — TMCNHS Teacher Portal</title>
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
    <a class="nav-item" href="teacher_dashboard.php"><span class="ni">📊</span> Dashboard</a>
    <a class="nav-item" href="teacher_documents.php"><span class="ni">📁</span> My Documents</a>
    <a class="nav-item active" href="teacher_service_credit.php"><span class="ni">📋</span> Service Credit</a>
    <a class="nav-item" href="teacher_profile.php"><span class="ni">👤</span> My Profile</a>
  </nav>
  <div class="sidebar-footer"><div class="user-chip">
    <div class="avatar"><?= mb_strtoupper(mb_substr($teacher['full_name'],0,1)) ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($teacher['full_name']) ?></strong><small>Teacher</small></div>
    <a href="../logout.php" class="logout-btn" title="Logout" style="font-size: 1.5rem;">⏏</a>
  </div></div>
</aside>

<main class="main">
  <div class="topbar">
    <div><h1>Service Credit</h1><p>Leave & Absence Tracking</p></div>
    <div class="topbar-actions">
      <button class="btn btn-purple" onclick="openLeaveModal()">📝 File Leave Report</button>
    </div>
  </div>

  <div class="page">
    <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- LEAVE STATS -->
    <div class="leave-stats">
      <div class="leave-stat-card green">
        <div class="ls-icon">🏖️</div>
        <div class="ls-num"><?= $total_leave_days ?></div>
        <div class="ls-label">Total Leave Allocation</div>
        <div class="ls-sub" style="color:var(--accent)">per year</div>
      </div>
      <div class="leave-stat-card <?= $leave_available<=5?'danger':($leave_available<=10?'warn':'blue') ?>">
        <div class="ls-icon"><?= $leave_available<=5?'⚠️':'✅' ?></div>
        <div class="ls-num" style="color:<?= $leave_available<=5?'var(--danger)':($leave_available<=10?'var(--warn)':'var(--accent)') ?>"><?= $leave_available ?></div>
        <div class="ls-label">Leave Days Available</div>
        <div class="ls-sub" style="color:var(--muted)">remaining</div>
      </div>
      <div class="leave-stat-card danger">
        <div class="ls-icon">📅</div>
        <div class="ls-num" style="color:var(--danger)"><?= $total_days_absent ?></div>
        <div class="ls-label">Total Days Absent</div>
        <div class="ls-sub" style="color:var(--muted)">this year</div>
      </div>
      <div class="leave-stat-card purple">
        <div class="ls-icon">📝</div>
        <div class="ls-num" style="color:var(--purple)"><?= count($leave_rows) ?></div>
        <div class="ls-label">Leave Reports Filed</div>
        <div class="ls-sub" style="color:var(--muted)">records</div>
      </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="leave-progress-wrap">
      <h3>
        <span>📊 Leave Consumption</span>
        <span style="font-size:12px;font-family:'JetBrains Mono',monospace;color:var(--muted)"><?= $total_days_absent ?> / <?= $total_leave_days ?> days used</span>
      </h3>
      <div class="progress-bar-bg">
        <div class="progress-bar-fill <?= $bar_class ?>" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="progress-labels"><span> <?= $total_days_absent?> days</span><span><?= $pct ?>% consumed</span><span><?= $total_leave_days ?> days</span></div>
    </div>

    <!-- LEAVE LOGS TABLE -->
    <div class="section-head">
      <h2>📋 Absent Logs</h2>
      <span style="color:var(--muted);font-size:12px;font-family:'JetBrains Mono',monospace"><?= count($leave_rows) ?> record<?= count($leave_rows)!=1?'s':'' ?></span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Leave Type</th><th>Dates Absent</th><th>Days</th><th>Reason</th><th>Scan</th><th>Filed</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (empty($leave_rows)): ?>
          <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:56px">
            <div style="font-size:40px;margin-bottom:12px">📋</div>
            <div>No leave records yet.</div>
            <div style="font-size:11px;margin-top:4px">Click "File Leave Report" to add your first record.</div>
          </td></tr>
        <?php else: foreach ($leave_rows as $i => $lr):
          $date_arr = $lr['absent_dates'] ? explode(',', $lr['absent_dates']) : [];
        ?>
          <tr>
            <td style="color:var(--muted);font-family:'JetBrains Mono',monospace;font-size:11px"><?= $i+1 ?></td>
            <td><span class="leave-type-badge <?= leaveClass($lr['leave_type']) ?>"><?= htmlspecialchars($lr['leave_type']) ?></span></td>
            <td><div class="date-chips"><?php foreach($date_arr as $dt): ?><span class="date-chip"><?= htmlspecialchars(trim($dt)) ?></span><?php endforeach; ?></div></td>
            <td><span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:var(--danger)"><?= (int)$lr['days_absent'] ?></span> <span style="color:var(--muted);font-size:11px">day<?= $lr['days_absent']!=1?'s':'' ?></span></td>
            <td style="max-width:140px"><div style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:130px" title="<?= htmlspecialchars($lr['reason']) ?>"><?= $lr['reason'] ? htmlspecialchars($lr['reason']) : '<span style="color:var(--muted)">—</span>' ?></div></td>
            <td><?php if ($lr['scan_file_path'] && file_exists($lr['scan_file_path'])): ?><a class="btn btn-ghost btn-sm" href="<?= htmlspecialchars($lr['scan_file_path']) ?>" target="_blank">📎 View</a><?php else: ?><span style="color:var(--muted);font-size:11px">No file</span><?php endif; ?></td>
            <td style="font-size:11px;font-family:'JetBrains Mono',monospace;color:var(--muted);white-space:nowrap"><?= date('M d, Y', strtotime($lr['filed_at'])) ?></td>
            <td>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this leave record?')">
                <input type="hidden" name="action" value="delete_leave">
                <input type="hidden" name="leave_id" value="<?= $lr['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- SUCCESS MODAL: LEAVE -->
<div class="success-modal-overlay" id="leaveSuccessModal">
  <div class="success-modal leave-success">
    <div class="confetti-container" id="leaveConfetti"></div>
    <div class="success-icon-wrap leave-icon">📋<div class="check-badge">✓</div></div>
    <div class="success-title">Leave Filed Successfully!</div>
    <div class="success-subtitle">Your leave report has been recorded and reflected in your <strong>Service Credit</strong> tracker.</div>
    <div class="success-detail-pill leave-pill">🏖️ <span id="leaveSuccessInfo"></span></div>
    <button class="success-close-btn leave-close" onclick="closeLeaveSuccess()">✓ Got it!</button>
  </div>
</div>

<!-- SUCCESS MODAL: DOC (placeholder) -->
<div class="success-modal-overlay" id="docSuccessModal" style="display:none">
  <div class="success-modal doc-success"><div class="confetti-container" id="docConfetti"></div>
    <div class="success-icon-wrap doc-icon">📄<div class="check-badge">✓</div></div>
    <div class="success-title"></div><div class="success-subtitle"></div>
    <div class="success-detail-pill doc-pill">📎 <span id="docSuccessName"></span></div>
    <button class="success-close-btn doc-close" onclick="closeDocSuccess()">✓ Got it!</button>
  </div>
</div>

<!-- UPLOAD MODAL (placeholder) -->
<div class="modal-overlay" id="uploadModal"></div>

<!-- LEAVE REPORT MODAL -->
<div class="modal-overlay" id="leaveModal">
  <div class="modal modal-wide">
    <h2>📝 File Leave Report</h2>
    <form method="POST" enctype="multipart/form-data" id="leaveForm">
      <input type="hidden" name="action" value="file_leave">
      <input type="hidden" name="absent_dates" id="absentDatesInput">
      <input type="hidden" name="days_absent" id="daysAbsentInput">

      <!-- Teacher info display (read-only) -->
      <div class="form-group" style="margin-bottom:18px">
        <label>Teacher</label>
        <div style="padding:11px 14px;background:rgba(200,16,46,.06);border:1px solid rgba(200,16,46,.2);border-radius:9px;display:flex;align-items:center;gap:10px">
          <span style="font-size:20px">👤</span>
          <div>
            <div style="font-weight:600"><?= htmlspecialchars($teacher['full_name']) ?></div>
            <div style="color:var(--muted);font-size:11px;font-family:'JetBrains Mono',monospace"><?= htmlspecialchars(($teacher['subject']??'').' — '.($teacher['department']??'')) ?></div>
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Leave Type *</label>
          <select name="leave_type">
            <option value="Sick Leave">Sick Leave</option>
            <option value="Vacation Leave">Vacation Leave</option>
            <option value="Emergency Leave">Emergency Leave</option>
            <option value="Maternity Leave">Maternity Leave</option>
            <option value="Other">Other</option>
          </select>
        </div>
        <div class="form-group">
          <label>Days Absent <span id="daysAbsentDisplay" style="color:var(--purple);font-weight:700"></span></label>
          <input type="text" id="daysAbsentReadonly" placeholder="Auto-calculated from selected dates" readonly style="cursor:not-allowed;opacity:.7">
        </div>
      </div>

      <div class="form-group">
        <label>Select Date(s) of Absence * <span style="color:var(--muted);font-size:10px">(click multiple dates)</span></label>
        <div class="date-picker-wrap">
          <div class="date-picker-header">
            <button type="button" class="date-nav-btn" onclick="changeMonth(-1)">‹</button>
            <span id="calMonthLabel"></span>
            <button type="button" class="date-nav-btn" onclick="changeMonth(1)">›</button>
          </div>
          <div class="date-grid" id="calGrid"></div>
          <div class="selected-dates-display" id="selectedDatesDisplay"><span class="no-dates">No dates selected</span></div>
        </div>
      </div>

      <div class="form-group">
        <label>Reason / Remarks</label>
        <textarea name="reason" placeholder="Brief description of reason for absence..." rows="2"></textarea>
      </div>

      <div class="form-group">
        <label>Upload Scan Copy <span style="color:var(--muted);font-size:10px">Optional — PDF / JPG / PNG</span></label>
        <div class="drop-zone-sm" onclick="document.getElementById('leaveScanInput').click()" id="leaveDropZone">
          <div class="dz-icon">📎</div>
          <p>Click to upload scanned leave report</p>
          <div id="leaveFileNameDisplay" style="color:var(--purple);font-size:11px;font-family:'JetBrains Mono',monospace;margin-top:4px"></div>
        </div>
        <input type="file" id="leaveScanInput" name="scan_file" style="display:none" accept=".pdf,.jpg,.jpeg,.png">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeLeaveModal()">Cancel</button>
        <button type="submit" class="btn btn-purple">📋 Submit Leave Report</button>
      </div>
    </form>
  </div>
</div>

<script>
const SUCCESS_TYPE       = <?= json_encode($success_type) ?>;
const SUCCESS_LEAVE_TYPE = <?= json_encode($success_leave_type) ?>;
const SUCCESS_LEAVE_DAYS = <?= json_encode($success_leave_days) ?>;
</script>
<script src="teacher_js.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  if (SUCCESS_TYPE === 'leave') setTimeout(() => openLeaveSuccess(SUCCESS_LEAVE_TYPE, SUCCESS_LEAVE_DAYS), 300);
});
</script>
</body>
</html>