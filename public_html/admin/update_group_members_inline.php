<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$group_id = $_POST['group_id'] ?? null;
$volunteer_ids = $_POST['volunteer_ids'] ?? [];

if (!$group_id) {
    http_response_code(400);
    exit("Group ID is required.");
}

// Remove existing members
$delete = $conn->prepare("DELETE FROM group_members WHERE group_id = ?");
$delete->bind_param("i", $group_id);
$delete->execute();

// Add new members
$insert = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
foreach ($volunteer_ids as $volunteer_id) {
    $insert->bind_param("ii", $group_id, $volunteer_id);
    $insert->execute();
}

header("Location: group_chat.php?group_id=$group_id");
exit();
