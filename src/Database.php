<?php

namespace DocBot;

use PDO;
use PDOException;

class Database
{
    private PDO $pdo;

    public function __construct(string $host, string $dbName, string $user, string $password)
    {
        $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    // ── Users ──

    public function getUserByTelegramId(int $telegramId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE telegram_id = ?');
        $stmt->execute([$telegramId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createUser(int $telegramId): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO users (telegram_id) VALUES (?)');
        $stmt->execute([$telegramId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateUser(int $telegramId, array $fields): void
    {
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
        $cols = array_keys($data);
        $placeholders = array_fill(0, count($cols), '?');
        $sql = 'INSERT INTO clients (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
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
