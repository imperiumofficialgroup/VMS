<?php
session_start();
require_once '../auth/db.php';

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    $sender_id = 1;
    $sender_role = 'admin';
} 
// Check if volunteer is logged in
elseif (isset($_SESSION['volunteer_id']) && $_SESSION['volunteer_id']) {
    $sender_id = $_SESSION['volunteer_id'];
    $sender_role = 'volunteer';
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Get POST data
$receiver_id = $_POST['receiver_id'] ?? null;
$receiver_role = $_POST['receiver_role'] ?? null;
$message = trim($_POST['message'] ?? '');

// Validate required data
if (!$receiver_id || !$message || !$receiver_role) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required data']);
    exit();
}

// Validate receiver role
if ($receiver_role !== 'admin' && $receiver_role !== 'volunteer') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid receiver role']);
    exit();
}

// Validate message length
if (strlen($message) > 1000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message too long']);
    exit();
}

try {
    // Insert message into database
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message, sent_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("isiss", $sender_id, $sender_role, $receiver_id, $receiver_role, $message);
    
    if ($stmt->execute()) {
        // Get the inserted message ID
        $message_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'message_id' => $message_id,
            'sent_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    exit();
}

$conn->close();
?>