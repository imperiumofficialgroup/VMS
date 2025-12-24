<?php
$host = 'localhost';
$dbname = 'u757237013_vms';
$user = 'u757237013_vms';
$pass = ';Wy8d#al3'; // change to your DB password

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
