<?php
// teacher_shared.php — included at top of every teacher page
require_once '../Config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php'); exit;
}

$user_id = $_SESSION['user_id'];

// Teacher profile
$stmt = $conn->prepare("SELECT t.*, u.username FROM personnels t JOIN users u ON t.user_id=u.id WHERE t.user_id=?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
if (!$teacher) { echo 'Profile not found. Contact admin.'; exit; }
$teacher_id = $teacher['id'];

// Ensure table exists
$conn->query("CREATE TABLE IF NOT EXISTS leave_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    last_name VARCHAR(100), first_name VARCHAR(100),
    absent_dates TEXT, days_absent INT DEFAULT 0,
    leave_type VARCHAR(50) DEFAULT 'Sick Leave',
    reason TEXT, scan_file_path VARCHAR(255), scan_file_name VARCHAR(255),
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// BUG FIX #3: Fetch documents into an array immediately so the result set is
// fully consumed and freed before any subsequent UPDATE queries run on the
// same connection (prevents "commands out of sync" silent failures).
$docs_result = $conn->query("SELECT * FROM documents WHERE teacher_id=$teacher_id ORDER BY uploaded_at DESC");
$doc_rows    = [];
if ($docs_result) {
    while ($dr = $docs_result->fetch_assoc()) {
        $doc_rows[] = $dr;
    }
    $docs_result->free();
}
$doc_count = count($doc_rows);

// Leave data
$leave_logs        = $conn->query("SELECT * FROM leave_reports WHERE teacher_id=$teacher_id ORDER BY filed_at DESC");
$total_days_absent = 0;
$leave_rows        = [];
if ($leave_logs) while ($lr = $leave_logs->fetch_assoc()) {
    $total_days_absent += (int)$lr['days_absent'];
    $leave_rows[] = $lr;
}

$total_leave_days = !empty($teacher['leave_days_allocation']) ? (int)$teacher['leave_days_allocation'] : 30;
$leave_available  = max(0, $total_leave_days - $total_days_absent);

// Helper functions
function fileIcon(string $f): string {
    $m = ['pdf'=>'📕','doc'=>'📘','docx'=>'📘','xls'=>'📗','xlsx'=>'📗',
          'ppt'=>'📙','pptx'=>'📙','jpg'=>'🖼','jpeg'=>'🖼','png'=>'🖼','zip'=>'📦','txt'=>'📄'];
    return $m[strtolower(pathinfo($f, PATHINFO_EXTENSION))] ?? '📄';
}
function leaveClass(string $t): string {
    return ['Sick Leave'=>'leave-sick','Vacation Leave'=>'leave-vacation',
            'Emergency Leave'=>'leave-emergency','Maternity Leave'=>'leave-maternity',
            'Other'=>'leave-other'][$t] ?? 'leave-other';
}
function fmtSize(int $b): string {
    if ($b < 1024) return $b.' B';
    if ($b < 1048576) return round($b/1024,1).' KB';
    return round($b/1048576,1).' MB';
}