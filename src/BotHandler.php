<?php

namespace DocBot;

use DocBot\Handler\RegistrationHandler;
use DocBot\Handler\ContractHandler;
use DocBot\Handler\ActHandler;
use DocBot\Handler\InvoiceHandler;
use DocBot\Handler\ClientHandler;
use DocBot\Handler\ExportHandler;
use DocBot\Handler\AdminHandler;
use DocBot\Handler\PaymentHandler;
use DocBot\Handler\ShareHandler;

class BotHandler
{
    private Database $db;
    private PdfGenerator $pdf;
    private TelegramApi $api;
    private string $storageDir;

    private RegistrationHandler $registrationHandler;
    private ContractHandler $contractHandler;
    private ActHandler $actHandler;
    private InvoiceHandler $invoiceHandler;
    private ClientHandler $clientHandler;
    private ExportHandler $exportHandler;
    private AdminHandler $adminHandler;
    private PaymentHandler $paymentHandler;
    private ShareHandler $shareHandler;

    public function __construct(array $config)
    {
        $this->storageDir = rtrim($config['pdf_storage'], '/');
        $sslVerify = $config['ssl_verify'] ?? false;
        $adminId = $config['admin_telegram_id'] ?? 0;
        $this->db = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
        $this->pdf = new PdfGenerator(__DIR__ . '/../templates', $this->storageDir);
        $proxy = $config['telegram_proxy'] ?? null;
        $this->api = new TelegramApi($config['bot_token'], $sslVerify, $proxy);

        $this->registrationHandler = new RegistrationHandler($this->db, $this->api);
        $this->contractHandler = new ContractHandler($this->db, $this->api, $this->pdf);
        $this->actHandler = new ActHandler($this->db, $this->api, $this->pdf);
        $this->invoiceHandler = new InvoiceHandler($this->db, $this->api, $this->pdf);
        $this->clientHandler = new ClientHandler($this->db, $this->api);
        $this->exportHandler = new ExportHandler($this->db, $this->api, $this->storageDir);
        $this->adminHandler = new AdminHandler($this->db, $this->api, $adminId);
        $this->paymentHandler = new PaymentHandler($this->db, $this->api, $config);
        $this->shareHandler = new ShareHandler($this->db, $this->api, $this->storageDir);
    }

    public function reconnectDb(): void
    {
        $this->db->reconnectIfNeeded();
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
        $username = $message['from']['username'] ?? null;

        // Обновляем username при каждом сообщении (пользователь мог сменить ник)
        if ($username) {
            $this->db->updateUsername($telegramId, $username);
        }

        // Handle non-text messages (photo, sticker, voice, etc.)
        if ($text === '') {
            $stateRow = $this->db->getState($telegramId);
            if ($stateRow && $stateRow['state']) {
                $this->api->sendMessage($chatId, 'Пожалуйста, отправьте текстовое сообщение.');
            }
            return;
        }

        // Commands
        if (str_starts_with($text, '/start')) {
            if (str_starts_with($text, '/start ')) {
                $param = substr($text, 7);
                if (str_starts_with($param, 'doc_')) {
                    $this->shareHandler->handleDocumentView($chatId, $telegramId, $param);
                    return;
                }
                if (str_starts_with($param, 'profile_')) {
                    $this->shareHandler->handleProfileView($chatId, $telegramId, $param);
                    return;
                }
            }
            $this->handleStart($chatId, $telegramId);
            return;
        }
        if ($text === '/docs') {
            $this->shareHandler->handleMyDocuments($chatId, $telegramId);
            return;
        }
        if ($text === '/profile') {
            $this->shareHandler->handleProfileCommand($chatId, $telegramId);
            return;
        }
        if ($text === '/help') {
            $this->handleHelp($chatId);
            return;
        }
        if ($text === '/mydata') {
            $this->registrationHandler->handleMyData($chatId, $telegramId);
            return;
        }
        if ($text === '/cancel') {
            $this->handleCancel($chatId, $telegramId);
            return;
        }
        if ($text === '/menu') {
            $user = $this->db->getUserByTelegramId($telegramId);
            if ($user && $user['full_name']) {
                $this->showMainMenu($chatId);
            } else {
                $this->api->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            }
            return;
        }
        if ($text === '/export') {
            $this->exportHandler->handleExport($chatId, $telegramId);
            return;
        }
        if ($text === '/admin') {
            $this->adminHandler->handleAdmin($chatId, $telegramId);
            return;
        }
        if ($text === '/broadcast') {
            $this->adminHandler->handleBroadcastStart($chatId, $telegramId);
            return;
        }
        if (str_starts_with($text, '/grant ')) {
            $this->adminHandler->handleGrant($chatId, $telegramId, $text);
            return;
        }
        if (str_starts_with($text, '/revoke ')) {
            $this->adminHandler->handleRevoke($chatId, $telegramId, $text);
            return;
        }
        if ($text === '/deletedata') {
            $this->handleDeleteDataStart($chatId, $telegramId);
            return;
        }

        // State-based input
        $stateRow = $this->db->getState($telegramId);
        if ($stateRow && $stateRow['state']) {
            $this->handleStateInput($chatId, $telegramId, $text, $stateRow);
            return;
        }

        $this->api->sendMessage($chatId, 'Используйте /start для начала работы или /help для справки.');
    }

    // ── /start ──

    private function handleStart(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);

        if ($user && $user['full_name']) {
            $this->api->sendMessage($chatId, "Добро пожаловать, {$user['full_name']}! 👋");
            $this->showMainMenu($chatId);
            return;
        }

        // New user — show consent screen before registration
        $this->showConsentScreen($chatId);
    }

    private function showConsentScreen(int $chatId): void
    {
        $text = "Добро пожаловать в DocBot! 📄\n\n"
            . "Для работы бот сохраняет ваши данные (ФИО, ИНН, банковские реквизиты) на защищённом сервере в РФ.\n\n"
            . "📄 Политика обработки ПД: https://www.krichigindocs.ru/public/privacy.html\n"
            . "📄 Пользовательское соглашение: https://www.krichigindocs.ru/public/terms.html\n\n"
            . "Нажимая «Принимаю», вы даёте согласие на обработку персональных данных в соответствии с 152-ФЗ.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Принимаю', 'callback_data' => 'accept_consent'],
                    ['text' => '❌ Отказываюсь', 'callback_data' => 'decline_consent'],
                ],
            ],
        ];
        $this->api->sendMessage($chatId, $text, $keyboard);
    }

    // ── Main menu ──

    public function showMainMenu(int $chatId): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📄 Создать договор', 'callback_data' => 'new_contract']],
                [['text' => '📋 Создать акт', 'callback_data' => 'new_act']],
                [['text' => '🧾 Выставить счёт', 'callback_data' => 'new_invoice']],
                [['text' => '📄 Мои документы', 'callback_data' => 'my_documents']],
                [['text' => '👥 Мои клиенты', 'callback_data' => 'my_clients']],
                [['text' => '📤 Экспорт документов', 'callback_data' => 'export_docs']],
                [['text' => '⚙️ Мои реквизиты', 'callback_data' => 'my_data']],
                [['text' => '💳 Подписка', 'callback_data' => 'subscription_info']],
                [['text' => 'ℹ️ Помощь', 'callback_data' => 'help']],
            ],
        ];
        $this->api->sendMessage($chatId, 'Главное меню:', $keyboard);
    }

    // ── Callback handler ──

    private function handleCallback(array $callback): void
    {
        $chatId = $callback['message']['chat']['id'];
        $telegramId = $callback['from']['id'];
        $data = $callback['data'];
        $callbackId = $callback['id'];
        $username = $callback['from']['username'] ?? null;

        $this->api->answerCallback($callbackId);

        if ($username) {
            $this->db->updateUsername($telegramId, $username);
        }

        // Date buttons can be pressed during different states
        if (in_array($data, ['date_today', 'date_plus30', 'date_plus90'], true)) {
            $stateRow = $this->db->getState($telegramId);
            if ($stateRow) {
                $state = $stateRow['state'];
                if (str_starts_with($state, 'contract_date_')) {
                    $this->contractHandler->handleDateCallback($chatId, $telegramId, $data);
                } elseif ($state === 'act_date') {
                    $this->actHandler->handleDateCallback($chatId, $telegramId, $data);
                }
            }
            return;
        }

        // Invoice date button
        if ($data === 'invoice_date_today') {
            $this->invoiceHandler->handleDateCallback($chatId, $telegramId, $data);
            return;
        }

        // Consent callbacks (before registration check — user is not yet registered)
        if ($data === 'accept_consent') {
            $this->handleAcceptConsent($chatId, $telegramId);
            return;
        }
        if ($data === 'decline_consent') {
            $this->api->sendMessage($chatId,
                'Без согласия на обработку персональных данных использование бота невозможно. '
                . 'Вы можете передумать, нажав /start.'
            );
            return;
        }

        // Delete account confirmation
        if ($data === 'confirm_delete_account') {
            $this->handleDeleteDataConfirm($chatId, $telegramId);
            return;
        }

        // Share callbacks (recipients may not be registered users)
        if (str_starts_with($data, 'view_shared_pdf_')) {
            $shareId = (int) substr($data, strlen('view_shared_pdf_'));
            $this->shareHandler->handleViewSharedPdf($chatId, $shareId);
            return;
        }
        if (str_starts_with($data, 'confirm_doc_')) {
            $shareId = (int) substr($data, strlen('confirm_doc_'));
            $this->shareHandler->handleConfirm($chatId, $telegramId, $shareId);
            return;
        }
        if (str_starts_with($data, 'reject_doc_')) {
            $shareId = (int) substr($data, strlen('reject_doc_'));
            $this->shareHandler->handleRejectStart($chatId, $telegramId, $shareId);
            return;
        }
        if (str_starts_with($data, 'hire_')) {
            $targetUserId = (int) substr($data, strlen('hire_'));
            $this->shareHandler->handleHire($chatId, $telegramId, $targetUserId);
            return;
        }
        if ($data === 'about_docbot') {
            $this->shareHandler->handleAboutDocBot($chatId);
            return;
        }
        if ($data === 'start_bot') {
            $this->handleStart($chatId, $telegramId);
            return;
        }

        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
            return;
        }

        // Share / documents
        if (str_starts_with($data, 'share_doc_')) {
            $documentId = (int) substr($data, strlen('share_doc_'));
            $this->shareHandler->handleShareStart($chatId, $telegramId, $documentId);
            return;
        }
        if ($data === 'my_documents') {
            $this->shareHandler->handleMyDocuments($chatId, $telegramId);
            return;
        }
        if (str_starts_with($data, 'doc_detail_')) {
            $documentId = (int) substr($data, strlen('doc_detail_'));
            $this->shareHandler->handleDocumentDetail($chatId, $telegramId, $documentId);
            return;
        }

        // Contract confirmation
        if ($data === 'confirm_contract') {
            $this->contractHandler->handleConfirm($chatId, $telegramId);
            return;
        }
        if ($data === 'edit_contract') {
            $this->contractHandler->handleEdit($chatId, $telegramId);
            return;
        }
        if ($data === 'cancel_contract') {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, '❌ Создание договора отменено.');
            $this->showMainMenu($chatId);
            return;
        }

        // Act confirmation
        if ($data === 'confirm_act') {
            $this->actHandler->handleConfirm($chatId, $telegramId);
            return;
        }
        if ($data === 'edit_act') {
            $this->actHandler->handleEdit($chatId, $telegramId);
            return;
        }
        if ($data === 'cancel_act') {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, '❌ Создание акта отменено.');
            $this->showMainMenu($chatId);
            return;
        }

        // Invoice confirmation
        if ($data === 'confirm_invoice') {
            $this->invoiceHandler->handleConfirm($chatId, $telegramId);
            return;
        }
        if ($data === 'edit_invoice') {
            $this->invoiceHandler->handleEdit($chatId, $telegramId);
            return;
        }
        if ($data === 'cancel_invoice') {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, '❌ Создание счёта отменено.');
            $this->showMainMenu($chatId);
            return;
        }

        // New contract
        if ($data === 'new_contract') {
            $this->contractHandler->startContractFlow($chatId, $telegramId, $user);
            return;
        }

        // Contract type selection
        if ($data === 'contract_type_service' || $data === 'contract_type_podryad') {
            $subtype = $data === 'contract_type_podryad' ? 'podryad' : 'service';
            $this->contractHandler->handleTypeSelection($chatId, $telegramId, $subtype, $user);
            return;
        }

        // New act
        if ($data === 'new_act') {
            $this->actHandler->startActFlow($chatId, $telegramId, $user);
            return;
        }

        // New invoice
        if ($data === 'new_invoice') {
            $this->invoiceHandler->startInvoiceFlow($chatId, $telegramId, $user);
            return;
        }

        // My clients
        if ($data === 'my_clients') {
            $this->clientHandler->showClientsList($chatId, $user);
            return;
        }

        // My data
        if ($data === 'my_data') {
            $this->registrationHandler->handleMyData($chatId, $telegramId);
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

        // Export
        if ($data === 'export_docs') {
            $this->exportHandler->handleExport($chatId, $telegramId);
            return;
        }

        // Admin refresh
        if ($data === 'admin_refresh') {
            $this->adminHandler->handleAdmin($chatId, $telegramId);
            return;
        }

        // Broadcast callbacks
        if ($data === 'confirm_broadcast') {
            $this->adminHandler->handleBroadcastConfirm($chatId, $telegramId);
            return;
        }
        if ($data === 'edit_broadcast') {
            $this->adminHandler->handleBroadcastEdit($chatId, $telegramId);
            return;
        }
        if ($data === 'cancel_broadcast') {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, '❌ Рассылка отменена.');
            return;
        }

        // Subscription / payment
        if ($data === 'subscription_info') {
            $this->paymentHandler->showPlans($chatId, $telegramId);
            return;
        }
        if ($data === 'pay_1') {
            $this->paymentHandler->createPayment($chatId, $telegramId, 1);
            return;
        }
        if ($data === 'pay_3') {
            $this->paymentHandler->createPayment($chatId, $telegramId, 3);
            return;
        }
        if ($data === 'pay_6') {
            $this->paymentHandler->createPayment($chatId, $telegramId, 6);
            return;
        }

        // Select client for contract
        if (str_starts_with($data, 'select_client_')) {
            $clientId = (int) substr($data, strlen('select_client_'));
            $this->contractHandler->onClientSelected($chatId, $telegramId, $clientId);
            return;
        }

        // New client
        if ($data === 'new_client') {
            $this->clientHandler->startNewClientFlow($chatId, $telegramId);
            return;
        }

        // Client type selection
        if (str_starts_with($data, 'client_type_')) {
            $type = substr($data, strlen('client_type_'));
            $this->clientHandler->onClientTypeSelected($chatId, $telegramId, $type);
            return;
        }

        // Delete client
        if (str_starts_with($data, 'delete_client_')) {
            $clientId = (int) substr($data, strlen('delete_client_'));
            $this->clientHandler->handleDeleteClient($chatId, $clientId);
            return;
        }

        // Select contract for act
        if (str_starts_with($data, 'select_contract_')) {
            $contractId = (int) substr($data, strlen('select_contract_'));
            $this->actHandler->onContractSelectedForAct($chatId, $telegramId, $contractId);
            return;
        }

        // Select contract for invoice
        if (str_starts_with($data, 'invoice_contract_')) {
            $contractId = (int) substr($data, strlen('invoice_contract_'));
            $this->invoiceHandler->onContractSelected($chatId, $telegramId, $contractId);
            return;
        }

        // Edit registration fields
        if (str_starts_with($data, 'edit_')) {
            $field = substr($data, strlen('edit_'));
            $this->registrationHandler->startEditField($chatId, $telegramId, $field);
            return;
        }
    }

    // ── State router ──

    private function handleStateInput(int $chatId, int $telegramId, string $text, array $stateRow): void
    {
        $state = $stateRow['state'];
        $data = json_decode($stateRow['data'] ?? '{}', true) ?: [];

        // Registration states
        if (str_starts_with($state, 'reg_')) {
            if ($this->registrationHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                return;
            }
        }

        // Edit field
        if ($state === 'edit_field') {
            if ($this->registrationHandler->handleEditField($chatId, $telegramId, $text, $data)) {
                return;
            }
        }

        // New client flow
        if (str_starts_with($state, 'new_client_')) {
            if ($this->clientHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                return;
            }
        }

        // Contract flow
        if (str_starts_with($state, 'contract_')) {
            if ($this->contractHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                return;
            }
        }

        // Act flow
        if ($state === 'act_date') {
            if ($this->actHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                return;
            }
        }

        // Invoice flow
        if ($state === 'invoice_date') {
            if ($this->invoiceHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                return;
            }
        }

        // Broadcast flow (admin)
        if ($state === 'broadcast_text') {
            $this->adminHandler->handleBroadcastText($chatId, $telegramId, $text);
            return;
        }

        // Reject comment flow (share)
        if (str_starts_with($state, 'reject_comment_')) {
            $shareId = (int) substr($state, strlen('reject_comment_'));
            $this->shareHandler->handleRejectComment($chatId, $telegramId, $text, $shareId);
            return;
        }

        // Unknown state — reset
        $this->db->clearState($telegramId);
        $this->showMainMenu($chatId);
    }

    // ── /help ──

    private function handleHelp(int $chatId): void
    {
        $msg = "ℹ️ <b>Справка по DocBot</b>\n\n"
            . "Я помогаю самозанятым генерировать документы:\n\n"
            . "📄 <b>Договор</b> — оказание услуг или подряд\n"
            . "📋 <b>Акт</b> — сдача-приёмка по договору\n"
            . "🧾 <b>Счёт</b> — оплата по договору\n"
            . "📨 <b>Подтверждение</b> — отправьте документ заказчику\n"
            . "👤 <b>Визитка</b> — ссылка на ваш профиль\n\n"
            . "══════════════════════\n\n"
            . "✅ <b>Электронное подтверждение</b>\n\n"
            . "Подтверждение документа через бота является\n"
            . "простой электронной подписью (ПЭП).\n\n"
            . "👍 <b>Плюсы:</b>\n"
            . "• Законно — 63-ФЗ «Об электронной подписи»\n"
            . "• Признаётся судами — Пленум ВС РФ № 18\n"
            . "• Фиксируется: кто, когда, что подтвердил\n"
            . "• Telegram ID привязан к номеру телефона\n"
            . "• Мгновенно и бесплатно\n\n"
            . "👎 <b>Ограничения:</b>\n"
            . "• Не равнозначна квалифицированной ЭЦП\n"
            . "• Не для госконтрактов и недвижимости\n"
            . "• Для сумм от 500 тыс. — бумажный договор\n"
            . "• Контрагент может оспорить в суде\n\n"
            . "💡 <b>Совет:</b> для максимальной защиты\n"
            . "обменяйтесь бумажными оригиналами\n"
            . "в течение 30 дней (п. 7.3 договора).\n\n"
            . "══════════════════════\n\n"
            . "<b>Команды:</b>\n"
            . "/start — начать работу\n"
            . "/menu — главное меню\n"
            . "/docs — мои документы\n"
            . "/profile — моя визитка\n"
            . "/mydata — мои реквизиты\n"
            . "/export — экспорт в CSV\n"
            . "/cancel — отмена\n"
            . "/help — эта справка\n\n"
            . "Бесплатно: 3 документа/мес · PRO: 299 руб./мес\n"
            . "НДС не облагается (422-ФЗ)";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    // ── /cancel ──

    private function handleCancel(int $chatId, int $telegramId): void
    {
        $stateRow = $this->db->getState($telegramId);
        if ($stateRow && $stateRow['state']) {
            $this->db->clearState($telegramId);
            $this->api->sendMessage($chatId, '❌ Действие отменено.');
        } else {
            $this->api->sendMessage($chatId, 'Нечего отменять.');
        }
        $user = $this->db->getUserByTelegramId($telegramId);
        if ($user && $user['full_name']) {
            $this->showMainMenu($chatId);
        }
    }

    // ── PDF cleanup ──

    public function cleanupOldPdfs(int $days = 30): int
    {
        $deleted = 0;
        $threshold = time() - ($days * 86400);
        $dir = $this->storageDir;

        if (!is_dir($dir)) return 0;

        foreach (glob($dir . '/*.pdf') as $file) {
            if (filemtime($file) < $threshold) {
                unlink($file);
                $deleted++;
            }
        }
        return $deleted;
    }

    public function getApi(): TelegramApi
    {
        return $this->api;
    }

    public function getDb(): Database
    {
        return $this->db;
    }

    // ── Consent ──

    private function handleAcceptConsent(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->db->createUser($telegramId);
        }
        $this->db->setConsentGiven($telegramId);

        $this->api->sendMessage($chatId,
            "✅ Спасибо! Согласие принято.\n\n"
            . "Для начала нужно заполнить ваши реквизиты.\n"
            . "Введите ваше ФИО полностью (например: Иванов Иван Иванович):"
        );
        $this->db->setState($telegramId, 'reg_full_name');
    }

    // ── /deletedata ──

    private function handleDeleteDataStart(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'У вас нет сохранённых данных.');
            return;
        }

        $text = "⚠️ Вы уверены? Будут удалены:\n"
            . "- Ваши реквизиты\n"
            . "- Все клиенты\n"
            . "- Все документы\n"
            . "- Сгенерированные PDF-файлы\n\n"
            . "Это действие необратимо.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🗑 Да, удалить всё', 'callback_data' => 'confirm_delete_account'],
                    ['text' => '❌ Отмена', 'callback_data' => 'main_menu'],
                ],
            ],
        ];
        $this->api->sendMessage($chatId, $text, $keyboard);
    }

    private function handleDeleteDataConfirm(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->api->sendMessage($chatId, 'У вас нет сохранённых данных.');
            return;
        }

        // Delete PDF files from disk
        $pdfPaths = $this->db->getUserPdfPaths($user['id']);
        foreach ($pdfPaths as $path) {
            $fullPath = $this->storageDir . '/' . basename($path);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        // Cascading delete from database
        $this->db->deleteUserData($user['id'], $telegramId);

        $this->api->sendMessage($chatId,
            '✅ Все ваши данные удалены. Для повторного использования бота нажмите /start.'
        );
    }
}
