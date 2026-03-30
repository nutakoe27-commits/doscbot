<?php

namespace DocBot;

use DocBot\Handler\RegistrationHandler;
use DocBot\Handler\ContractHandler;
use DocBot\Handler\ActHandler;
use DocBot\Handler\ClientHandler;
use DocBot\Handler\ExportHandler;

class BotHandler
{
    private Database $db;
    private PdfGenerator $pdf;
    private TelegramApi $api;
    private string $storageDir;

    private RegistrationHandler $registrationHandler;
    private ContractHandler $contractHandler;
    private ActHandler $actHandler;
    private ClientHandler $clientHandler;
    private ExportHandler $exportHandler;

    public function __construct(array $config)
    {
        $this->storageDir = rtrim($config['pdf_storage'], '/');
        $sslVerify = $config['ssl_verify'] ?? false;
        $this->db = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_password']);
        $this->pdf = new PdfGenerator(__DIR__ . '/../templates', $this->storageDir);
        $this->api = new TelegramApi($config['bot_token'], $sslVerify);

        $this->registrationHandler = new RegistrationHandler($this->db, $this->api);
        $this->contractHandler = new ContractHandler($this->db, $this->api, $this->pdf);
        $this->actHandler = new ActHandler($this->db, $this->api, $this->pdf);
        $this->clientHandler = new ClientHandler($this->db, $this->api);
        $this->exportHandler = new ExportHandler($this->db, $this->api, $this->storageDir);
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
            $this->handleStart($chatId, $telegramId);
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

        if (!$user) {
            $this->db->createUser($telegramId);
        }

        $this->api->sendMessage($chatId,
            "Добро пожаловать в DocBot! 📄\n\n" .
            "Я помогу вам генерировать договоры и акты для самозанятых.\n\n" .
            "Для начала нужно заполнить ваши реквизиты.\n" .
            "Введите ваше ФИО полностью (например: Иванов Иван Иванович):"
        );
        $this->db->setState($telegramId, 'reg_full_name');
    }

    // ── Main menu ──

    public function showMainMenu(int $chatId): void
    {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📄 Создать договор', 'callback_data' => 'new_contract']],
                [['text' => '📋 Создать акт', 'callback_data' => 'new_act']],
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

        $this->api->answerCallback($callbackId);

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

        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user || !$user['full_name']) {
            $this->api->sendMessage($chatId, 'Сначала завершите регистрацию. Нажмите /start');
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

        // New contract
        if ($data === 'new_contract') {
            $this->contractHandler->startContractFlow($chatId, $telegramId, $user);
            return;
        }

        // New act
        if ($data === 'new_act') {
            $this->actHandler->startActFlow($chatId, $telegramId, $user);
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

        // Subscription info
        if ($data === 'subscription_info') {
            $this->showSubscriptionInfo($chatId, $user);
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
                // After registration completes, show menu
                $newState = $this->db->getState($telegramId);
                if (!$newState || !$newState['state']) {
                    $this->showMainMenu($chatId);
                }
                return;
            }
        }

        // Edit field
        if ($state === 'edit_field') {
            if ($this->registrationHandler->handleEditField($chatId, $telegramId, $text, $data)) {
                $this->showMainMenu($chatId);
                return;
            }
        }

        // New client flow
        if (str_starts_with($state, 'new_client_')) {
            if ($this->clientHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                // After client saved outside contract flow, show menu
                $newState = $this->db->getState($telegramId);
                if (!$newState || !$newState['state']) {
                    $this->showMainMenu($chatId);
                }
                return;
            }
        }

        // Contract flow
        if (str_starts_with($state, 'contract_')) {
            if ($this->contractHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                // After contract generation, show menu
                $newState = $this->db->getState($telegramId);
                if (!$newState || !$newState['state']) {
                    $this->showMainMenu($chatId);
                }
                return;
            }
        }

        // Act flow
        if ($state === 'act_date') {
            if ($this->actHandler->handleStateInput($chatId, $telegramId, $text, $state, $data)) {
                $newState = $this->db->getState($telegramId);
                if (!$newState || !$newState['state']) {
                    $this->showMainMenu($chatId);
                }
                return;
            }
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
            . "📄 <b>Договор оказания услуг</b> — создайте договор с клиентом, выберите тип заказчика (физлицо, ИП, юрлицо), укажите описание услуги и сумму.\n\n"
            . "📋 <b>Акт сдачи-приёмки</b> — создайте акт к существующему договору.\n\n"
            . "<b>Команды:</b>\n"
            . "/start — начать работу / главное меню\n"
            . "/menu — главное меню\n"
            . "/mydata — посмотреть/изменить свои реквизиты\n"
            . "/export — экспорт документов в CSV\n"
            . "/cancel — отменить текущее действие\n"
            . "/help — эта справка\n\n"
            . "Бесплатный план: до 3 документов в месяц.\n"
            . "НДС не облагается (422-ФЗ).\n"
            . "Обмен сканами через Telegram имеет юридическую силу (п. 7.3 договора).";
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

    // ── Subscription info ──

    private function showSubscriptionInfo(int $chatId, array $user): void
    {
        $status = ($user['subscription_status'] ?? 'free') === 'paid' ? 'Платная' : 'Бесплатная';
        $monthCount = $this->db->countDocumentsThisMonth($user['id']);

        $msg = "💳 <b>Подписка</b>\n\n"
            . "Текущий план: {$status}\n"
            . "Документов в этом месяце: {$monthCount}\n\n";

        if (($user['subscription_status'] ?? 'free') === 'free') {
            $msg .= "Бесплатный план: до 3 документов в месяц.\n\n"
                . "Для безлимитного доступа оформите подписку.\n"
                . "По вопросам подписки обращайтесь к администратору.";
        } else {
            $msg .= "У вас безлимитный доступ к генерации документов.";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
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
}
