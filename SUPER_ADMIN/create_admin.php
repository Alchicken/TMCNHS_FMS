<?php
// SUPER_ADMIN/create_admin.php
// Only super_admin can POST to this endpoint
require_once '../Config.php';
require_once '../helpers/ActionRequest.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ../index.php'); exit;
}

$ar = new ActionRequest($conn);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        // Check duplicate
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $chk->bind_param('s', $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Username '$username' already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $username, $hash, $role);
            $stmt->execute();

            $ar->log($_SESSION['user_id'], 'super_admin', 'created_admin', "Created admin account: $username");

            header('Location: superadmin_dashboard.php?msg=admin_created'); exit;
        }
    }
}

// If error, bounce back with session flash
$_SESSION['sa_error'] = $error;
header('Location: superadmin_dashboard.php'); exit;