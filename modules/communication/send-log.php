<?php
require_once __DIR__ . '/../../config/db.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required.']);
    exit;
}
$db = (new Database())->getConnection();

$payload = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$recipients = $payload['recipients'] ?? [];
$message = $payload['message'] ?? '';
$recipient_type = $payload['recipient_type'] ?? '';
$sent_by = getUserId();

if (!$recipients || !$message) {
    echo json_encode(['success' => false, 'message' => 'Missing recipients or message.']); exit;
}

$inserted = 0;
try {
    if (!tableExists($db, 'whatsapp_logs')) {
        echo json_encode(['success' => false, 'message' => 'WhatsApp logs table is not configured.']);
        exit;
    }
    $db->beginTransaction();
    $whStmt = $db->prepare("INSERT INTO whatsapp_logs (recipient_type, recipient_name, phone, message, sent_by, status) VALUES (?, ?, ?, ?, ?, ?)");
    if (tableExists($db, 'communication_logs')) {
        $commStmt = $db->prepare("INSERT INTO communication_logs (recipient_type, recipient_name, phone, message, sent_by) VALUES (?, ?, ?, ?, ?)");
    } else {
        $commStmt = null;
    }

    foreach ($recipients as $r) {
        $name = $r['name'] ?? trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
        $phone = $r['guardian_phone'] ?? $r['phone'] ?? '';
        // sanitize and normalize phone
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($phone, '+') === 0) { $phone = substr($phone,1); }
        if (strpos($phone, '0') === 0) { $phone = '92' . substr($phone,1); } // default country code 92
        if (strlen($phone) === 10) { $phone = '92' . $phone; }
        $status = 'Sent';
        $whStmt->execute([$recipient_type, $name, $phone, $message, $sent_by, $status]);
        if ($commStmt) {
            $commStmt->execute([$recipient_type, $name, $phone, $message, $sent_by]);
        }
        $inserted++;
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    error_log('WhatsApp Center Send Log Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to save WhatsApp send log.']); exit;
}

echo json_encode(['success' => true, 'inserted' => $inserted]);
