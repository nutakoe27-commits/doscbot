<?php

return [
    'bot_token'   => 'YOUR_BOT_TOKEN',
    'webhook_url' => 'https://your-domain.ru/index.php',
    'db_host'     => 'localhost',
    'db_name'     => 'docbot_db',
    'db_user'     => 'docbot_user',
    'db_password' => '',
    'pdf_storage' => __DIR__ . '/storage/pdf/',

    // Проверка SSL-сертификата при запросах к Telegram API
    // false — для shared-хостинга (SpaceWeb)
    // true  — для VDS и выделенных серверов (рекомендуется)
    'ssl_verify'  => false,
];
