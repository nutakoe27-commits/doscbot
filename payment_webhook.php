<?php

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

// Read raw POST body
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || !isset($data['event'])) {
    http_response_code(400);
    exit;
}

// Log webhook
file_put_contents(__DIR__ . '/storage/webhook.log',
    date('Y-m-d H:i:s') . ' ' . $body . "\n", FILE_APPEND);

$db = new \DocBot\Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
$api = new \DocBot\TelegramApi($config['bot_token'], $config['ssl_verify'] ?? false, $config['telegram_proxy'] ?? null);
$handler = new \DocBot\Handler\PaymentHandler($db, $api, $config);

try {
    $handler->handleWebhook($data);
} catch (\Throwable $e) {
    error_log('[DocBot] Webhook error: ' . $e->getMessage());
}

// Always return 200 to YooKassa
http_response_code(200);
echo 'OK';
