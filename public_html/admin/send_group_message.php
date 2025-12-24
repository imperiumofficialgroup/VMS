<?php
session_start();
require_once '../auth/db.php';

// Initialize defaults
$sender_id = null;
$sender_role = null;

// Determine sender identity (volunteer takes priority if both are set)
if (isset($_SESSION['volunteer_id'])) {
    $sender_id = $_SESSION['volunteer_id'];
    $sender_role = 'volunteer';
} elseif (isset($_SESSION['admin_logged_in'])) {
    $sender_id = 1;
    $sender_role = 'admin';
} else {
    http_response_code(403);
    echo "Unauthorized access.";
    exit();
}


// Retrieve and sanitize POST data
$group_id = $_POST['group_id'] ?? null;
$message = trim($_POST['message'] ?? '');

if (!$group_id || empty($message)) {
    http_response_code(400);
    echo "Group ID or message missing.";
    exit();
}

// Insert group message
$stmt = $conn->prepare("
    INSERT INTO group_messages (group_id, sender_id, sender_role, message, sent_at, is_read)
    VALUES (?, ?, ?, ?, NOW(), 0)
");
$stmt->bind_param("iiss", $group_id, $sender_id, $sender_role, $message);

if ($stmt->execute()) {
    http_response_code(200);
    echo "Message sent.";
} else {
    http_response_code(500);
    echo "Failed to send message.";
}
?>
