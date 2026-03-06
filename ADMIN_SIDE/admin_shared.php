<?php
// admin_shared.php — Common bootstrap for all admin pages
require_once '../Config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

// ── Detect base path automatically (works for any subfolder name) ─────────────
// e.g. if project is at C:/xampp/htdocs/TMCNHS-SYSTEM/, base = /TMCNHS-SYSTEM
$_BASE = rtrim(str_replace(
    str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']),
    '',
    str_replace('\\', '/', dirname(dirname(__FILE__)))
), '/');

function asset_url($path) {
    global $_BASE;
    if (empty($path)) return '';
    $path = str_replace('\\', '/', $path);
    // Remove any accidental absolute server path prefix
    $root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
    if (strpos($path, $root) === 0) {
        $path = substr($path, strlen($root));
    }
    // Strip leading slashes so we can re-add cleanly
    $path = ltrim($path, '/');
    return $_BASE . '/' . $path;
}

// ── ENSURE leave_reports TABLE EXISTS ────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS leave_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    last_name VARCHAR(100),
    first_name VARCHAR(100),
    absent_dates TEXT,
    days_absent INT DEFAULT 0,
    leave_type VARCHAR(50) DEFAULT 'Sick Leave',
    reason TEXT,
    scan_file_path VARCHAR(255),
    scan_file_name VARCHAR(255),
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ── FETCH ALL TEACHERS ────────────────────────────────────────────────────────
$teachers_arr = [];
$res = $conn->query("SELECT t.*, u.username, u.created_at as joined FROM personnels t JOIN users u ON t.user_id=u.id ORDER BY t.full_name");
if ($res) while ($row = $res->fetch_assoc()) $teachers_arr[] = $row;

// ── FETCH DOCUMENTS (grouped by teacher_id) ───────────────────────────────────
$docs_by_teacher = [];
$docs_arr = [];
$res2 = $conn->query("SELECT d.*, t.full_name FROM documents d JOIN personnels t ON d.teacher_id=t.id ORDER BY d.uploaded_at DESC");
if ($res2) while ($row = $res2->fetch_assoc()) {
    $row['file_path'] = asset_url($row['file_path']);
    $docs_arr[] = $row;
    $docs_by_teacher[$row['teacher_id']][] = $row;
}

// ── FETCH LEAVE REPORTS (grouped by teacher_id) ───────────────────────────────
$leave_by_teacher   = [];
$leave_total_absent = [];
$res3 = $conn->query("SELECT * FROM leave_reports ORDER BY filed_at DESC");
if ($res3) {
    while ($row = $res3->fetch_assoc()) {
        $tid = $row['teacher_id'];
        if (!empty($row['scan_file_path'])) {
            $row['scan_file_path'] = asset_url($row['scan_file_path']);
        }
        $leave_by_teacher[$tid][] = $row;
        $leave_total_absent[$tid] = ($leave_total_absent[$tid] ?? 0) + (int)$row['days_absent'];
    }
}

$total_leave_days = 30;
$total_teachers   = count($teachers_arr);
$total_docs       = count($docs_arr);
$active_teachers  = count(array_filter($teachers_arr, fn($t) => $t['status'] === 'active'));

// JSON for JS
$docs_by_teacher_json  = json_encode($docs_by_teacher);
$leave_by_teacher_json = json_encode($leave_by_teacher);
$leave_total_json      = json_encode($leave_total_absent);