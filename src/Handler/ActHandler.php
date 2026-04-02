<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;
use DocBot\PdfGenerator;
use DocBot\NumberToWords;

class ActHandler
{
    private Database $db;
    private TelegramApi $api;
    private PdfGenerator $pdf;

    public function __construct(Database $db, TelegramApi $api, PdfGenerator $pdf)
    {
        $this->db = $db;
        $this->api = $api;
        $this->pdf = $pdf;
    }

    public function startActFlow(int $chatId, int $telegramId, array $user): void
    {
        $contracts = $this->db->getDocumentsByUserId($user['id'], 'contract');

        if (empty($contracts)) {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->api->sendMessage($chatId, 'У вас пока нет договоров. Сначала создайте договор.', $keyboard);
            return;
        }

        $buttons = [];
        foreach ($contracts as $c) {
            $label = "{$c['contract_number']} — {$c['client_name']}";
            $buttons[] = [['text' => ContractHandler::truncateButton($label), 'callback_data' => 'select_contract_' . $c['id']]];
        }
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, 'Выберите договор для создания акта:', $keyboard);
    }

    public function onContractSelectedForAct(int $chatId, int $telegramId, int $contractId): void
    {
        $this->db->setState($telegramId, 'act_date', ['contract_id' => $contractId]);
        $today = date('d.m.Y');
        $this->api->sendMessage($chatId, "Введите дату акта (ДД.ММ.ГГГГ) или отправьте «-» для сегодняшней ({$today}):", $this->dateButtons());
    }

    public function handleStateInput(int $chatId, int $telegramId, string $text, string $state, array $data): bool
    {
        if ($state !== 'act_date') {
            return false;
        }

        if ($text === '-' || $text === '') {
            $actDate = date('Y-m-d');
        } else {
            $actDate = ContractHandler::parseDate($text);
            if (!$actDate) {
                $this->api->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                return true;
            }
        }

        $data['act_date'] = $actDate;
        $this->showConfirmation($chatId, $telegramId, $data);
        return true;
    }

    public function showConfirmation(int $chatId, int $telegramId, array $data): void
    {
        $contract = $this->db->getDocumentById($data['contract_id']);
        $client = $this->db->getClientById($contract['client_id']);
        $actDate = date('d.m.Y', strtotime($data['act_date']));

        $msg = "📋 <b>Подтверждение акта:</b>\n\n"
            . "Договор: {$contract['contract_number']}\n"
            . "Клиент: {$client['full_name']}\n"
            . "Дата акта: {$actDate}\n"
            . "Сумма: {$contract['amount']} руб.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_act'],
                    ['text' => '✏️ Изменить', 'callback_data' => 'edit_act'],
                    ['text' => '❌ Отмена', 'callback_data' => 'cancel_act'],
                ],
            ],
        ];

        $this->db->setState($telegramId, 'act_confirm', $data);
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    public function handleConfirm(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $this->generateAct($chatId, $telegramId, $data['contract_id'], $data['act_date']);
    }

    public function handleEdit(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $this->db->setState($telegramId, 'act_date', ['contract_id' => $data['contract_id']]);
        $today = date('d.m.Y');
        $this->api->sendMessage($chatId, "Введите дату акта (ДД.ММ.ГГГГ) или отправьте «-» для сегодняшней ({$today}):", $this->dateButtons());
    }

    public function handleDateCallback(int $chatId, int $telegramId, string $callbackData): void
    {
        $stateRow = $this->db->getState($telegramId);
        if (!$stateRow || $stateRow['state'] !== 'act_date') return;

        $data = json_decode($stateRow['data'] ?? '{}', true) ?: [];

        $date = match ($callbackData) {
            'date_today'  => date('Y-m-d'),
            'date_plus30' => date('Y-m-d', strtotime('+30 days')),
            'date_plus90' => date('Y-m-d', strtotime('+90 days')),
            default       => null,
        };

        if (!$date) return;

        $data['act_date'] = $date;
        $this->showConfirmation($chatId, $telegramId, $data);
    }

    private function generateAct(int $chatId, int $telegramId, int $contractId, string $actDate): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        $contract = $this->db->getDocumentById($contractId);

        if (!$user || !$contract) {
            $this->api->sendMessage($chatId, 'Ошибка: данные не найдены.');
            $this->db->clearState($telegramId);
            return;
        }

        $client = $this->db->getClientById($contract['client_id']);
        if (!$client) {
            $this->api->sendMessage($chatId, 'Ошибка: клиент не найден.');
            $this->db->clearState($telegramId);
            return;
        }

        // Free tier limit check
        if (!$this->db->isSubscriptionActive($user)) {
            $monthCount = $this->db->countDocumentsThisMonth($user['id']);
            if ($monthCount >= 3) {
                $this->api->sendMessage($chatId, "📊 Вы достигли лимита бесплатных документов (3 в месяц). Для безлимитного доступа оформите подписку.");
                $this->db->clearState($telegramId);
                return;
            }
        }

        $count = $this->db->countDocumentsToday($user['id'], 'act');
        $actNumber = 'АКТ-' . date('Ymd') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        $actData = [
            'act_number' => $actNumber,
            'act_date'   => $actDate,
        ];

        $this->api->sendMessage($chatId, '⏳ Генерирую акт...');

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

            $this->api->sendDocument($chatId, $pdfPath, "Акт {$actNumber}");
            $menuKb = ['inline_keyboard' => [[['text' => '📋 Главное меню', 'callback_data' => 'main_menu']]]];
            $this->api->sendMessage($chatId, "✅ Акт {$actNumber} к договору {$contract['contract_number']} создан и отправлен.", $menuKb);
        } catch (\Throwable $e) {
            $this->api->sendMessage($chatId, '❌ Ошибка при генерации PDF: ' . $e->getMessage());
            $this->db->clearState($telegramId);
        }
    }

    private function dateButtons(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📅 Сегодня', 'callback_data' => 'date_today'],
                    ['text' => '📅 +30 дней', 'callback_data' => 'date_plus30'],
                    ['text' => '📅 +90 дней', 'callback_data' => 'date_plus90'],
                ],
            ],
        ];
    }
}
