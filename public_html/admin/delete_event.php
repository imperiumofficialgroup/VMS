<?php
include '../auth/db.php';
session_start();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid event ID.");
}

$event_id = $_GET['id'];

// Fetch event to get image path
$stmt = $conn->prepare("SELECT image_path FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Event not found.");
}

$event = $result->fetch_assoc();
$image_path = '../uploads/' . $event['image_path'];

// Delete image file if it exists
if (file_exists($image_path)) {
    unlink($image_path);
}

// --- Delete related volunteer_points first to respect foreign key constraint ---
$delete_points = $conn->prepare("DELETE FROM volunteer_points WHERE event_id = ?");
$delete_points->bind_param("i", $event_id);
$delete_points->execute();
$delete_points->close();

// --- Now delete the event itself ---
$stmt = $conn->prepare("DELETE FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);

if ($stmt->execute()) {
    header("Location: view_events.php?deleted=1");
    exit;
} else {
    echo "Error deleting event.";
}

$stmt->close();
$conn->close();
?>
