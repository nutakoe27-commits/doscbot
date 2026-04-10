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

    // SOCKS5-прокси для запросов к Telegram API (если api.telegram.org заблокирован)
    // null — прямое подключение, 'socks5://127.0.0.1:10808' — через локальный прокси
    'telegram_proxy' => null,

    // ЮKassa
    'yookassa_shop_id'  => '', // ID магазина из личного кабинета ЮKassa
    'yookassa_secret'   => '', // Секретный ключ API
    'yookassa_return_url' => 'https://www.krichigindocs.ru/payment_success.html', // Страница после оплаты
];
