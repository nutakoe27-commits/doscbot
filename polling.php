<?php
/**
 * Long polling для DocBot.
 * Используется вместо webhook, когда api.telegram.org заблокирован
 * и Telegram не может доставить webhook на сервер.
 *
 * Запуск: php /var/www/docbot/polling.php
 * Или через systemd: systemctl start docbot
 */

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$proxy = $config['telegram_proxy'] ?? null;
$token = $config['bot_token'];
$offset = 0;

echo "DocBot polling started...\n";

// Удаляем webhook
$ch = curl_init("https://api.telegram.org/bot{$token}/deleteWebhook");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
if ($proxy) {
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
}
curl_exec($ch);
curl_close($ch);

$bot = new \DocBot\BotHandler($config);

while (true) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/getUpdates?offset={$offset}&timeout=30");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    }

    $result = curl_exec($ch);
    curl_close($ch);

    if (!$result) continue;

    $data = json_decode($result, true);
    if (!isset($data['ok']) || !$data['ok']) continue;

    foreach ($data['result'] as $update) {
        $offset = $update['update_id'] + 1;
        try {
            $bot->handle($update);
        } catch (\Throwable $e) {
            error_log("[DocBot] Polling error: " . $e->getMessage());
        }
    }
}
