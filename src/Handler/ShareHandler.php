<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;

class ShareHandler
{
    private Database $db;
    private TelegramApi $api;
    private string $storageDir;

    public function __construct(Database $db, TelegramApi $api, string $storageDir)
    {
        $this->db = $db;
        $this->api = $api;
        $this->storageDir = $storageDir;
    }

    private function generateToken(int $length = 8): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }

    // ── Share document (sender clicks "Отправить заказчику") ──

    public function handleShareStart(int $chatId, int $telegramId, int $documentId): void
    {
        $document = $this->db->getDocumentById($documentId);
        if (!$document) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            return;
        }

        $token = $this->generateToken();
        $this->db->createDocumentShare($documentId, $telegramId, $token);

        $link = "https://t.me/docssz_bot?start=doc_{$token}";

        $msg = "📨 Ссылка для заказчика:\n\n"
            . "{$link}\n\n"
            . "Отправьте эту ссылку заказчику в любом чате.\n"
            . "Вы получите уведомление, когда заказчик просмотрит или подтвердит документ.\n\n"
            . "📄 Текущий статус: ⏳ Отправлен";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Client opens deep link doc_{token} ──

    public function handleDocumentView(int $chatId, int $telegramId, string $param): void
    {
        $token = substr($param, 4); // strip "doc_"
        $share = $this->db->getDocumentShareByToken($token);

        if (!$share) {
            $this->api->sendMessage($chatId, 'Документ не найден или ссылка устарела.');
            return;
        }

        // Update recipient and status
        $this->db->updateShareRecipient($share['id'], $telegramId);
        if ($share['status'] === 'sent') {
            $this->db->updateShareStatus($share['id'], 'viewed');
        }

        $document = $this->db->getDocumentById($share['document_id']);
        $user = $this->db->getUserByTelegramId($share['sender_telegram_id']);
        $client = $this->db->getClientById($document['client_id']);

        // Notify sender
        $senderChat = $share['sender_telegram_id'];
        $this->api->sendMessage($senderChat,
            "👁 Заказчик просмотрел документ {$document['contract_number']}"
        );

        // Doc type labels
        $docTypeLabels = [
            'contract' => 'Договор',
            'act'      => 'Акт',
            'invoice'  => 'Счёт',
        ];
        $docTypeLabel = $docTypeLabels[$document['doc_type']] ?? 'Документ';

        $amount = number_format((float) $document['amount'], 2, '.', ' ');
        $dateStart = $document['date_start'] ? date('d.m.Y', strtotime($document['date_start'])) : '';
        $dateEnd = $document['date_end'] ? date('d.m.Y', strtotime($document['date_end'])) : '';
        $period = ($dateStart && $dateEnd) ? "{$dateStart} — {$dateEnd}" : '';

        $msg = "📄 Вам направлен документ на подтверждение\n\n"
            . "{$docTypeLabel} №{$document['contract_number']}\n"
            . "От: {$user['full_name']} (ИНН {$user['inn']})\n"
            . "Сумма: {$amount} ₽\n";
        if ($period) {
            $msg .= "Срок: {$period}\n";
        }
        $msg .= "\nНажимая «Подтвердить», вы подтверждаете ознакомление "
            . "с условиями документа и выражаете согласие с ними "
            . "в порядке п. 2 ст. 434 ГК РФ.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '👁 Просмотреть PDF', 'callback_data' => 'view_shared_pdf_' . $share['id']]],
                [['text' => '✅ Подтвердить получение', 'callback_data' => 'confirm_doc_' . $share['id']]],
                [['text' => '❌ Отклонить / Запросить правки', 'callback_data' => 'reject_doc_' . $share['id']]],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── View shared PDF ──

    public function handleViewSharedPdf(int $chatId, int $shareId): void
    {
        $share = $this->db->getShareById($shareId);
        if (!$share) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            return;
        }

        $document = $this->db->getDocumentById($share['document_id']);
        if (!$document || !$document['pdf_path']) {
            $this->api->sendMessage($chatId, 'PDF-файл не найден.');
            return;
        }

        $pdfPath = $document['pdf_path'];
        if (!file_exists($pdfPath)) {
            $pdfPath = $this->storageDir . '/' . basename($document['pdf_path']);
        }

        if (!file_exists($pdfPath)) {
            $this->api->sendMessage($chatId, 'PDF-файл не найден на сервере.');
            return;
        }

        $this->api->sendDocument($chatId, $pdfPath, $document['contract_number']);
    }

    // ── Confirm document ──

    public function handleConfirm(int $chatId, int $telegramId, int $shareId): void
    {
        $share = $this->db->getShareById($shareId);
        if (!$share) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            return;
        }

        if ($share['status'] === 'confirmed') {
            $this->api->sendMessage($chatId, 'Вы уже подтвердили этот документ.');
            return;
        }

        $this->db->updateShareStatus($shareId, 'confirmed');

        $document = $this->db->getDocumentById($share['document_id']);
        $now = date('d.m.Y');
        $time = date('H:i');

        // Notify recipient
        $msg = "✅ Вы подтвердили документ {$document['contract_number']}.\n"
            . "Дата подтверждения: {$now} {$time}";
        $this->api->sendMessage($chatId, $msg);

        // Notify sender
        $docTypeLabels = [
            'contract' => 'Договор',
            'act'      => 'Акт',
            'invoice'  => 'Счёт',
        ];
        $docTypeLabel = $docTypeLabels[$document['doc_type']] ?? 'Документ';

        // Try to get recipient info for notification
        $recipientUser = $this->db->getUserByTelegramId($telegramId);
        $recipientName = $recipientUser ? $recipientUser['full_name'] : 'Заказчик';
        if (!$recipientName) {
            $recipientName = 'Заказчик';
        }

        $senderMsg = "✅ Заказчик подтвердил документ!\n\n"
            . "📄 {$docTypeLabel} №{$document['contract_number']}\n"
            . "Заказчик: {$recipientName}\n"
            . "Подтверждено: {$now} в {$time}\n\n"
            . "📄 Статус: ⏳ Отправлен → ✅ Просмотрен → ✅ Подтверждён";
        $this->api->sendMessage($share['sender_telegram_id'], $senderMsg);
    }

    // ── Reject document (ask for comment) ──

    public function handleRejectStart(int $chatId, int $telegramId, int $shareId): void
    {
        $share = $this->db->getShareById($shareId);
        if (!$share) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            return;
        }

        $this->db->setState($telegramId, 'reject_comment_' . $shareId);
        $this->api->sendMessage($chatId, 'Укажите причину отклонения или комментарий:');
    }

    // ── Handle reject comment (state-based text input) ──

    public function handleRejectComment(int $chatId, int $telegramId, string $text, int $shareId): void
    {
        $share = $this->db->getShareById($shareId);
        if (!$share) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            $this->db->clearState($telegramId);
            return;
        }

        $this->db->updateShareStatus($shareId, 'rejected');
        $this->db->setShareComment($shareId, $text);
        $this->db->clearState($telegramId);

        $this->api->sendMessage($chatId, 'Ваш комментарий отправлен. Исполнитель свяжется с вами.');

        // Notify sender
        $document = $this->db->getDocumentById($share['document_id']);
        $senderMsg = "❌ Заказчик отклонил документ {$document['contract_number']}\n\n"
            . "Комментарий: \"{$text}\"";
        $this->api->sendMessage($share['sender_telegram_id'], $senderMsg);
    }

    // ── Document list (/docs or "Мои документы") ──

    public function handleMyDocuments(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            return;
        }

        $docs = $this->db->getUserDocumentsWithShares($user['id']);

        if (empty($docs)) {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->api->sendMessage($chatId, '📄 У вас пока нет документов.', $keyboard);
            return;
        }

        $msg = "📄 Мои документы\n\n";
        $buttons = [];

        foreach ($docs as $i => $doc) {
            $num = $i + 1;
            $statusLine = $this->formatStatusLine($doc['share_status']);
            $confirmedDate = '';
            if ($doc['share_status'] === 'confirmed' && $doc['share_confirmed_at']) {
                $confirmedDate = ' (' . date('d.m.Y H:i', strtotime($doc['share_confirmed_at'])) . ')';
            }

            $msg .= "{$num}. {$doc['contract_number']} — {$doc['client_name']}\n"
                . "   {$statusLine}{$confirmedDate}\n\n";

            $buttons[] = [['text' => "{$num}. {$doc['contract_number']}", 'callback_data' => 'doc_detail_' . $doc['id']]];
        }

        $buttons[] = [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']];
        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Document detail ──

    public function handleDocumentDetail(int $chatId, int $telegramId, int $documentId): void
    {
        $document = $this->db->getDocumentById($documentId);
        if (!$document) {
            $this->api->sendMessage($chatId, 'Документ не найден.');
            return;
        }

        $client = $this->db->getClientById($document['client_id']);
        $share = $this->db->getShareByDocumentId($documentId);

        $docTypeLabels = [
            'contract' => 'Договор',
            'act'      => 'Акт',
            'invoice'  => 'Счёт',
        ];
        $docTypeLabel = $docTypeLabels[$document['doc_type']] ?? 'Документ';
        $amount = number_format((float) $document['amount'], 2, '.', ' ');

        $statusLine = $share ? $this->formatStatusLine($share['status']) : '📄 Создан (не отправлен)';

        $msg = "📄 {$docTypeLabel} №{$document['contract_number']}\n\n"
            . "Клиент: {$client['full_name']}\n"
            . "Сумма: {$amount} ₽\n"
            . "Статус: {$statusLine}\n";

        if ($share && $share['status'] === 'confirmed' && $share['confirmed_at']) {
            $msg .= "Подтверждено: " . date('d.m.Y H:i', strtotime($share['confirmed_at'])) . "\n";
        }
        if ($share && $share['status'] === 'rejected' && $share['recipient_comment']) {
            $msg .= "Комментарий: \"{$share['recipient_comment']}\"\n";
        }

        $buttons = [];
        if (!$share) {
            $buttons[] = [['text' => '📨 Отправить заказчику', 'callback_data' => 'share_doc_' . $documentId]];
        }
        $buttons[] = [['text' => '« Назад к документам', 'callback_data' => 'my_documents']];
        $buttons[] = [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']];

        $keyboard = ['inline_keyboard' => $buttons];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Profile card (/profile) ──

    public function handleProfileCommand(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            return;
        }

        $profile = $this->db->getProfileByUserId($user['id']);
        if (!$profile) {
            // Auto-create profile
            $token = $this->generateToken();
            $this->db->createProfile($user['id'], $token);
            $profile = $this->db->getProfileByUserId($user['id']);
        }

        $link = "https://t.me/docssz_bot?start=profile_{$profile['profile_token']}";

        $msg = "👤 Ваша визитка\n\n"
            . "{$user['full_name']}\n"
            . "ИНН: {$user['inn']}\n"
            . "Статус: Самозанятый (НПД)\n"
            . "📱 {$user['phone']}\n\n"
            . "🔗 Ваша ссылка: {$link}\n\n"
            . "Вставьте ссылку в профиль на Авито, Kwork, HH.ru —\n"
            . "каждый клик = потенциальный клиент.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Client views profile via deep link ──

    public function handleProfileView(int $chatId, int $telegramId, string $param): void
    {
        $token = substr($param, 8); // strip "profile_"
        $profile = $this->db->getProfileByToken($token);

        if (!$profile) {
            $this->api->sendMessage($chatId, 'Профиль не найден или ссылка устарела.');
            return;
        }

        $msg = "👤 Визитка исполнителя\n\n"
            . "{$profile['full_name']}\n"
            . "ИНН: {$profile['inn']}\n"
            . "Статус: Самозанятый (НПД)\n\n"
            . "Этот исполнитель работает через DocBot —\n"
            . "сервис для безопасного оформления договоров.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📄 Создать договор с этим исполнителем', 'callback_data' => 'hire_' . $profile['user_id']]],
                [['text' => '📋 Подробнее о DocBot', 'callback_data' => 'about_docbot']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Hire callback ──

    public function handleHire(int $chatId, int $telegramId, int $targetUserId): void
    {
        $visitor = $this->db->getUserByTelegramId($telegramId);
        if ($visitor && $visitor['full_name']) {
            $this->api->sendMessage($chatId, 'Функция скоро будет доступна.');
        } else {
            $this->api->sendMessage($chatId, 'Для создания документов зарегистрируйтесь: нажмите /start');
        }
    }

    // ── About DocBot callback ──

    public function handleAboutDocBot(int $chatId): void
    {
        $msg = "📄 DocBot — бот для самозанятых\n\n"
            . "Создавайте договоры, акты и счета за пару минут.\n"
            . "Все документы соответствуют ГК РФ и 422-ФЗ.\n\n"
            . "Нажмите /start, чтобы начать работу.";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🚀 Начать', 'callback_data' => 'start_bot']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard);
    }

    // ── Helpers ──

    private function formatStatusLine(?string $shareStatus): string
    {
        return match ($shareStatus) {
            'sent'      => '⏳ Отправлен',
            'viewed'    => '⏳ Отправлен → ✅ Просмотрен',
            'confirmed' => '⏳ Отправлен → ✅ Просмотрен → ✅ Подтверждён',
            'rejected'  => '⏳ Отправлен → ✅ Просмотрен → ❌ Отклонён',
            default     => '📄 Создан (не отправлен)',
        };
    }
}
