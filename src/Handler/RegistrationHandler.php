<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;

class RegistrationHandler
{
    private Database $db;
    private TelegramApi $api;

    public function __construct(Database $db, TelegramApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    public function handleStateInput(int $chatId, int $telegramId, string $text, string $state, array $data): bool
    {
        $regStates = [
            'reg_full_name'    => ['field' => 'full_name',      'next' => 'reg_inn',          'prompt' => 'Введите ваш ИНН (12 цифр):'],
            'reg_inn'          => ['field' => 'inn',            'next' => 'reg_address',      'prompt' => 'Введите адрес регистрации:'],
            'reg_address'      => ['field' => 'address',        'next' => 'reg_phone',        'prompt' => 'Введите номер телефона:'],
            'reg_phone'        => ['field' => 'phone',          'next' => 'reg_email',        'prompt' => 'Введите email:'],
            'reg_email'        => ['field' => 'email',          'next' => 'reg_bank_name',    'prompt' => 'Введите название банка:'],
            'reg_bank_name'    => ['field' => 'bank_name',      'next' => 'reg_account',      'prompt' => 'Введите номер расчётного счёта (р/с):'],
            'reg_account'      => ['field' => 'account_number', 'next' => 'reg_bik',          'prompt' => 'Введите БИК банка:'],
            'reg_bik'          => ['field' => 'bik',            'next' => 'reg_corr_account', 'prompt' => 'Введите корреспондентский счёт (к/с):'],
            'reg_corr_account' => ['field' => 'corr_account',   'next' => null,               'prompt' => null],
        ];

        if (!isset($regStates[$state])) {
            return false;
        }

        $info = $regStates[$state];

        if ($info['field'] === 'inn' && !$this->validateInn($text)) {
            $this->api->sendMessage($chatId, '⚠️ ИНН должен содержать 10 или 12 цифр. Попробуйте ещё раз:');
            return true;
        }

        $this->db->updateUser($telegramId, [$info['field'] => $text]);

        if ($info['next']) {
            $this->db->setState($telegramId, $info['next']);
            $this->api->sendMessage($chatId, $info['prompt']);
        } else {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, "✅ Регистрация завершена! Ваши реквизиты сохранены.");
        }
        return true;
    }

    public function handleEditField(int $chatId, int $telegramId, string $text, array $data): bool
    {
        $field = $data['field'] ?? '';
        if (!$field) {
            return false;
        }
        $this->db->updateUser($telegramId, [$field => $text]);
        $this->db->clearState($telegramId);
        $this->api->sendMessage($chatId, "✅ Данные обновлены.");
        return true;
    }

    public function startEditField(int $chatId, int $telegramId, string $field): void
    {
        $labels = [
            'full_name' => 'ФИО', 'inn' => 'ИНН', 'address' => 'адрес',
            'phone' => 'телефон', 'email' => 'email', 'bank_name' => 'название банка',
            'account_number' => 'р/с', 'bik' => 'БИК', 'corr_account' => 'к/с',
        ];
        $label = $labels[$field] ?? $field;
        $this->db->setState($telegramId, 'edit_field', ['field' => $field]);
        $this->api->sendMessage($chatId, "Введите новое значение для поля «{$label}»:");
    }

    public function handleMyData(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'Вы ещё не зарегистрированы. Нажмите /start');
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
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    private function validateInn(string $inn): bool
    {
        return (bool) preg_match('/^\d{10}(\d{2})?$/', $inn);
    }
}
