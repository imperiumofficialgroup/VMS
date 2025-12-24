<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin/login.php");
    exit();
}

$group_name = $_POST['group_name'] ?? null;
$event_id = $_POST['event_id'] ?? null;

if (!$group_name) {
    die("Group name is required.");
}

// Save group chat
$stmt = $conn->prepare("INSERT INTO group_chats (group_name, event_id, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("si", $group_name, $event_id);
$stmt->execute();

header("Location: group_list.php");
exit();
