<?php

namespace DocBot;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;
    private string $dsn;
    private string $user;
    private string $password;

    /** Белый список разрешённых колонок по таблицам */
    private const ALLOWED_COLUMNS = [
        'users' => [
            'full_name', 'inn', 'address', 'phone', 'email',
            'bank_name', 'account_number', 'bik', 'corr_account',
            'username',
        ],
        'clients' => [
            'user_id', 'client_type', 'full_name', 'inn', 'kpp', 'ogrn', 'ogrnip',
            'representative_title', 'representative_name', 'basis',
            'passport_series', 'passport_number', 'passport_issued',
            'address', 'phone', 'email', 'bank_details',
        ],
        'documents' => [
            'user_id', 'client_id', 'doc_type', 'contract_number',
            'contract_subtype', 'service_description', 'amount', 'amount_words',
            'date_start', 'date_end', 'city', 'payment_terms',
            'payment_days', 'payment_moment', 'acceptance_days',
            'termination_days', 'pdf_path', 'parent_contract_id',
        ],
    ];

    public function __construct(string $host, string $dbName, string $user, string $password)
    {
        $this->dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
        $this->user = $user;
        $this->password = $password;
        $this->connect();
    }

    private function connect(): void
    {
        $this->pdo = new PDO($this->dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Переподключение при потере соединения (MySQL server has gone away)
     */
    public function reconnectIfNeeded(): void
    {
        try {
            $this->pdo->query('SELECT 1');
        } catch (\PDOException $e) {
            $this->connect();
        }
    }

    /**
     * Фильтрует массив, оставляя только разрешённые колонки для таблицы.
     */
    private function filterColumns(array $data, string $table): array
    {
        $allowed = self::ALLOWED_COLUMNS[$table] ?? [];
        return array_filter(
            $data,
            fn(string $key) => in_array($key, $allowed, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    // ── Users ──

    public function getUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUser(int $telegramId, ?string $username = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (telegram_id, username) VALUES (?, ?)');
        $stmt->execute([$telegramId, $username]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateUsername(int $telegramId, ?string $username): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET username = ? WHERE telegram_id = ?');
        $stmt->execute([$username, $telegramId]);
    }

    public function getUserByUsername(string $username): ?array
    {
        $username = ltrim($username, '@');
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function grantSubscription(int $telegramId, int $months = 1): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET subscription_status = "paid", '
            . 'subscription_end = DATE_ADD(GREATEST(COALESCE(subscription_end, CURDATE()), CURDATE()), INTERVAL ? MONTH) '
            . 'WHERE telegram_id = ?'
        );
        $stmt->execute([$months, $telegramId]);
    }

    public function revokeSubscription(int $telegramId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET subscription_status = "free", subscription_end = NULL WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
    }

    public function isSubscriptionActive(array $user): bool
    {
        if (($user['subscription_status'] ?? 'free') !== 'paid') return false;
        if (empty($user['subscription_end'])) return true; // paid без даты = активна
        return $user['subscription_end'] >= date('Y-m-d');
    }

    public function updateUser(int $telegramId, array $fields): void
    {
        $fields = $this->filterColumns($fields, 'users');
        if (empty($fields)) return;

        $sets = [];
        $values = [];
        foreach ($fields as $col => $val) {
            $sets[] = "`{$col}` = ?";
            $values[] = $val;
        }
        $values[] = $telegramId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE telegram_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }

    // ── Clients ──

    public function getClientsByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE user_id = ? ORDER BY id DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getClientById(int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$clientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createClient(array $data): int
    {
        $data = $this->filterColumns($data, 'clients');
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = 'INSERT INTO clients (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteClient(int $clientId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM clients WHERE id = ?');
        $stmt->execute([$clientId]);
    }

    public function countDocumentsByClientId(int $clientId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE client_id = ?');
        $stmt->execute([$clientId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Documents ──

    public function getDocumentsByUserId(int $userId, string $docType = null): array
    {
        $sql = 'SELECT d.*, c.full_name AS client_name FROM documents d JOIN clients c ON d.client_id = c.id WHERE d.user_id = ?';
        $params = [$userId];
        if ($docType) {
            $sql .= ' AND d.doc_type = ?';
            $params[] = $docType;
        }
        $sql .= ' ORDER BY d.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDocumentById(int $docId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ?');
        $stmt->execute([$docId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createDocument(array $data): int
    {
        $data = $this->filterColumns($data, 'documents');
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = 'INSERT INTO documents (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    public function countDocumentsToday(int $userId, string $docType): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documents WHERE user_id = ? AND doc_type = ? AND DATE(created_at) = CURDATE()'
        );
        $stmt->execute([$userId, $docType]);
        return (int) $stmt->fetchColumn();
    }

    public function countDocumentsThisMonth(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM documents WHERE user_id = ? AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    // ── Contract expiry ──

    public function getExpiringContracts(int $daysAhead = 3): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.full_name AS client_name, u.telegram_id '
            . 'FROM documents d '
            . 'JOIN clients c ON d.client_id = c.id '
            . 'JOIN users u ON d.user_id = u.id '
            . 'WHERE d.doc_type = ? AND d.date_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)'
        );
        $stmt->execute(['contract', $daysAhead]);
        return $stmt->fetchAll();
    }

    public function getAllRegisteredTelegramIds(): array
    {
        $stmt = $this->pdo->query('SELECT telegram_id FROM users WHERE full_name IS NOT NULL AND full_name != ""');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    // ── Admin Stats ──

    public function getAdminStats(): array
    {
        $stats = [];

        // Общее количество пользователей
        $stats['total_users'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

        // Зарегистрированные (заполнили реквизиты)
        $stats['registered_users'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE full_name IS NOT NULL AND full_name != ""')->fetchColumn();

        // Новые за 7 дней
        $stats['new_users_7d'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();

        // Новые за 24 часа
        $stats['new_users_24h'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn();

        // Документы: сегодня
        $stats['docs_today'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents WHERE DATE(created_at) = CURDATE()')->fetchColumn();

        // Документы: за 7 дней
        $stats['docs_7d'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();

        // Документы: за 30 дней
        $stats['docs_30d'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)')->fetchColumn();

        // Документы: всего
        $stats['docs_total'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();

        // Договоры vs акты
        $stats['contracts_total'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents WHERE doc_type = "contract"')->fetchColumn();
        $stats['acts_total'] = (int) $this->pdo->query('SELECT COUNT(*) FROM documents WHERE doc_type = "act"')->fetchColumn();

        // Активные пользователи за 7 дней (создавали документы)
        $stats['active_7d'] = (int) $this->pdo->query('SELECT COUNT(DISTINCT user_id) FROM documents WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)')->fetchColumn();

        // Free vs Paid
        $stats['free_users'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE subscription_status = "free" AND full_name IS NOT NULL')->fetchColumn();
        $stats['paid_users'] = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE subscription_status = "paid"')->fetchColumn();

        // Клиентов всего
        $stats['clients_total'] = (int) $this->pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();

        // Общая сумма договоров
        $stats['total_amount'] = (float) ($this->pdo->query('SELECT COALESCE(SUM(amount), 0) FROM documents WHERE doc_type = "contract"')->fetchColumn());

        return $stats;
    }

    public function getTopUsers(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.full_name, u.telegram_id, COUNT(d.id) AS doc_count, '
            . 'COALESCE(SUM(d.amount), 0) AS total_amount '
            . 'FROM users u '
            . 'JOIN documents d ON d.user_id = u.id '
            . 'GROUP BY u.id '
            . 'ORDER BY doc_count DESC '
            . 'LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public function getDocsPerDay(int $days = 7): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS cnt '
            . 'FROM documents '
            . 'WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) '
            . 'GROUP BY DATE(created_at) '
            . 'ORDER BY day'
        );
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }

    // ── Consent & Account Deletion ──

    public function setConsentGiven(int $telegramId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET consent_given = 1 WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
    }

    public function getUserPdfPaths(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT pdf_path FROM documents WHERE user_id = ? AND pdf_path IS NOT NULL');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function deleteUserData(int $userId, int $telegramId): void
    {
        $this->pdo->prepare('DELETE FROM documents WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('DELETE FROM clients WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('DELETE FROM user_states WHERE telegram_id = ?')->execute([$telegramId]);
        $this->pdo->prepare('DELETE FROM users WHERE telegram_id = ?')->execute([$telegramId]);
    }

    // ── Document Shares ──

    public function getDocumentShareByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_shares WHERE share_token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createDocumentShare(int $documentId, int $senderTelegramId, string $token): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_shares (document_id, share_token, sender_telegram_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$documentId, $token, $senderTelegramId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateShareStatus(int $shareId, string $status): void
    {
        $extra = '';
        if ($status === 'viewed') {
            $extra = ', viewed_at = NOW()';
        } elseif ($status === 'confirmed') {
            $extra = ', confirmed_at = NOW()';
        }
        $stmt = $this->pdo->prepare("UPDATE document_shares SET status = ?{$extra} WHERE id = ?");
        $stmt->execute([$status, $shareId]);
    }

    public function updateShareRecipient(int $shareId, int $recipientTelegramId): void
    {
        $stmt = $this->pdo->prepare('UPDATE document_shares SET recipient_telegram_id = ? WHERE id = ?');
        $stmt->execute([$recipientTelegramId, $shareId]);
    }

    public function setShareComment(int $shareId, string $comment): void
    {
        $stmt = $this->pdo->prepare('UPDATE document_shares SET recipient_comment = ? WHERE id = ?');
        $stmt->execute([$comment, $shareId]);
    }

    public function getShareByDocumentId(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_shares WHERE document_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$documentId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getShareById(int $shareId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_shares WHERE id = ?');
        $stmt->execute([$shareId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getUserDocumentsWithShares(int $userId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, c.full_name AS client_name, '
            . 'ds.status AS share_status, ds.confirmed_at AS share_confirmed_at '
            . 'FROM documents d '
            . 'JOIN clients c ON d.client_id = c.id '
            . 'LEFT JOIN document_shares ds ON ds.document_id = d.id '
            . 'WHERE d.user_id = ? '
            . 'ORDER BY d.id DESC '
            . 'LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    // ── Profiles ──

    public function getProfileByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.full_name, u.inn, u.phone, u.telegram_id '
            . 'FROM profiles p JOIN users u ON p.user_id = u.id WHERE p.profile_token = ?'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getProfileByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createProfile(int $userId, string $token): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO profiles (user_id, profile_token) VALUES (?, ?)');
        $stmt->execute([$userId, $token]);
        return (int) $this->pdo->lastInsertId();
    }

    // ── Payments ──

    public function createPayment(int $telegramId, string $yookassaId, float $amount, int $months): int
    {
        $user = $this->getUserByTelegramId($telegramId);
        $userId = $user ? $user['id'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO payments (user_id, telegram_id, yookassa_payment_id, amount, months) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $telegramId, $yookassaId, $amount, $months]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePaymentStatus(string $yookassaId, string $status): void
    {
        $paidAt = $status === 'succeeded' ? date('Y-m-d H:i:s') : null;
        $stmt = $this->pdo->prepare(
            'UPDATE payments SET status = ?, paid_at = ? WHERE yookassa_payment_id = ?'
        );
        $stmt->execute([$status, $paidAt, $yookassaId]);
    }

    public function getPaymentByYookassaId(string $yookassaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payments WHERE yookassa_payment_id = ?');
        $stmt->execute([$yookassaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── User States ──

    public function getState(int $telegramId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_states WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function setState(int $telegramId, string $state, array $data = []): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_states (telegram_id, state, data) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), data = VALUES(data)'
        );
        $stmt->execute([$telegramId, $state, $json]);
    }

    public function clearState(int $telegramId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_states WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
    }
}
