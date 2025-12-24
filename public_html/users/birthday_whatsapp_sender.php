<?php
require_once 'vendor/autoload.php';
include '../auth/db.php';

use Twilio\Rest\Client;

// Twilio credentials
$account_sid = 'AC3c307df9d952cbb6b438beeb4fd46cd7';
$auth_token = '0b67aab314b061bc57f9d848940906fb';
$twilio_whatsapp = 'whatsapp:+917887741483'; // Twilio sandbox or your business number

$client = new Client($account_sid, $auth_token);

// Fetch today’s birthdays
$today = date('m-d');
$result = $conn->query("
    SELECT name, whatsapp_number 
    FROM volunteers 
    WHERE DATE_FORMAT(birth_date, '%m-%d') = '$today'
");

while ($row = $result->fetch_assoc()) {
    $name = $row['name'];
    $to = 'whatsapp:+' . preg_replace('/[^0-9]/', '', $row['whatsapp_number']);

    $message = "🎉 Happy Birthday, $name! 🎂 
Wishing you a wonderful year ahead from all of us at Imperium!";

    try {
        $client->messages->create(
            $to,
            [
                'from' => $twilio_whatsapp,
                'body' => $message
            ]
        );
        echo "Message sent to $name<br>";
    } catch (Exception $e) {
        echo "Failed to send to $name: " . $e->getMessage() . "<br>";
    }
}
?>
