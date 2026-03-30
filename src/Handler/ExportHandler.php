<?php

namespace DocBot\Handler;

use DocBot\Database;
use DocBot\TelegramApi;

class ExportHandler
{
    private Database $db;
    private TelegramApi $api;
    private string $csvDir;

    public function __construct(Database $db, TelegramApi $api, string $storageDir)
    {
        $this->db = $db;
        $this->api = $api;
        $this->csvDir = dirname($storageDir) . '/csv';
        if (!is_dir($this->csvDir)) {
            mkdir($this->csvDir, 0755, true);
        }
    }

    public function handleExport(int $chatId, int $telegramId): void
    {
        $user = $this->db->getUserByTelegramId($telegramId);
        if (!$user) {
            $this->api->sendMessage($chatId, 'Вы ещё не зарегистрированы. Нажмите /start');
            return;
        }

        $documents = $this->db->getDocumentsByUserId($user['id']);
        if (empty($documents)) {
            $this->api->sendMessage($chatId, 'У вас пока нет документов для экспорта.');
            return;
        }

        $filename = 'export_' . $telegramId . '_' . time() . '.csv';
        $filepath = $this->csvDir . '/' . $filename;

        $fp = fopen($filepath, 'w');
        // BOM for Excel UTF-8 compatibility
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, ['Номер', 'Тип', 'Клиент', 'Услуга', 'Сумма', 'Дата начала', 'Дата окончания', 'Создан'], ';');

        $typeLabels = ['contract' => 'Договор', 'act' => 'Акт'];
        foreach ($documents as $doc) {
            fputcsv($fp, [
                $doc['contract_number'],
                $typeLabels[$doc['doc_type']] ?? $doc['doc_type'],
                $doc['client_name'],
                $doc['service_description'] ?? '',
                $doc['amount'] ?? '',
                $doc['date_start'] ?? '',
                $doc['date_end'] ?? '',
                $doc['created_at'] ?? '',
            ], ';');
        }
        fclose($fp);

        $this->api->sendDocument($chatId, $filepath, 'Экспорт документов');
        $this->api->sendMessage($chatId, '✅ CSV-файл с вашими документами отправлен.');

        // Cleanup after sending
        @unlink($filepath);
    }
}
