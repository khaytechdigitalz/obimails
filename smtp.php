<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Import PHPMailer classes into the global namespace
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
$message = $input['message']; // PHPMailer sanitizes HTML within MsgHTML()

$mail = new PHPMailer(true); // 'true' enables exceptions

try {
    // SMTP Configuration
    $mail->isSMTP();
    $mail->SMTPDebug = 0; // Set to 2 if you need to debug connection issues
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Safe replacement for 'ssl' (port 465)
    $mail->Host = 'wb.cloversbk.xyz';
    $mail->Port = 465;
    $mail->Username = 'info@wb.cloversbk.xyz';
    $mail->Password = 'Education12!21';

    // Recipients
    $mail->setFrom('info@wb.cloversbk.xyz', 'Clover');
    $mail->addReplyTo('info@wb.cloversbk.xyz', 'Clover');
    $mail->addAddress($recipient_email); // Dynamically sends to the form input email now
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
    // Decode existing JSON, fallback to empty array if empty or corrupted
    $history = json_decode($current_data, true) ?: [];
}

// Add the new record
$history[] = [
    'email' => $recipient_email,
    'status' => $status,
    'time' => date('Y-m-d H:i:s'), // Optional: handy for tracking when it happened
];

// Write back to the file safely using an exclusive lock
file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);

// Output the final JSON response to the client
echo json_encode($response);
