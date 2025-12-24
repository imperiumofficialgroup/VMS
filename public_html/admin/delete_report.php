<?php
session_start();
require_once '../auth/db.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Validate report ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid report ID.");
}

$report_id = (int)$_GET['id'];

// Fetch report and image paths
$stmt = $conn->prepare("SELECT image_paths FROM event_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Report not found.");
}

$report = $result->fetch_assoc();
$image_paths = json_decode($report['image_paths'], true);

// Delete images from server
if (!empty($image_paths) && is_array($image_paths)) {
    foreach ($image_paths as $img) {
        $imgPath = realpath('../uploads/reports/' . $img);
        if ($imgPath && file_exists($imgPath)) {
            unlink($imgPath);
        }
    }
}

// Delete report from database
$deleteStmt = $conn->prepare("DELETE FROM event_reports WHERE id = ?");
$deleteStmt->bind_param("i", $report_id);

if ($deleteStmt->execute()) {
    header("Location: report_list.php?deleted=success");
    exit();
} else {
    die("Failed to delete report.");
}
?>
