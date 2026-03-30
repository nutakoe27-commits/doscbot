<?php
/**
 * Удаление старых PDF-файлов.
 * Запускать через cron, например раз в сутки:
 * 0 3 * * * php /путь/к/проекту/cleanup.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$bot = new \DocBot\BotHandler($config);
$deleted = $bot->cleanupOldPdfs(7); // удалять файлы старше 30 дней

echo date('Y-m-d H:i:s') . " — Удалено PDF: {$deleted}\n";
