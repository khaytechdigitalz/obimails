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

// --- Send via MailerSend REST API ---
$api_token = 'mlsn.bbb97ae5b09376bd03743c16d3d49e3368e340eb46b99699812a23bacf543200'; // Replace with your actual MailerSend token
$url = 'https://api.mailersend.com/v1/email';

// Dynamically use form inputs for the payload
$data = [
    'from' => [
        'email' => 'info@domain.com', // Must be a verified domain in MailerSend
        'name' => 'Your Website',
    ],
    'to' => [
        [
            'email' => $recipient_email,
        ],
    ],
    'subject' => $subject,
    'text' => strip_tags($message), // Plain text fallback
    'html' => $message,
];

// Initialize cURL session
$ch = curl_init($url);

// Set cURL options
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Set the required headers
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest',
    'Authorization: Bearer '.$api_token,
]);

// Execute the request and handle the response
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_errno($ch) ? curl_error($ch) : null;

curl_close($ch);

// MailerSend returns a 202 Accepted status code on success
if (!$curl_error && ($http_code === 202 || $http_code === 200)) {
    $status = 'success';
    $final_response = ['success' => true, 'message' => 'Email sent successfully via MailerSend!'];
} else {
    $status = 'failed';
    $error_msg = $curl_error ? $curl_error : "Code $http_code - $response";
    $final_response = ['success' => false, 'message' => "Message could not be sent. MailerSend Error: $error_msg"];
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
echo json_encode($final_response);
