<?php
require_once __DIR__ . '/../config/db.php';

function ensureWhatsAppLogsTable(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(30) NOT NULL,
            message_type VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL,
            response TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_whatsapp_logs_phone (phone),
            INDEX idx_whatsapp_logs_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function normalizePakistanWhatsAppPhone($phone) {
    $phone = preg_replace('/\D+/', '', (string)$phone);
    if (strpos($phone, '0092') === 0) {
        $phone = substr($phone, 2);
    }
    if (strpos($phone, '92') === 0) {
        return $phone;
    }
    if (strpos($phone, '0') === 0 && strlen($phone) === 11) {
        return '92' . substr($phone, 1);
    }
    if (strlen($phone) === 10 && strpos($phone, '3') === 0) {
        return '92' . $phone;
    }
    return $phone;
}

function getWhatsAppConfig() {
    $config = require __DIR__ . '/../config/whatsapp.php';
    $localPath = __DIR__ . '/../config/whatsapp.local.php';
    if (is_file($localPath)) {
        $localConfig = require $localPath;
        if (is_array($localConfig)) {
            $config = array_merge($config, $localConfig);
        }
    }
    return $config;
}

function logWhatsAppMessage($phone, $messageType, $status, $response) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        ensureWhatsAppLogsTable($db);
        $stmt = $db->prepare("INSERT INTO whatsapp_logs (phone, message_type, status, response) VALUES (?, ?, ?, ?)");
        $stmt->execute([$phone, $messageType, $status, $response]);
    } catch (Exception $e) {
        error_log('WhatsApp log failed: ' . $e->getMessage());
    }
}

function sendWhatsAppMessage($phone, $studentName, $email, $password) {
    $config = getWhatsAppConfig();
    $phone = normalizePakistanWhatsAppPhone($phone);
    $messageType = 'admission_approval';

    if (empty($config['phone_number_id']) || empty($config['access_token']) || empty($config['template_name'])) {
        logWhatsAppMessage($phone, $messageType, 'failed', 'WhatsApp API settings are incomplete.');
        return false;
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'template',
        'template' => [
            'name' => $config['template_name'],
            'language' => ['code' => $config['language_code'] ?? 'en_US'],
            'components' => [[
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => (string)$studentName],
                    ['type' => 'text', 'text' => (string)$email],
                    ['type' => 'text', 'text' => (string)$password],
                ],
            ]],
        ],
    ];

    $graphVersion = $config['graph_version'] ?? 'v25.0';
    $url = "https://graph.facebook.com/{$graphVersion}/" . rawurlencode($config['phone_number_id']) . "/messages";

    if (!function_exists('curl_init')) {
        logWhatsAppMessage($phone, $messageType, 'failed', 'PHP cURL extension is not enabled.');
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $config['access_token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        $details = $response !== false ? $response : $error;
        logWhatsAppMessage($phone, $messageType, 'failed', "HTTP {$httpCode}: {$details}");
        return false;
    }

    logWhatsAppMessage($phone, $messageType, 'sent', $response);
    return true;
}
