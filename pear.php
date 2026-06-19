<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

// 2. Require BOTH the Exception class and the PHPMailer class
// (PHPMailer needs the Exception class to handle its custom errors)
require_once 'mailer/Exception.php';
require_once 'mailer/PHPMailer.php';

// Set header to return JSON data back to your JavaScript frontend
header('Content-Type: application/json');

// Get JSON input from the frontend form
$json = file_get_contents('php://input');
$input = json_decode($json, true);

// Fallback check to ensure data exists
if (!isset($input['email']) || !isset($input['subject']) || !isset($input['message'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$recipient_email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$subject = htmlspecialchars($input['subject']);
$message = $input['message'];

$mail = new PHPMailer(true); // Now PHP knows exactly where to find this class

try {
    // Tell PHPMailer to use PHP's native mail() function instead of SMTP
    $mail->isMail();

    // Recipients
    $mail->setFrom('info@wb.cloversbk.xyz', 'Clover');
    $mail->addReplyTo('info@wb.cloversbk.xyz', 'Clover');
    $mail->addAddress($recipient_email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->msgHTML($message);

    // Send the email
    $mail->send();
    $status = 'success';
    $response = ['success' => true, 'message' => 'Email sent successfully!'];
} catch (Exception $e) {
    $status = 'failed';
    $response = ['success' => false, 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
}

// --- Log to history.json ---
$file = 'history.json';

// Initialize an empty array if the file doesn't exist yet
$history = [];
if (file_exists($file)) {
    $current_data = file_get_contents($file);
    $history = json_decode($current_data, true) ?: [];
}

// Add the new record
$history[] = [
    'email' => $recipient_email,
    'status' => $status,
    'time' => date('Y-m-d H:i:s'),
];

// Write back to the file safely using an exclusive lock
file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);

// Output the final JSON response to the client
echo json_encode($response);
