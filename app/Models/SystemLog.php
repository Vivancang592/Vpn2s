<?php

namespace App\Models;

class SystemLog extends BaseModel
{
    protected string $table = 'vc_system_logs';

    /**
     * Ghi mới một dòng nhật ký vào CSDL
     */
    public function create(array $data): bool
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = self::$db->prepare("INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})");
        $executed = $stmt->execute(array_values($data));
        if ($executed && (($data['action'] ?? null) === 'AI Customer Service')) {
            $this->pruneAiCustomerServiceLogs(60);
        }
        return $executed;
    }

    /**
     * Lấy nhật ký hệ thống kèm thông tin người thực hiện (hỗ trợ phân trang).
     */
    public function allWithUser(?int $limit = null, int $offset = 0): array
    {
        $sql = "
            SELECT sl.*, u.username
            FROM `{$this->table}` sl
            LEFT JOIN `vc_users` u ON sl.user_id = u.id
            ORDER BY sl.id DESC
        ";
        $paginated = $limit !== null && $limit > 0;
        if ($paginated) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }

        $stmt = self::$db->prepare($sql);
        if ($paginated) {
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function recentAiCustomerService(int $limit = 20, int $offset = 0): array
    {
        $stmt = self::$db->prepare('
            SELECT sl.action, sl.description, sl.created_at
            FROM `vc_system_logs` sl
            WHERE sl.action = \'AI Customer Service\'
            ORDER BY sl.id DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', max(1, min($limit, 60)), \PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countAiCustomerServiceLogs(): int
    {
        $stmt = self::$db->query("SELECT COUNT(*) FROM `{$this->table}` WHERE action = 'AI Customer Service'");
        return (int) ($stmt !== false ? $stmt->fetchColumn() : 0);
    }

    public function pruneAiCustomerServiceLogs(int $maxEntries = 60): int
    {
        if ($maxEntries < 1) {
            throw new \InvalidArgumentException('Số lượng sự kiện tối đa cho nhật ký AI phải lớn hơn 0.');
        }

        $count = $this->countAiCustomerServiceLogs();
        if ($count <= $maxEntries) {
            return 0;
        }

        $excess = $count - $maxEntries;
        $idsStmt = self::$db->prepare(
            "SELECT id FROM `{$this->table}` WHERE action = 'AI Customer Service' ORDER BY id ASC LIMIT :excess"
        );
        $idsStmt->bindValue(':excess', $excess, \PDO::PARAM_INT);
        $idsStmt->execute();
        $ids = array_map('intval', $idsStmt->fetchAll(\PDO::FETCH_COLUMN, 0) ?: []);
        if ($ids === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $deleteStmt = self::$db->prepare("DELETE FROM `{$this->table}` WHERE id IN ({$placeholders})");
        $deleteStmt->execute($ids);
        return $deleteStmt->rowCount();
    }
}