<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;

class AdminHandler
{
    private Database $db;
    private TelegramApi $api;
    private int $adminId;

    public function __construct(Database $db, TelegramApi $api, int $adminId)
    {
        $this->db = $db;
        $this->api = $api;
        $this->adminId = $adminId;
    }

    public function isAdmin(int $telegramId): bool
    {
        return $telegramId === $this->adminId;
    }

    /**
     * /admin — полная статистика бота
     */
    public function handleAdmin(int $chatId, int $telegramId): void
    {
        if (!$this->isAdmin($telegramId)) {
            $this->api->sendMessage($chatId, 'Команда доступна только администратору.');
            return;
        }

        $stats = $this->db->getAdminStats();
        $top = $this->db->getTopUsers(5);
        $daily = $this->db->getDocsPerDay(7);

        $msg = "📊 <b>Статистика DocBot</b>\n\n";

        // Пользователи
        $msg .= "👥 <b>Пользователи</b>\n";
        $msg .= "Всего: {$stats['total_users']}\n";
        $msg .= "Зарегистрированных: {$stats['registered_users']}\n";
        $msg .= "Новых за 24ч: {$stats['new_users_24h']}\n";
        $msg .= "Новых за 7 дней: {$stats['new_users_7d']}\n";
        $msg .= "Активных за 7 дней: {$stats['active_7d']}\n";
        $msg .= "Free: {$stats['free_users']} | Paid: {$stats['paid_users']}\n\n";

        // Документы
        $msg .= "📄 <b>Документы</b>\n";
        $msg .= "Сегодня: {$stats['docs_today']}\n";
        $msg .= "За 7 дней: {$stats['docs_7d']}\n";
        $msg .= "За 30 дней: {$stats['docs_30d']}\n";
        $msg .= "Всего: {$stats['docs_total']}\n";
        $msg .= "Договоров: {$stats['contracts_total']} | Актов: {$stats['acts_total']}\n\n";

        // Финансы
        $msg .= "💰 <b>Финансы</b>\n";
        $totalFormatted = number_format($stats['total_amount'], 0, '.', ' ');
        $msg .= "Сумма договоров: {$totalFormatted} руб.\n";
        $msg .= "Клиентов: {$stats['clients_total']}\n\n";

        // Документы по дням
        if (!empty($daily)) {
            $msg .= "📈 <b>Документы по дням</b>\n";
            foreach ($daily as $row) {
                $day = date('d.m', strtotime($row['day']));
                $bar = str_repeat('█', min((int) $row['cnt'], 20));
                $msg .= "{$day}: {$bar} {$row['cnt']}\n";
            }
            $msg .= "\n";
        }

        // Топ пользователей
        if (!empty($top)) {
            $msg .= "🏆 <b>Топ-5 пользователей</b>\n";
            foreach ($top as $i => $u) {
                $name = $u['full_name'] ?: 'ID ' . $u['telegram_id'];
                $sum = number_format((float) $u['total_amount'], 0, '.', ' ');
                $msg .= ($i + 1) . ". {$name} — {$u['doc_count']} док. ({$sum} руб.)\n";
            }
        }

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔄 Обновить', 'callback_data' => 'admin_refresh']],
            ],
        ];

        $this->api->sendMessage($chatId, $msg, $keyboard, 'HTML');
    }

    /**
     * Формирует текст ежедневного отчёта (для cron)
     */
    public function buildDailyReport(): string
    {
        $stats = $this->db->getAdminStats();
        $daily = $this->db->getDocsPerDay(7);

        $msg = "📋 <b>Ежедневный отчёт DocBot</b>\n";
        $msg .= date('d.m.Y H:i') . "\n\n";

        $msg .= "👥 Пользователей: {$stats['registered_users']} (новых за 24ч: {$stats['new_users_24h']})\n";
        $msg .= "📄 Документов сегодня: {$stats['docs_today']}\n";
        $msg .= "📄 За 7 дней: {$stats['docs_7d']}\n";
        $msg .= "📄 За 30 дней: {$stats['docs_30d']}\n";
        $msg .= "📄 Всего: {$stats['docs_total']}\n";
        $msg .= "🔥 Активных за 7 дней: {$stats['active_7d']}\n";

        $totalFormatted = number_format($stats['total_amount'], 0, '.', ' ');
        $msg .= "💰 Сумма договоров: {$totalFormatted} руб.\n\n";

        if (!empty($daily)) {
            $msg .= "📈 <b>По дням:</b>\n";
            foreach ($daily as $row) {
                $day = date('d.m', strtotime($row['day']));
                $bar = str_repeat('█', min((int) $row['cnt'], 20));
                $msg .= "{$day}: {$bar} {$row['cnt']}\n";
            }
        }

        return $msg;
    }

    /**
     * Отправляет ежедневный отчёт администратору
     */
    public function sendDailyReport(): void
    {
        $msg = $this->buildDailyReport();
        $this->api->sendMessage($this->adminId, $msg, null, 'HTML');
    }

    public function getAdminId(): int
    {
        return $this->adminId;
    }
}
