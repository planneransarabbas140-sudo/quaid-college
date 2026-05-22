<?php
/**
 * Chatbot API Handler
 * Connects the frontend widget to Anthropic Claude API
 */

require_once '../../config/anthropic.php';
header('Content-Type: application/json');

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';

if (empty($userMessage)) {
    echo json_encode(['error' => 'Empty message.']);
    exit;
}

// Prepare payload for Anthropic
$payload = json_encode([
    'model' => ANTHROPIC_MODEL,
    'max_tokens' => 500,
    'system' => 'You are a helpful student helpdesk assistant for Quaid-e-Azam Group of Colleges in Pakistan. ' .
                'Answer student and parent questions clearly and politely in the language they write in (Urdu or English). ' .
                'You help with: admissions process, fee structure, timetable, exam schedules, complaint submission, ' .
                'library, transport routes, and general college information. ' .
                'If you do not know a specific answer, say: "Please contact the front desk at +923338879961 for this query." ' .
                'Keep answers short, friendly, and helpful. Never make up specific fee amounts or dates.',
    'messages' => [
        ['role' => 'user', 'content' => $userMessage]
    ]
]);

// Initialize cURL
$ch = curl_init(ANTHROPIC_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . ANTHROPIC_API_KEY,
    'anthropic-version: 2023-06-01'
]);

// Handle potential local SSL issues on XAMPP
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    $reply = $data['content'][0]['text'] ?? 'No response received from AI.';
    echo json_encode(['reply' => $reply]);
} else {
    // Log error for debugging (optional)
    // error_log("Chatbot API Error ($httpCode): $response $curlError");
    echo json_encode(['error' => 'Something went wrong. Please try again. (API error: ' . $httpCode . ')']);
}
