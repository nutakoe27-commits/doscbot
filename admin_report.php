<?php
/**
 * Ежедневный отчёт администратору.
 * Запускать через cron раз в день:
 * 0 9 * * * php /путь/к/проекту/admin_report.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$adminId = $config['admin_telegram_id'] ?? 0;
if (!$adminId) {
    echo "admin_telegram_id не указан в config.php\n";
    exit(1);
}

$db = new \DocBot\Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
$api = new \DocBot\TelegramApi($config['bot_token'], $config['ssl_verify'] ?? false, $config['telegram_proxy'] ?? null);
$admin = new \DocBot\Handler\AdminHandler($db, $api, $adminId);

$admin->sendDailyReport();

echo date('Y-m-d H:i:s') . " — Отчёт отправлен\n";
