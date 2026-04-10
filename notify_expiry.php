<?php
/**
 * Уведомления об истекающих договорах.
 * Запускать через cron, например ежедневно:
 * 0 9 * * * php /путь/к/проекту/notify_expiry.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$db = new \DocBot\Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
$api = new \DocBot\TelegramApi($config['bot_token'], $config['ssl_verify'] ?? false, $config['telegram_proxy'] ?? null);

$contracts = $db->getExpiringContracts(3);
$sent = 0;

foreach ($contracts as $contract) {
    $dateEnd = date('d.m.Y', strtotime($contract['date_end']));
    $msg = "⏰ Договор {$contract['contract_number']} с {$contract['client_name']} заканчивается {$dateEnd}.";
    $api->sendMessage($contract['telegram_id'], $msg);
    $sent++;
}

echo date('Y-m-d H:i:s') . " — Отправлено уведомлений: {$sent}\n";
