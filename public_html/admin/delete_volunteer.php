<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['volunteer_id'])) {
    $volunteer_id = intval($_POST['volunteer_id']);

    // Delete the volunteer
    $stmt = $conn->prepare("DELETE FROM volunteers WHERE id = ?");
    $stmt->bind_param("i", $volunteer_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Volunteer deleted successfully.";
    } else {
        $_SESSION['message'] = "Error deleting volunteer.";
    }

    $stmt->close();
}

header("Location: view_volunteers.php");
exit();
?>
