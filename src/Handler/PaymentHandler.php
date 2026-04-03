<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;
use YooKassa\Client;

class PaymentHandler
{
    private Database $db;
    private TelegramApi $api;
    private string $shopId;
    private string $secretKey;
    private string $returnUrl;

    private const PLANS = [
        1 => ['amount' => 299, 'label' => '1 месяц — 299 руб.'],
        3 => ['amount' => 599, 'label' => '3 месяца — 599 руб. (экономия 38%)'],
        6 => ['amount' => 999, 'label' => '6 месяцев — 999 руб. (экономия 44%)'],
    ];

    public function __construct(Database $db, TelegramApi $api, array $config)
    {
        $this->db = $db;
        $this->api = $api;
        $this->shopId = $config['yookassa_shop_id'] ?? '';
        $this->secretKey = $config['yookassa_secret'] ?? '';
        $this->returnUrl = $config['yookassa_return_url'] ?? '';
    }

    public function showPlans(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        $isActive = $user ? $this->db->isSubscriptionActive($user) : false;
        $monthCount = $user ? $this->db->countDocumentsThisMonth($user['id']) : 0;

        $msg = "💳 <b>Подписка PRO</b>\n\n"
            . "Документов в этом месяце: {$monthCount}\n\n";

        if ($isActive) {
            $endDate = $user['subscription_end'] ?? 'бессрочно';
            $msg .= "✅ <b>Подписка PRO</b> — активна\n"
                . "Действует до: {$endDate}\n\n"
                . "Безлимитный доступ к генерации документов.\n\n"
                . "Вы можете продлить подписку:";
        } else {
            $remaining = max(0, 3 - $monthCount);
            $msg .= "Текущий план: Бесплатный\n"
                . "Осталось: {$remaining} из 3\n\n"
                . "Выберите тариф:\n\n"
                . "📦 1 месяц — 299 руб.\n"
                . "📦 3 месяца — 599 руб. (экономия 38%)\n"
                . "📦 6 месяцев — 999 руб. (экономия 44%)";
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '📦 1 мес — 299 ₽', 'callback_data' => 'pay_1']],
                [['text' => '📦 3 мес — 599 ₽', 'callback_data' => 'pay_3']],
                [['text' => '📦 6 мес — 999 ₽', 'callback_data' => 'pay_6']],
                [['text' => '« Назад в меню', 'callback_data' => 'main_menu']],
            ],
        ];
        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    public function createPayment(int $chatId, int $telegramId, int $months): void
    {
        $plan = self::PLANS[$months] ?? null;
        if (!$plan) {
            $this->api->sendMessage($chatId, '❌ Неизвестный тариф.');
            return;
        }

        $amount = $plan['amount'];

        try {
            $client = new Client();
            $client->setAuth($this->shopId, $this->secretKey);

            $payment = $client->createPayment([
                'amount' => [
                    'value' => $amount . '.00',
                    'currency' => 'RUB',
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'locale' => 'ru_RU',
                    'return_url' => $this->returnUrl,
                ],
                'capture' => true,
                'description' => "Подписка DocBot PRO на {$months} мес.",
                'metadata' => [
                    'telegram_id' => $telegramId,
                    'months' => $months,
                    'type' => 'subscription',
                ],
            ], uniqid('', true));

            $confirmationUrl = $payment->getConfirmation()->getConfirmationUrl();
            $yookassaId = $payment->getId();

            $this->db->createPayment($telegramId, $yookassaId, (float) $amount, $months);

            $msg = "💳 <b>Оплата подписки PRO</b>\n\n"
                . "Тариф: {$months} мес. — {$amount} руб.\n\n"
                . "Нажмите кнопку ниже для оплаты:";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "💳 Оплатить {$amount} руб.", 'url' => $confirmationUrl]],
                    [['text' => '📋 Главное меню', 'callback_data' => 'main_menu']],
                ],
            ];
            $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
        } catch (\Throwable $e) {
            error_log('[DocBot] Payment creation error: ' . $e->getMessage());
            $this->api->sendMessage($chatId,
                "❌ Ошибка при создании платежа. Попробуйте позже или обратитесь в поддержку."
            );
        }
    }

    public function handleWebhook(array $data): void
    {
        $event = $data['event'] ?? '';
        if ($event !== 'payment.succeeded') {
            return;
        }

        $paymentData = $data['object'] ?? [];
        $paymentId = $paymentData['id'] ?? '';

        if (!$paymentId) {
            return;
        }

        // Verify payment via YooKassa API (security: don't trust webhook body alone)
        try {
            $client = new Client();
            $client->setAuth($this->shopId, $this->secretKey);
            $payment = $client->getPaymentInfo($paymentId);
        } catch (\Throwable $e) {
            error_log('[DocBot] Payment verification error: ' . $e->getMessage());
            return;
        }

        if ($payment->getStatus() !== 'succeeded') {
            error_log("[DocBot] Payment {$paymentId} status is not succeeded: " . $payment->getStatus());
            return;
        }

        $metadata = $payment->getMetadata();
        $telegramId = (int) ($metadata['telegram_id'] ?? 0);
        $months = (int) ($metadata['months'] ?? 0);

        if (!$telegramId || !$months) {
            error_log("[DocBot] Payment {$paymentId} missing metadata");
            return;
        }

        // Check if already processed
        $existing = $this->db->getPaymentByYookassaId($paymentId);
        if ($existing && $existing['status'] === 'succeeded') {
            return;
        }

        $this->db->updatePaymentStatus($paymentId, 'succeeded');
        $this->db->grantSubscription($telegramId, $months);

        $this->api->sendMessage(
            $telegramId,
            "🎉 Оплата получена! Подписка PRO активирована на {$months} мес."
        );
    }
}
