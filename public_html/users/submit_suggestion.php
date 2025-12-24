<?php
session_start();
require_once '../auth/db.php';

if (!isset($_SESSION['volunteer_id'])) {
    header("Location: ../auth/volunteer_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volunteer_id = $_SESSION['volunteer_id'];
    $subject = $_POST['subject'];
    $message = trim($_POST['message']);
    $preferred_contact = $_POST['preferred_contact'];

    // Basic validation
    if (empty($subject) || empty($message) || empty($preferred_contact)) {
        echo "All fields are required.";
        exit();
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO suggestions (volunteer_id, subject, message, preferred_contact) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $volunteer_id, $subject, $message, $preferred_contact);

    if ($stmt->execute()) {
        $_SESSION['suggestion_success'] = true;
        header("Location: add_suggestion.php");
        exit();
    } else {
        echo "Failed to submit your suggestion. Please try again later.";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request.";
}
