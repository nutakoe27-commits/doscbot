<?php

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(200);
    echo 'OK';
    exit;
}

try {
    $bot = new \DocBot\BotHandler($config);
    $bot->handle($update);
} catch (\Throwable $e) {
    error_log('DocBot error: ' . $e->getMessage());
}

http_response_code(200);
echo 'OK';
