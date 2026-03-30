<?php

namespace DocBot;

class BotHandler
{
    private Database $db;
    private PdfGenerator $pdf;
    private string $token;
    private string $storageDir;

    public function __construct(array $config)
    {
        $this->token = $config['bot_token'];
        $this->storageDir = rtrim($config['pdf_storage'], '/');
        $this->db = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
        $this->pdf = new PdfGenerator(__DIR__ . '/../templates', $this->storageDir);
    }

    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }

        if (!isset($update['message'])) {
            return;
        }

        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $telegramId = $message['from']['id'];
        $text = trim($message['text'] ?? '');

        // Check for commands first
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, $telegramId);
            return;
        }
        if ($text === '/help') {
            $this->handleHelp($chatId);
            return;
        }
        if ($text === '/mydata') {
            $this->handleMyData($chatId, $telegramId);
            return;
        }
        if ($text === '/menu') {
            $user = $this->db->getUserByTelegramId($telegramId);
            if ($user && $user['full_name']) {
                $this->showMainMenu($chatId);
            } else {
                $this->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            }
            return;
        }

        // Handle state-based input
        $stateRow = $this->db->getState($telegramId);
        if ($stateRow && $stateRow['state']) {
            $this->handleStateInput($chatId, $telegramId, $text, $stateRow);
            return;
        }

        $this->sendMessage($chatId, 'Используйте /start для начала работы или /help для справки.');
    }

    // ── /start ──

    private function handleStart(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);

        if ($user && $user['full_name']) {
            $this->sendMessage($chatId, "Добро пожаловать, {$user['full_name']}! 👋");
            $this->showMainMenu($chatId);
            return;
        }

        if (!$user) {
            $this->db->createUser($telegramId);
        }

        $this->sendMessage($chatId,
            "Добро пожаловать в DocBot! 📄\n\n" .
            "Я помогу вам генерировать договоры и акты для самозанятых.\n\n" .
            "Для начала нужно заполнить ваши реквизиты.\n" .
            "Введите ваше ФИО полностью (например: Иванов Иван Иванович):"
        );
        $this->db->setState($telegramId, 'reg_full_name');
    }

    // ── Main menu ──

    private function showMainMenu(int $chatId): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📄 Создать договор', 'callback_data' => 'new_contract']],
                [['text' => '📋 Создать акт', 'callback_data' => 'new_act']],
                [['text' => '👥 Мои клиенты', 'callback_data' => 'my_clients']],
                [['text' => '⚙️ Мои реквизиты', 'callback_data' => 'my_data']],
                [['text' => 'ℹ️ Помощь', 'callback_data' => 'help']],
            ],
        ];
        $this->sendMessage($chatId, 'Главное меню:', $keyboard);
    }

    // ── Callback handler ──

    private function handleCallback(array $callback): void
    {
        $chatId = $callback['message']['chat']['id'];
        $telegramId = $callback['from']['id'];
        $data = $callback['data'];
        $callbackId = $callback['id'];

        $this->answerCallback($callbackId);

        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            return;
        }

        // New contract
        if ($data === 'new_contract') {
            $this->startContractFlow($chatId, $telegramId, $user);
            return;
        }

        // New act
        if ($data === 'new_act') {
            $this->startActFlow($chatId, $telegramId, $user);
            return;
        }

        // My clients
        if ($data === 'my_clients') {
            $this->showClientsList($chatId, $user);
            return;
        }

        // My data
        if ($data === 'my_data') {
            $this->handleMyData($chatId, $telegramId);
            return;
        }

        // Help
        if ($data === 'help') {
            $this->handleHelp($chatId);
            return;
        }

        // Back to menu
        if ($data === 'main_menu') {
            $this->showMainMenu($chatId);
            return;
        }

        // Select client for contract
        if (str_starts_with($data, 'select_client_')) {
            $clientId = (int) substr($data, strlen('select_client_'));
            $this->onClientSelected($chatId, $telegramId, $clientId);
            return;
        }

        // New client for contract
        if ($data === 'new_client') {
            $this->startNewClientFlow($chatId, $telegramId);
            return;
        }

        // Client type selection
        if (str_starts_with($data, 'client_type_')) {
            $type = substr($data, strlen('client_type_'));
            $this->onClientTypeSelected($chatId, $telegramId, $type);
            return;
        }

        // Select contract for act
        if (str_starts_with($data, 'select_contract_')) {
            $contractId = (int) substr($data, strlen('select_contract_'));
            $this->onContractSelectedForAct($chatId, $telegramId, $contractId);
            return;
        }

        // Edit registration fields
        if (str_starts_with($data, 'edit_')) {
            $field = substr($data, strlen('edit_'));
            $this->startEditField($chatId, $telegramId, $field);
            return;
        }
    }

    // ── Registration flow ──

    private function handleStateInput(int $chatId, int $telegramId, string $text, array $stateRow): void
    {
        $state = $stateRow['state'];
        $data = json_decode($stateRow['data'] ?? '{}', true) ?: [];

        // Registration states
        $regStates = [
            'reg_full_name'      => ['field' => 'full_name',       'next' => 'reg_inn',           'prompt' => 'Введите ваш ИНН (12 цифр):'],
            'reg_inn'            => ['field' => 'inn',             'next' => 'reg_address',       'prompt' => 'Введите адрес регистрации:'],
            'reg_address'        => ['field' => 'address',         'next' => 'reg_phone',         'prompt' => 'Введите номер телефона:'],
            'reg_phone'          => ['field' => 'phone',           'next' => 'reg_email',         'prompt' => 'Введите email:'],
            'reg_email'          => ['field' => 'email',           'next' => 'reg_bank_name',     'prompt' => 'Введите название банка:'],
            'reg_bank_name'      => ['field' => 'bank_name',       'next' => 'reg_account',       'prompt' => 'Введите номер расчётного счёта (р/с):'],
            'reg_account'        => ['field' => 'account_number',  'next' => 'reg_bik',           'prompt' => 'Введите БИК банка:'],
            'reg_bik'            => ['field' => 'bik',             'next' => 'reg_corr_account',  'prompt' => 'Введите корреспондентский счёт (к/с):'],
            'reg_corr_account'   => ['field' => 'corr_account',    'next' => null,                'prompt' => null],
        ];

        if (isset($regStates[$state])) {
            $info = $regStates[$state];
            $this->db->updateUser($telegramId, [$info['field'] => $text]);

            if ($info['next']) {
                $this->db->setState($telegramId, $info['next']);
                $this->sendMessage($chatId, $info['prompt']);
            } else {
                $this->db->clearState($telegramId);
                $this->sendMessage($chatId, "✅ Регистрация завершена! Ваши реквизиты сохранены.");
                $this->showMainMenu($chatId);
            }
            return;
        }

        // Edit field
        if ($state === 'edit_field') {
            $field = $data['field'] ?? '';
            if ($field) {
                $this->db->updateUser($telegramId, [$field => $text]);
                $this->db->clearState($telegramId);
                $this->sendMessage($chatId, "✅ Данные обновлены.");
                $this->showMainMenu($chatId);
            }
            return;
        }

        // New client flow
        if (str_starts_with($state, 'new_client_')) {
            $this->handleNewClientInput($chatId, $telegramId, $text, $state, $data);
            return;
        }

        // Contract flow
        if (str_starts_with($state, 'contract_')) {
            $this->handleContractInput($chatId, $telegramId, $text, $state, $data);
            return;
        }

        // Act flow
        if ($state === 'act_date') {
            $this->handleActDateInput($chatId, $telegramId, $text, $data);
            return;
        }
    }

    // ── Edit field ──

    private function startEditField(int $chatId, int $telegramId, string $field): void
    {
        $labels = [
            'full_name' => 'ФИО', 'inn' => 'ИНН', 'address' => 'адрес',
            'phone' => 'телефон', 'email' => 'email', 'bank_name' => 'название банка',
            'account_number' => 'р/с', 'bik' => 'БИК', 'corr_account' => 'к/с',
        ];
        $label = $labels[$field] ?? $field;
        $this->db->setState($telegramId, 'edit_field', ['field' => $field]);
        $this->sendMessage($chatId, "Введите новое значение для поля «{$label}»:");
    }

    // ── /mydata ──

    private function handleMyData(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->sendMessage($chatId, 'Вы ещё не зарегистрированы. Нажмите /start');
            return;
        }

        $msg = "⚙️ <b>Ваши реквизиты:</b>\n\n"
            . "ФИО: {$user['full_name']}\n"
            . "ИНН: {$user['inn']}\n"
            . "Адрес: {$user['address']}\n"
            . "Телефон: {$user['phone']}\n"
            . "Email: {$user['email']}\n"
            . "Банк: {$user['bank_name']}\n"
            . "Р/с: {$user['account_number']}\n"
            . "БИК: {$user['bik']}\n"
            . "К/с: {$user['corr_account']}\n\n"
            . "Нажмите кнопку, чтобы изменить:";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'ФИО', 'callback_data' => 'edit_full_name'],
                    ['text' => 'ИНН', 'callback_data' => 'edit_inn'],
                    ['text' => 'Адрес', 'callback_data' => 'edit_address'],
                ],
                [
                    ['text' => 'Телефон', 'callback_data' => 'edit_phone'],
                    ['text' => 'Email', 'callback_data' => 'edit_email'],
                    ['text' => 'Банк', 'callback_data' => 'edit_bank_name'],
                ],
                [
                    ['text' => 'Р/с', 'callback_data' => 'edit_account_number'],
                    ['text' => 'БИК', 'callback_data' => 'edit_bik'],
                    ['text' => 'К/с', 'callback_data' => 'edit_corr_account'],
                ],
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    // ── /help ──

    private function handleHelp(int $chatId): void
    {
        $msg = "ℹ️ <b>Справка по DocBot</b>\n\n"
            . "Я помогаю самозанятым генерировать документы:\n\n"
            . "📄 <b>Договор оказания услуг</b> — создайте договор с клиентом, выберите тип заказчика (физлицо, ИП, юрлицо), укажите описание услуги и сумму.\n\n"
            . "📋 <b>Акт сдачи-приёмки</b> — создайте акт к существующему договору.\n\n"
            . "<b>Команды:</b>\n"
            . "/start — начать работу / главное меню\n"
            . "/menu — главное меню\n"
            . "/mydata — посмотреть/изменить свои реквизиты\n"
            . "/help — эта справка\n\n"
            . "НДС не облагается (422-ФЗ).\n"
            . "Обмен сканами через Telegram имеет юридическую силу (п. 7.3 договора).";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    // ── Clients ──

    private function showClientsList(int $chatId, array $user): void
    {
        $clients = $this->db->getClientsByUserId($user['id']);

        if (empty($clients)) {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '➕ Добавить клиента', 'callback_data' => 'new_client']],
                    [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->sendMessage($chatId, 'У вас пока нет клиентов.', $keyboard);
            return;
        }

        $typeLabels = ['fiz' => 'Физлицо', 'ip' => 'ИП', 'org' => 'Юрлицо'];
        $msg = "👥 <b>Ваши клиенты:</b>\n\n";
        foreach ($clients as $i => $c) {
            $type = $typeLabels[$c['client_type']] ?? $c['client_type'];
            $msg .= ($i + 1) . ". {$c['full_name']} ({$type})\n";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '➕ Добавить клиента', 'callback_data' => 'new_client']],
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    // ── New client flow ──

    private function startNewClientFlow(int $chatId, int $telegramId): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Физлицо', 'callback_data' => 'client_type_fiz']],
                [['text' => 'ИП', 'callback_data' => 'client_type_ip']],
                [['text' => 'Юрлицо', 'callback_data' => 'client_type_org']],
            ],
        ];
        $this->sendMessage($chatId, 'Выберите тип клиента:', $keyboard);
    }

    private function onClientTypeSelected(int $chatId, int $telegramId, string $type): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $data['client_type'] = $type;

        $this->db->setState($telegramId, 'new_client_full_name', $data);

        $label = match ($type) {
            'fiz' => 'ФИО клиента',
            'ip'  => 'ФИО индивидуального предпринимателя',
            'org' => 'Название организации',
        };
        $this->sendMessage($chatId, "Введите {$label}:");
    }

    private function handleNewClientInput(int $chatId, int $telegramId, string $text, string $state, array $data): void
    {
        $type = $data['client_type'] ?? 'fiz';

        // State machine for client creation
        $flows = [
            'fiz' => [
                'new_client_full_name'       => ['key' => 'full_name',        'next' => 'new_client_passport_series',  'prompt' => 'Введите серию паспорта (4 цифры):'],
                'new_client_passport_series'  => ['key' => 'passport_series',  'next' => 'new_client_passport_number', 'prompt' => 'Введите номер паспорта (6 цифр):'],
                'new_client_passport_number'  => ['key' => 'passport_number',  'next' => 'new_client_passport_issued', 'prompt' => 'Введите кем выдан паспорт:'],
                'new_client_passport_issued'  => ['key' => 'passport_issued',  'next' => 'new_client_address',         'prompt' => 'Введите адрес клиента:'],
                'new_client_address'          => ['key' => 'address',          'next' => 'new_client_phone',           'prompt' => 'Введите телефон клиента:'],
                'new_client_phone'            => ['key' => 'phone',            'next' => 'new_client_email',           'prompt' => 'Введите email клиента (или «-» чтобы пропустить):'],
                'new_client_email'            => ['key' => 'email',            'next' => null,                         'prompt' => null],
            ],
            'ip' => [
                'new_client_full_name'   => ['key' => 'full_name', 'next' => 'new_client_inn',     'prompt' => 'Введите ИНН:'],
                'new_client_inn'         => ['key' => 'inn',       'next' => 'new_client_ogrnip',  'prompt' => 'Введите ОГРНИП:'],
                'new_client_ogrnip'      => ['key' => 'ogrnip',    'next' => 'new_client_address', 'prompt' => 'Введите адрес:'],
                'new_client_address'     => ['key' => 'address',   'next' => 'new_client_phone',   'prompt' => 'Введите телефон:'],
                'new_client_phone'       => ['key' => 'phone',     'next' => 'new_client_email',   'prompt' => 'Введите email (или «-» чтобы пропустить):'],
                'new_client_email'       => ['key' => 'email',     'next' => 'new_client_bank',    'prompt' => 'Введите банковские реквизиты (или «-» чтобы пропустить):'],
                'new_client_bank'        => ['key' => 'bank_details', 'next' => null,              'prompt' => null],
            ],
            'org' => [
                'new_client_full_name'    => ['key' => 'full_name',            'next' => 'new_client_inn',             'prompt' => 'Введите ИНН организации:'],
                'new_client_inn'          => ['key' => 'inn',                  'next' => 'new_client_kpp',             'prompt' => 'Введите КПП:'],
                'new_client_kpp'          => ['key' => 'kpp',                  'next' => 'new_client_ogrn',            'prompt' => 'Введите ОГРН:'],
                'new_client_ogrn'         => ['key' => 'ogrn',                 'next' => 'new_client_rep_title',       'prompt' => 'Введите должность представителя (напр. Генеральный директор):'],
                'new_client_rep_title'    => ['key' => 'representative_title', 'next' => 'new_client_rep_name',        'prompt' => 'Введите ФИО представителя:'],
                'new_client_rep_name'     => ['key' => 'representative_name',  'next' => 'new_client_basis',           'prompt' => 'Введите основание (напр. Устава):'],
                'new_client_basis'        => ['key' => 'basis',                'next' => 'new_client_address',         'prompt' => 'Введите юридический адрес:'],
                'new_client_address'      => ['key' => 'address',              'next' => 'new_client_phone',           'prompt' => 'Введите телефон:'],
                'new_client_phone'        => ['key' => 'phone',                'next' => 'new_client_email',           'prompt' => 'Введите email (или «-» чтобы пропустить):'],
                'new_client_email'        => ['key' => 'email',                'next' => 'new_client_bank',            'prompt' => 'Введите банковские реквизиты (или «-» чтобы пропустить):'],
                'new_client_bank'         => ['key' => 'bank_details',         'next' => null,                         'prompt' => null],
            ],
        ];

        $flow = $flows[$type] ?? [];
        if (!isset($flow[$state])) {
            $this->db->clearState($telegramId);
            $this->sendMessage($chatId, 'Произошла ошибка. Попробуйте снова.');
            $this->showMainMenu($chatId);
            return;
        }

        $step = $flow[$state];
        $value = ($text === '-') ? '' : $text;
        $data[$step['key']] = $value;

        if ($step['next']) {
            $this->db->setState($telegramId, $step['next'], $data);
            $this->sendMessage($chatId, $step['prompt']);
        } else {
            // Save client
            $user = $this->db->getUserByTelegramId($telegramId);
            $clientData = [
                'user_id'              => $user['id'],
                'client_type'          => $type,
                'full_name'            => $data['full_name'] ?? '',
                'inn'                  => $data['inn'] ?? null,
                'kpp'                  => $data['kpp'] ?? null,
                'ogrn'                 => $data['ogrn'] ?? null,
                'ogrnip'               => $data['ogrnip'] ?? null,
                'representative_title' => $data['representative_title'] ?? null,
                'representative_name'  => $data['representative_name'] ?? null,
                'basis'                => $data['basis'] ?? null,
                'passport_series'      => $data['passport_series'] ?? null,
                'passport_number'      => $data['passport_number'] ?? null,
                'passport_issued'      => $data['passport_issued'] ?? null,
                'address'              => $data['address'] ?? null,
                'phone'                => $data['phone'] ?? null,
                'email'                => $data['email'] ?? null,
                'bank_details'         => $data['bank_details'] ?? null,
            ];
            $clientId = $this->db->createClient($clientData);

            // If we were in contract flow, continue it
            if (!empty($data['_contract_flow'])) {
                $data['client_id'] = $clientId;
                $this->db->setState($telegramId, 'contract_service', $data);
                $this->sendMessage($chatId, "✅ Клиент сохранён.\n\nВведите описание услуги:");
            } else {
                $this->db->clearState($telegramId);
                $this->sendMessage($chatId, "✅ Клиент «{$data['full_name']}» сохранён.");
                $this->showMainMenu($chatId);
            }
        }
    }

    // ── Contract flow ──

    private function startContractFlow(int $chatId, int $telegramId, array $user): void
    {
        $clients = $this->db->getClientsByUserId($user['id']);

        $buttons = [];
        foreach ($clients as $c) {
            $buttons[] = [['text' => $c['full_name'], 'callback_data' => 'select_client_' . $c['id']]];
        }
        $buttons[] = [['text' => '➕ Новый клиент', 'callback_data' => 'new_client']];
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        // Set state so new_client callback knows we're in contract flow
        $this->db->setState($telegramId, 'contract_select_client', ['_contract_flow' => true]);

        $keyboard = ['inline_keyboard' => $buttons];
        $this->sendMessage($chatId, 'Выберите клиента для договора:', $keyboard);
    }

    private function onClientSelected(int $chatId, int $telegramId, int $clientId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $data['client_id'] = $clientId;
        $data['_contract_flow'] = true;

        $this->db->setState($telegramId, 'contract_service', $data);
        $this->sendMessage($chatId, 'Введите описание услуги:');
    }

    private function handleContractInput(int $chatId, int $telegramId, string $text, string $state, array $data): void
    {
        switch ($state) {
            case 'contract_service':
                $data['service_description'] = $text;
                $this->db->setState($telegramId, 'contract_amount', $data);
                $this->sendMessage($chatId, 'Введите сумму (число, например: 15000):');
                break;

            case 'contract_amount':
                $amount = (float) str_replace([' ', ','], ['', '.'], $text);
                if ($amount <= 0) {
                    $this->sendMessage($chatId, 'Введите корректную сумму (число больше 0):');
                    return;
                }
                $data['amount'] = $amount;
                $this->db->setState($telegramId, 'contract_city', $data);
                $this->sendMessage($chatId, 'Введите город (или отправьте «-» для Москвы):');
                break;

            case 'contract_city':
                $data['city'] = ($text === '-' || $text === '') ? 'Москва' : $text;
                $this->db->setState($telegramId, 'contract_date_start', $data);
                $this->sendMessage($chatId, 'Введите дату начала договора (ДД.ММ.ГГГГ):');
                break;

            case 'contract_date_start':
                $date = $this->parseDate($text);
                if (!$date) {
                    $this->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                    return;
                }
                $data['date_start'] = $date;
                $this->db->setState($telegramId, 'contract_date_end', $data);
                $this->sendMessage($chatId, 'Введите дату окончания договора (ДД.ММ.ГГГГ):');
                break;

            case 'contract_date_end':
                $date = $this->parseDate($text);
                if (!$date) {
                    $this->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                    return;
                }
                $data['date_end'] = $date;
                $this->generateContract($chatId, $telegramId, $data);
                break;

            default:
                $this->db->clearState($telegramId);
                $this->showMainMenu($chatId);
                break;
        }
    }

    private function generateContract(int $chatId, int $telegramId, array $data): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        $client = $this->db->getClientById($data['client_id']);

        if (!$user || !$client) {
            $this->sendMessage($chatId, 'Ошибка: данные не найдены.');
            $this->db->clearState($telegramId);
            $this->showMainMenu($chatId);
            return;
        }

        $count = $this->db->countDocumentsToday($user['id'], 'contract');
        $contractNumber = 'ДОУ-' . date('Ymd') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $amountWords = NumberToWords::convertInteger((float) $data['amount']);

        $docData = [
            'user_id'             => $user['id'],
            'client_id'           => $client['id'],
            'doc_type'            => 'contract',
            'contract_number'     => $contractNumber,
            'service_description' => $data['service_description'],
            'amount'              => $data['amount'],
            'amount_words'        => $amountWords,
            'date_start'          => $data['date_start'],
            'date_end'            => $data['date_end'],
            'city'                => $data['city'] ?? 'Москва',
            'payment_terms'       => 'по безналичному расчёту',
            'payment_days'        => 5,
            'payment_moment'      => 'с момента подписания Акта сдачи-приёмки',
            'acceptance_days'     => 5,
            'termination_days'    => 14,
        ];

        $this->sendMessage($chatId, '⏳ Генерирую договор...');

        try {
            $pdfPath = $this->pdf->generateContract($user, $client, $docData);
            $docData['pdf_path'] = $pdfPath;
            $this->db->createDocument($docData);
            $this->db->clearState($telegramId);

            $this->sendDocument($chatId, $pdfPath, "Договор {$contractNumber}");
            $this->sendMessage($chatId, "✅ Договор {$contractNumber} создан и отправлен.");
            $this->showMainMenu($chatId);
        } catch (\Throwable $e) {
            $this->sendMessage($chatId, '❌ Ошибка при генерации PDF: ' . $e->getMessage());
            $this->db->clearState($telegramId);
            $this->showMainMenu($chatId);
        }
    }

    // ── Act flow ──

    private function startActFlow(int $chatId, int $telegramId, array $user): void
    {
        $contracts = $this->db->getDocumentsByUserId($user['id'], 'contract');

        if (empty($contracts)) {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->sendMessage($chatId, 'У вас пока нет договоров. Сначала создайте договор.', $keyboard);
            return;
        }

        $buttons = [];
        foreach ($contracts as $c) {
            $label = "{$c['contract_number']} — {$c['client_name']}";
            $buttons[] = [['text' => $label, 'callback_data' => 'select_contract_' . $c['id']]];
        }
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->sendMessage($chatId, 'Выберите договор для создания акта:', $keyboard);
    }

    private function onContractSelectedForAct(int $chatId, int $telegramId, int $contractId): void
    {
        $this->db->setState($telegramId, 'act_date', ['contract_id' => $contractId]);
        $today = date('d.m.Y');
        $this->sendMessage($chatId, "Введите дату акта (ДД.ММ.ГГГГ) или отправьте «-» для сегодняшней ({$today}):");
    }

    private function handleActDateInput(int $chatId, int $telegramId, string $text, array $data): void
    {
        if ($text === '-' || $text === '') {
            $actDate = date('Y-m-d');
        } else {
            $actDate = $this->parseDate($text);
            if (!$actDate) {
                $this->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                return;
            }
        }

        $this->generateAct($chatId, $telegramId, $data['contract_id'], $actDate);
    }

    private function generateAct(int $chatId, int $telegramId, int $contractId, string $actDate): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        $contract = $this->db->getDocumentById($contractId);

        if (!$user || !$contract) {
            $this->sendMessage($chatId, 'Ошибка: данные не найдены.');
            $this->db->clearState($telegramId);
            $this->showMainMenu($chatId);
            return;
        }

        $client = $this->db->getClientById($contract['client_id']);
        if (!$client) {
            $this->sendMessage($chatId, 'Ошибка: клиент не найден.');
            $this->db->clearState($telegramId);
            $this->showMainMenu($chatId);
            return;
        }

        $count = $this->db->countDocumentsToday($user['id'], 'act');
        $actNumber = 'АКТ-' . date('Ymd') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        $actData = [
            'act_number' => $actNumber,
            'act_date'   => $actDate,
        ];

        $this->sendMessage($chatId, '⏳ Генерирую акт...');

        try {
            $pdfPath = $this->pdf->generateAct($user, $client, $contract, $actData);

            $docData = [
                'user_id'             => $user['id'],
                'client_id'           => $client['id'],
                'doc_type'            => 'act',
                'contract_number'     => $actNumber,
                'service_description' => $contract['service_description'],
                'amount'              => $contract['amount'],
                'amount_words'        => $contract['amount_words'],
                'date_start'          => $contract['date_start'],
                'date_end'            => $contract['date_end'],
                'city'                => $contract['city'],
                'pdf_path'            => $pdfPath,
                'parent_contract_id'  => $contractId,
            ];
            $this->db->createDocument($docData);
            $this->db->clearState($telegramId);

            $this->sendDocument($chatId, $pdfPath, "Акт {$actNumber}");
            $this->sendMessage($chatId, "✅ Акт {$actNumber} к договору {$contract['contract_number']} создан и отправлен.");
            $this->showMainMenu($chatId);
        } catch (\Throwable $e) {
            $this->sendMessage($chatId, '❌ Ошибка при генерации PDF: ' . $e->getMessage());
            $this->db->clearState($telegramId);
            $this->showMainMenu($chatId);
        }
    }

    // ── Telegram API ──

    private function sendMessage(int $chatId, string $text, ?array $keyboard = null, string $parseMode = ''): void
    {
        $params = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];
        if ($parseMode) {
            $params['parse_mode'] = $parseMode;
        }
        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }
        $this->apiRequest('sendMessage', $params);
    }

    private function sendDocument(int $chatId, string $filePath, string $caption = ''): void
    {
        $url = "https://api.telegram.org/bot{$this->token}/sendDocument";

        $post = [
            'chat_id'  => $chatId,
            'document' => new \CURLFile($filePath, 'application/pdf', basename($filePath)),
        ];
        if ($caption) {
            $post['caption'] = $caption;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function answerCallback(string $callbackId): void
    {
        $this->apiRequest('answerCallbackQuery', ['callback_query_id' => $callbackId]);
    }

    private function apiRequest(string $method, array $params): ?array
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result ? json_decode($result, true) : null;
    }

    // ── Helpers ──

    private function parseDate(string $text): ?string
    {
        // Accept DD.MM.YYYY format
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            $d = (int) $m[1];
            $mon = (int) $m[2];
            $y = (int) $m[3];
            if (checkdate($mon, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mon, $d);
            }
        }
        // Accept YYYY-MM-DD format
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $text, $m)) {
            $y = (int) $m[1];
            $mon = (int) $m[2];
            $d = (int) $m[3];
            if (checkdate($mon, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mon, $d);
            }
        }
        return null;
    }
}
