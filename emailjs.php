<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// --- EmailJS Configuration ---
$service_id = 'YOUR_EMAILJS_SERVICE_ID';
$template_id = 'YOUR_EMAILJS_TEMPLATE_ID';
$public_key = 'YOUR_EMAILJS_PUBLIC_KEY'; // Also known as User ID

// Map your frontend variables to the template variables you created in EmailJS
$template_params = [
    'to_email' => $recipient_email,
    'subject' => $subject,
    'message' => $message,
    // Add any other template variables your EmailJS template expects here
];

$payload = [
    'service_id' => $service_id,
    'template_id' => $template_id,
    'user_id' => $public_key,
    'template_params' => $template_params,
];

// --- Send via EmailJS REST API ---
$ch = curl_init('https://api.emailjs.com/api/v1.0/email/send');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
]);

$api_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// EmailJS returns a 200 status code text "OK" on success
if ($http_code === 200) {
    $status = 'success';
    $response = ['success' => true, 'message' => 'Email sent successfully via EmailJS!'];
} else {
    $status = 'failed';
    $response = ['success' => false, 'message' => "Message could not be sent. EmailJS Error: Code $http_code - $api_response"];
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
    'time' => date('Y-m-d H:i:s'),
];

// Write back to the file safely using an exclusive lock
file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT), LOCK_EX);

// Output the final JSON response to the client
echo json_encode($response);
