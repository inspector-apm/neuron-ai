<?php

declare(strict_types=1);

namespace NeuronAI\Chat\History;

use NeuronAI\Chat\Messages\Message;
use NeuronAI\Exceptions\ChatHistoryException;
use PDO;

/**
 *
 * CREATE TABLE chat_history (
 * id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 * thread_id VARCHAR(255) NOT NULL,
 * messages LONGTEXT NOT NULL,
 * created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 * updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 *
 * UNIQUE KEY uk_thread_id (thread_id),
 * INDEX idx_thread_id (thread_id)
 * );
 *
 */
class SQLChatHistory extends AbstractChatHistory
{
    protected string $table;

    public function __construct(
        protected string $thread_id,
        protected PDO $pdo,
        string $table = 'chat_history',
        int $contextWindow = 50000
    ) {
        parent::__construct($contextWindow);
        $this->table = $this->sanitizeTableName($table);
        $this->load();
    }

    protected function load(): void
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE thread_id = :thread_id");
        $stmt->execute(['thread_id' => $this->thread_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($history)) {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} (thread_id, messages) VALUES (:thread_id, :messages)");
            $stmt->execute([
                'thread_id' => $this->thread_id,
                'messages' => '[]'
            ]);
        } else {
            $this->history = $this->deserializeMessages(\json_decode((string) $history[0]['messages'], true));
        }
    }

    protected function storeMessage(Message $message): ChatHistoryInterface
    {
        $this->updateTable();
        return $this;
    }

    public function removeOldMessages(int $skipFrom): ChatHistoryInterface
    {
        $this->updateTable();
        return $this;
    }

    public function removeMessage(int $index): ChatHistoryInterface
    {
        $this->updateTable();
        return $this;
    }

    protected function clear(): ChatHistoryInterface
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE thread_id = :thread_id");
        $stmt->execute(['thread_id' => $this->thread_id]);
        return $this;
    }

    public function updateTable(): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET messages = :messages WHERE thread_id = :thread_id");
        $stmt->execute(['thread_id' => $this->thread_id, 'messages' => \json_encode($this->jsonSerialize())]);

        if ($stmt->rowCount() <= 0) {
            throw new ChatHistoryException("No rows were updated (thread_id {$this->thread_id} not found)");
        }
    }

    protected function sanitizeTableName(string $tableName): string
    {
        $tableName = \trim($tableName);

        // Whitelist validation
        if (!$this->tableExists($tableName)) {
            throw new ChatHistoryException('Table not allowed');
        }

        // Format validation as backup
        if (\in_array(\preg_match('/^[a-zA-Z_]\w*$/', $tableName), [0, false], true)) {
            throw new ChatHistoryException('Invalid table name format');
        }

        return $tableName;
    }

    protected function tableExists(string $tableName): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name");
        $stmt->execute(['table_name' => $tableName]);
        return $stmt->fetch() !== false;
    }
}
