<?php
session_start();
require_once '../auth/db.php';

$username = $_POST['username'];
$password = $_POST['password'];

// Query admin table
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $admin = $result->fetch_assoc();
    if (password_verify($password, $admin['password_hash'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_id'] = $admin['id']; // ✅ Add this line
    header("Location: dashboard.php");
    exit();
}

}

$_SESSION['error'] = "Invalid credentials.";
header("Location: login.php");
exit();
