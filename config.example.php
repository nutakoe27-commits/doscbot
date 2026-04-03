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

    // Telegram ID администратора (для /admin и ежедневных отчётов)
    // Узнать свой ID: напиши @userinfobot в Telegram
    'admin_telegram_id' => 0,

    // ЮKassa
    'yookassa_shop_id'  => '', // ID магазина из личного кабинета ЮKassa
    'yookassa_secret'   => '', // Секретный ключ API
    'yookassa_return_url' => 'https://www.krichigindocs.ru/payment_success.html', // Страница после оплаты
];
