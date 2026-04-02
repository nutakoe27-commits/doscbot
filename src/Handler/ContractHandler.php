<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;
use DocBot\PdfGenerator;
use DocBot\NumberToWords;

class ContractHandler
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

    public function startContractFlow(int $chatId, int $telegramId, array $user): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📄 Оказание услуг', 'callback_data' => 'contract_type_service']],
                [['text' => '🔧 Подряд', 'callback_data' => 'contract_type_podryad']],
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];

        $this->db->setState($telegramId, 'contract_select_type', ['_contract_flow' => true]);
        $this->api->sendMessage($chatId, 'Выберите тип договора:', $keyboard);
    }

    public function handleTypeSelection(int $chatId, int $telegramId, string $subtype, array $user): void
    {
        $clients = $this->db->getClientsByUserId($user['id']);

        $buttons = [];
        foreach ($clients as $c) {
            $buttons[] = [['text' => self::truncateButton($c['full_name']), 'callback_data' => 'select_client_' . $c['id']]];
        }
        $buttons[] = [['text' => '➕ Новый клиент', 'callback_data' => 'new_client']];
        $buttons[] = [['text' => '« Назад в меню', 'callback_data' => 'main_menu']];

        $this->db->setState($telegramId, 'contract_select_client', [
            '_contract_flow' => true,
            'contract_subtype' => $subtype,
        ]);

        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, 'Выберите клиента для договора:', $keyboard);
    }

    public function onClientSelected(int $chatId, int $telegramId, int $clientId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $data['client_id'] = $clientId;
        $data['_contract_flow'] = true;

        $isPodryad = ($data['contract_subtype'] ?? 'service') === 'podryad';
        $prompt = $isPodryad ? 'Введите описание работ:' : 'Введите описание услуги:';

        $this->db->setState($telegramId, 'contract_service', $data);
        $this->api->sendMessage($chatId, $prompt);
    }

    public function handleStateInput(int $chatId, int $telegramId, string $text, string $state, array $data): bool
    {
        if (!str_starts_with($state, 'contract_')) {
            return false;
        }

        switch ($state) {
            case 'contract_service':
                $data['service_description'] = $text;
                $this->db->setState($telegramId, 'contract_amount', $data);
                $this->api->sendMessage($chatId, 'Введите сумму (число, например: 15000):');
                break;

            case 'contract_amount':
                $amount = (float) str_replace([' ', ','], ['', '.'], $text);
                if ($amount <= 0) {
                    $this->api->sendMessage($chatId, 'Введите корректную сумму (число больше 0):');
                    return true;
                }
                $data['amount'] = $amount;
                $this->db->setState($telegramId, 'contract_city', $data);
                $this->api->sendMessage($chatId, 'Введите город (или отправьте «-» для Москвы):');
                break;

            case 'contract_city':
                $data['city'] = ($text === '-' || $text === '') ? 'Москва' : $text;
                $this->db->setState($telegramId, 'contract_date_start', $data);
                $this->api->sendMessage($chatId, 'Введите дату начала договора (ДД.ММ.ГГГГ):', $this->dateButtons());
                break;

            case 'contract_date_start':
                $date = self::parseDate($text);
                if (!$date) {
                    $this->api->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                    return true;
                }
                $data['date_start'] = $date;
                $this->db->setState($telegramId, 'contract_date_end', $data);
                $this->api->sendMessage($chatId, 'Введите дату окончания договора (ДД.ММ.ГГГГ):', $this->dateButtons());
                break;

            case 'contract_date_end':
                $date = self::parseDate($text);
                if (!$date) {
                    $this->api->sendMessage($chatId, 'Неверный формат даты. Введите в формате ДД.ММ.ГГГГ:');
                    return true;
                }
                $data['date_end'] = $date;
                $this->showConfirmation($chatId, $telegramId, $data);
                break;

            default:
                return false;
        }

        return true;
    }

    public function showConfirmation(int $chatId, int $telegramId, array $data): void
    {
        $client = $this->db->getClientById($data['client_id']);
        $typeLabels = ['fiz' => 'физлицо', 'ip' => 'ИП', 'org' => 'юрлицо'];
        $typeName = $typeLabels[$client['client_type']] ?? $client['client_type'];

        $dateStart = date('d.m.Y', strtotime($data['date_start']));
        $dateEnd = date('d.m.Y', strtotime($data['date_end']));

        $subtypeLabels = ['service' => 'Договор оказания услуг', 'podryad' => 'Договор подряда'];
        $subtype = $data['contract_subtype'] ?? 'service';
        $subtypeLabel = $subtypeLabels[$subtype] ?? $subtypeLabels['service'];

        $descLabel = $subtype === 'podryad' ? 'Работы' : 'Услуга';

        $msg = "📄 <b>Подтверждение договора:</b>\n\n"
            . "Тип: {$subtypeLabel}\n"
            . "Клиент: {$client['full_name']}\n"
            . "Тип клиента: {$typeName}\n"
            . "{$descLabel}: {$data['service_description']}\n"
            . "Сумма: {$data['amount']} руб.\n"
            . "Город: {$data['city']}\n"
            . "Период: {$dateStart} — {$dateEnd}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Подтвердить', 'callback_data' => 'confirm_contract'],
                    ['text' => '✏️ Изменить', 'callback_data' => 'edit_contract'],
                    ['text' => '❌ Отмена', 'callback_data' => 'cancel_contract'],
                ],
            ],
        ];

        $this->db->setState($telegramId, 'contract_confirm', $data);
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    public function handleConfirm(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $this->generateContract($chatId, $telegramId, $data);
    }

    public function handleEdit(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        $data = $stateRow ? (json_decode($stateRow['data'] ?? '{}', true) ?: []) : [];
        $subtype = $data['contract_subtype'] ?? 'service';
        // Keep client_id and subtype but restart from service description
        $newData = [
            'client_id' => $data['client_id'] ?? null,
            'contract_subtype' => $subtype,
            '_contract_flow' => true,
        ];
        $isPodryad = $subtype === 'podryad';
        $prompt = $isPodryad ? 'Введите описание работ:' : 'Введите описание услуги:';
        $this->db->setState($telegramId, 'contract_service', $newData);
        $this->api->sendMessage($chatId, $prompt);
    }

    public function generateContract(int $chatId, int $telegramId, array $data): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        $client = $this->db->getClientById($data['client_id']);

        if (!$user || !$client) {
            $this->api->sendMessage($chatId, 'Ошибка: данные не найдены.');
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

        $subtype = $data['contract_subtype'] ?? 'service';
        $isPodryad = $subtype === 'podryad';

        $count = $this->db->countDocumentsToday($user['id'], 'contract');
        $prefix = $isPodryad ? 'ДП-' : 'ДОУ-';
        $contractNumber = $prefix . date('Ymd') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $amountWords = NumberToWords::convert((float) $data['amount']);

        $docData = [
            'user_id'             => $user['id'],
            'client_id'           => $client['id'],
            'doc_type'            => 'contract',
            'contract_subtype'    => $subtype,
            'contract_number'     => $contractNumber,
            'service_description' => $data['service_description'],
            'amount'              => $data['amount'],
            'amount_words'        => $amountWords,
            'date_start'          => $data['date_start'],
            'date_end'            => $data['date_end'],
            'city'                => $data['city'] ?? 'Москва',
            'payment_terms'       => 'по безналичному расчёту',
            'payment_days'        => 5,
            'payment_moment'      => $isPodryad
                ? 'с момента подписания Акта приема-передачи выполненных Работ'
                : 'с момента подписания Акта сдачи-приёмки',
            'acceptance_days'     => 5,
            'termination_days'    => 14,
        ];

        $this->api->sendMessage($chatId, '⏳ Генерирую договор...');

        try {
            $pdfPath = $isPodryad
                ? $this->pdf->generatePodryad($user, $client, $docData)
                : $this->pdf->generateContract($user, $client, $docData);
            $docData['pdf_path'] = $pdfPath;
            $this->db->createDocument($docData);
            $this->db->clearState($telegramId);

            $this->api->sendDocument($chatId, $pdfPath, "Договор {$contractNumber}");
            $menuKb = ['inline_keyboard' => [[['text' => '📋 Главное меню', 'callback_data' => 'main_menu']]]];
            $this->api->sendMessage($chatId, "✅ Договор {$contractNumber} создан и отправлен.", $menuKb);
        } catch (\Throwable $e) {
            $this->api->sendMessage($chatId, '❌ Ошибка при генерации PDF: ' . $e->getMessage());
            $this->db->clearState($telegramId);
        }
    }

    public function handleDateCallback(int $chatId, int $telegramId, string $callbackData): void
    {
        $stateRow = $this->db->getState($telegramId);
        if (!$stateRow) return;

        $state = $stateRow['state'];
        $data = json_decode($stateRow['data'] ?? '{}', true) ?: [];

        $date = match ($callbackData) {
            'date_today'  => date('Y-m-d'),
            'date_plus30' => date('Y-m-d', strtotime('+30 days')),
            'date_plus90' => date('Y-m-d', strtotime('+90 days')),
            default       => null,
        };

        if (!$date) return;

        if ($state === 'contract_date_start') {
            $data['date_start'] = $date;
            $this->db->setState($telegramId, 'contract_date_end', $data);
            $formatted = date('d.m.Y', strtotime($date));
            $this->api->sendMessage($chatId, "Дата начала: {$formatted}\n\nВведите дату окончания договора (ДД.ММ.ГГГГ):", $this->dateButtons());
        } elseif ($state === 'contract_date_end') {
            $data['date_end'] = $date;
            $this->showConfirmation($chatId, $telegramId, $data);
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

    public static function truncateButton(string $text, int $maxLen = 30): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen - 1) . '…';
    }

    public static function parseDate(string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $text, $m)) {
            $d = (int) $m[1];
            $mon = (int) $m[2];
            $y = (int) $m[3];
            if (checkdate($mon, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mon, $d);
            }
        }
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
