<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

$group_id = $_POST['group_id'];
$selected_volunteers = $_POST['volunteers'] ?? [];

// Clear existing
$conn->query("DELETE FROM group_members WHERE group_id = $group_id");

// Re-insert
$stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
foreach ($selected_volunteers as $vid) {
    $stmt->bind_param("ii", $group_id, $vid);
    $stmt->execute();
}

header("Location: group_list.php");
exit();
