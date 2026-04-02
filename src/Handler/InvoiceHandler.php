<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;
use DocBot\PdfGenerator;
use DocBot\NumberToWords;

class InvoiceHandler
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

    public function startInvoiceFlow(int $chatId, int $telegramId, array $user): void
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
            $amount = number_format((float) $c['amount'], 0, '', ' ');
            $label = "{$c['contract_number']} — {$c['client_name']} ({$amount} руб.)";
            $buttons[] = [['text' => ContractHandler::truncateButton($label, 50), 'callback_data' => 'invoice_contract_' . $c['id']]];
        }
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, 'Выберите договор для выставления счёта:', $keyboard);
    }

    public function onContractSelected(int $chatId, int $telegramId, int $contractId): void
    {
        $this->db->setState($telegramId, 'invoice_date', ['contract_id' => $contractId]);
        $today = date('d.m.Y');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📅 Сегодня', 'callback_data' => 'invoice_date_today'],
                ],
            ],
        ];
        $this->api->sendMessage($chatId, "Введите дату счёта (ДД.ММ.ГГГГ) или нажмите кнопку ({$today}):", $keyboard);
    }

    public function handleStateInput(int $chatId, int $telegramId, string $text, string $state, array $data): bool
    {
        if ($state !== 'invoice_date') {
            return false;
        }

        if ($text === '-' || $text === '') {
            $invoiceDate = date('Y-m-d');
        } else {
            $invoiceDate = ContractHandler::parseDate($text);
            if (!$invoiceDate) {
                $this->api->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                return true;
            }
        }

        $data['invoice_date'] = $invoiceDate;
        $this->showConfirmation($chatId, $telegramId, $data);
        return true;
    }

    public function handleDateCallback(int $chatId, int $telegramId, string $callbackData): void
    {
        $stateRow = $this->db->getState($telegramId);
        if (!$stateRow || $stateRow['state'] !== 'invoice_date') return;

        $data = json_decode($stateRow['data'] ?? '{}', true) ?: [];

        if ($callbackData === 'invoice_date_today') {
            $data['invoice_date'] = date('Y-m-d');
            $this->showConfirmation($chatId, $telegramId, $data);
        }
    }

    public function showConfirmation(int $chatId, int $telegramId, array $data): void
    {
        $contract = $this->db->getDocumentById($data['contract_id']);
        $client = $this->db->getClientById($contract['client_id']);
        $invoiceDate = date('d.m.Y', strtotime($data['invoice_date']));
        $amount = number_format((float) $contract['amount'], 2, '.', ' ');

        $msg = "🧾 <b>Подтверждение счёта:</b>\n\n"
            . "Договор: {$contract['contract_number']}\n"
            . "Клиент: {$client['full_name']}\n"
            . "Услуга: {$contract['service_description']}\n"
            . "Дата счёта: {$invoiceDate}\n"
            . "Сумма: {$amount} руб.\n"
            . "НДС: не облагается (самозанятый, 422-ФЗ)";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_invoice'],
                    ['text' => '✏️ Изменить', 'callback_data' => 'edit_invoice'],
                    ['text' => '❌ Отмена', 'callback_data' => 'cancel_invoice'],
                ],
            ],
        ];

        $this->db->setState($telegramId, 'invoice_confirm', $data);
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    public function handleConfirm(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $this->generateInvoice($chatId, $telegramId, $data['contract_id'], $data['invoice_date']);
    }

    public function handleEdit(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $this->db->setState($telegramId, 'invoice_date', ['contract_id' => $data['contract_id']]);
        $today = date('d.m.Y');
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📅 Сегодня', 'callback_data' => 'invoice_date_today'],
                ],
            ],
        ];
        $this->api->sendMessage($chatId, "Введите дату счёта (ДД.ММ.ГГГГ) или нажмите кнопку ({$today}):", $keyboard);
    }

    private function generateInvoice(int $chatId, int $telegramId, int $contractId, string $invoiceDate): void
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

        $count = $this->db->countDocumentsToday($user['id'], 'invoice');
        $invoiceNumber = 'СЧ-' . date('Ymd') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $amountWords = NumberToWords::convert((float) $contract['amount']);

        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'invoice_date'   => $invoiceDate,
            'amount_words'   => $amountWords,
        ];

        $this->api->sendMessage($chatId, '⏳ Генерирую счёт...');

        try {
            $pdfPath = $this->pdf->generateInvoice($user, $client, $contract, $invoiceData);

            $docData = [
                'user_id'             => $user['id'],
                'client_id'           => $client['id'],
                'doc_type'            => 'invoice',
                'contract_number'     => $invoiceNumber,
                'service_description' => $contract['service_description'],
                'amount'              => $contract['amount'],
                'amount_words'        => $amountWords,
                'date_start'          => $contract['date_start'],
                'date_end'            => $contract['date_end'],
                'city'                => $contract['city'],
                'pdf_path'            => $pdfPath,
                'parent_contract_id'  => $contractId,
            ];
            $this->db->createDocument($docData);
            $this->db->clearState($telegramId);

            $this->api->sendDocument($chatId, $pdfPath, "Счёт {$invoiceNumber}");
            $menuKb = ['inline_keyboard' => [[['text' => '📋 Главное меню', 'callback_data' => 'main_menu']]]];
            $this->api->sendMessage($chatId, "✅ Счёт {$invoiceNumber} создан и отправлен.", $menuKb);
        } catch (\Throwable $e) {
            $this->api->sendMessage($chatId, '❌ Ошибка при генерации PDF: ' . $e->getMessage());
            $this->db->clearState($telegramId);
        }
    }
}
