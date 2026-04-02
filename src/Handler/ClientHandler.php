<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;

class ClientHandler
{
    private Database $db;
    private TelegramApi $api;

    public function __construct(Database $db, TelegramApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    public function showClientsList(int $chatId, array $user): void
    {
        $clients = $this->db->getClientsByUserId($user['id']);

        if (empty($clients)) {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '➕ Добавить клиента', 'callback_data' => 'new_client']],
                    [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->api->sendMessage($chatId, 'У вас пока нет клиентов.', $keyboard);
            return;
        }

        $typeLabels = ['fiz' => 'Физлицо', 'ip' => 'ИП', 'org' => 'Юрлицо'];
        $msg = "👥 <b>Ваши клиенты:</b>\n\n";
        foreach ($clients as $i => $c) {
            $type = $typeLabels[$c['client_type']] ?? $c['client_type'];
            $msg .= ($i + 1) . ". {$c['full_name']} ({$type})\n";
        }

        $buttons = [];
        foreach ($clients as $c) {
            $buttons[] = [['text' => '🗑 ' . ContractHandler::truncateButton($c['full_name'], 27), 'callback_data' => 'delete_client_' . $c['id']]];
        }
        $buttons[] = [['text' => '➕ Добавить клиента', 'callback_data' => 'new_client']];
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    public function handleDeleteClient(int $chatId, int $clientId): void
    {
        $docCount = $this->db->countDocumentsByClientId($clientId);
        if ($docCount > 0) {
            $this->api->sendMessage($chatId, 'Нельзя удалить клиента с существующими документами.');
            return;
        }

        $this->db->deleteClient($clientId);
        $menuKb = ['inline_keyboard' => [[['text' => '📋 Главное меню', 'callback_data' => 'main_menu']]]];
        $this->api->sendMessage($chatId, '✅ Клиент удалён.', $menuKb);
    }

    public function startNewClientFlow(int $chatId, int $telegramId): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Физлицо', 'callback_data' => 'client_type_fiz']],
                [['text' => 'ИП', 'callback_data' => 'client_type_ip']],
                [['text' => 'Юрлицо', 'callback_data' => 'client_type_org']],
            ],
        ];
        $this->api->sendMessage($chatId, 'Выберите тип клиента:', $keyboard);
    }

    public function onClientTypeSelected(int $chatId, int $telegramId, string $type): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $data['client_type'] = $type;

        $this->db->setState($telegramId, 'new_client_full_name', $data);

        $label = match ($type) {
            'fiz' => 'ФИО клиента',
            'ip'  => 'ФИО индивидуального предпринимателя',
            'org' => 'Название организации',
            default => 'ФИО клиента',
        };
        $this->api->sendMessage($chatId, "Введите {$label}:");
    }

    public function handleStateInput(int $chatId, int $telegramId, string $text, string $state, array $data): bool
    {
        if (!str_starts_with($state, 'new_client_')) {
            return false;
        }

        $type = $data['client_type'] ?? 'fiz';

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
            $this->api->sendMessage($chatId, 'Произошла ошибка. Попробуйте снова.');
            return true;
        }

        $step = $flow[$state];

        if ($step['key'] === 'inn' && !$this->validateInn($text)) {
            $this->api->sendMessage($chatId, '⚠️ ИНН должен содержать 10 или 12 цифр. Попробуйте ещё раз:');
            return true;
        }

        $value = ($text === '-') ? '' : $text;
        $data[$step['key']] = $value;

        if ($step['next']) {
            $this->db->setState($telegramId, $step['next'], $data);
            $this->api->sendMessage($chatId, $step['prompt']);
        } else {
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

            if (!empty($data['_contract_flow'])) {
                $data['client_id'] = $clientId;
                $this->db->setState($telegramId, 'contract_service', $data);
                $this->api->sendMessage($chatId, "✅ Клиент сохранён.\n\nВведите описание услуги:");
            } else {
                $this->db->clearState($telegramId);
                $menuKb = ['inline_keyboard' => [[['text' => '📋 Главное меню', 'callback_data' => 'main_menu']]]];
                $this->api->sendMessage($chatId, "✅ Клиент «{$data['full_name']}» сохранён.", $menuKb);
            }
        }
        return true;
    }

    private function validateInn(string $inn): bool
    {
        return (bool) preg_match('/^\d{10}(\d{2})?$/', $inn);
    }
}
