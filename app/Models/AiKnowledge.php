<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class AiKnowledge extends BaseModel
{
    protected string $table = 'vc_ai_knowledge';

    public function listActive(int $limit = 100): array
    {
        $stmt = self::$db->prepare('
            SELECT id, title, slug, category, content, status, created_at, updated_at
            FROM `vc_ai_knowledge`
            WHERE status <> \'archived\'
            ORDER BY updated_at DESC, id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = self::$db->prepare('
            SELECT id, title, slug, category, content, status
            FROM `vc_ai_knowledge`
            WHERE id = :id AND status <> \'archived\'
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function publishedForContext(int $limit = 8): array
    {
        $stmt = self::$db->prepare('
            SELECT title, content
            FROM `vc_ai_knowledge`
            WHERE status = \'published\'
            ORDER BY published_at DESC, id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countPending(): int
    {
        $stmt = self::$db->query('SELECT COUNT(*) FROM `vc_ai_knowledge` WHERE status = \'pending\'');
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function insertKnowledge(array $data): int
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_knowledge`
                (title, slug, category, content, status, created_by, reviewed_by, published_at)
            VALUES
                (:title, :slug, :category, :content, :status, :created_by, :reviewed_by, :published_at)
        ');
        $stmt->execute($data);
        return (int) self::$db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = self::$db->prepare('
            UPDATE `vc_ai_knowledge`
            SET title = :title,
                slug = :slug,
                category = :category,
                content = :content,
                status = :status,
                reviewed_by = :reviewed_by,
                published_at = :published_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status <> \'archived\'
        ');
        return $stmt->execute([...$data, 'id' => $id]);
    }

    public function archive(int $id): bool
    {
        $stmt = self::$db->prepare('
            UPDATE `vc_ai_knowledge`
            SET status = \'archived\', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status <> \'archived\'
        ');
        return $stmt->execute(['id' => $id]) && $stmt->rowCount() === 1;
    }
}
