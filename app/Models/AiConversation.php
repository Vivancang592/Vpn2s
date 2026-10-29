<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class AiConversation extends BaseModel
{
    protected string $table = 'vc_ai_conversations';

    public function listInbox(string $channel, string $state, string $search, int $limit): array
    {
        $sql = $this->inboxSelect() . $this->inboxWhere() . '
            ORDER BY COALESCE(c.last_message_at, lm.created_at, c.created_at) DESC, c.id DESC
            LIMIT :limit
        ';
        $stmt = self::$db->prepare($sql);
        $this->bindInboxFilters($stmt, $channel, $state, $search);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countInbox(string $channel, string $state, string $search): int
    {
        $stmt = self::$db->prepare('
            SELECT COUNT(*) AS total
            FROM `vc_ai_conversations` c
            LEFT JOIN `vc_users` u ON u.id = c.user_id
            ' . $this->inboxWhere()
        );
        $this->bindInboxFilters($stmt, $channel, $state, $search);
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function dashboardOverview(): array
    {
        $totals = self::$db->query('
            SELECT COUNT(*) AS total,
                   SUM(channel = \'website\') AS website,
                   SUM(channel = \'facebook\') AS facebook,
                   SUM(state = \'ai_active\') AS ai_active,
                   SUM(state = \'staff_active\') AS human_handoff,
                   SUM(state = \'waiting\') AS waiting,
                   SUM(state = \'error\') AS errors,
                   SUM(state IN (\'waiting\', \'error\')) AS pending
            FROM `vc_ai_conversations`
        ')->fetch() ?: [];
        $aiHandled = (int) (self::$db->query('
            SELECT COUNT(DISTINCT conversation_id)
            FROM `vc_ai_messages`
            WHERE sender_type = \'ai\'
        ')->fetchColumn() ?: 0);
        $daily = self::$db->query('
            SELECT DATE(created_at) AS day, COUNT(*) AS total
            FROM `vc_ai_conversations`
            WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ')->fetchAll() ?: [];

        return [
            'total' => (int) ($totals['total'] ?? 0),
            'website' => (int) ($totals['website'] ?? 0),
            'facebook' => (int) ($totals['facebook'] ?? 0),
            'ai_active' => (int) ($totals['ai_active'] ?? 0),
            'human_handoff' => (int) ($totals['human_handoff'] ?? 0),
            'waiting' => (int) ($totals['waiting'] ?? 0),
            'errors' => (int) ($totals['errors'] ?? 0),
            'pending' => (int) ($totals['pending'] ?? 0),
            'ai_handled' => $aiHandled,
            'daily' => $daily,
        ];
    }

    public function findForUpdate(int $id): ?array
    {
        $stmt = self::$db->prepare('
            SELECT c.id, c.channel, c.state, c.external_id, c.guest_name, c.handoff_staff_id
            FROM `vc_ai_conversations` c
            WHERE c.id = :id
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->execute(['id' => $id]);
        $conversation = $stmt->fetch();
        return $conversation ?: null;
    }

    public function findOrCreateFacebookConversation(string $externalId): array
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_conversations` (channel, external_id, state)
            VALUES (\'facebook\', :external_id, \'ai_active\')
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ');
        $stmt->execute(['external_id' => $externalId]);
        $id = (int) self::$db->lastInsertId();
        $conversation = $this->findForUpdate($id);
        if ($conversation === null) {
            throw new \RuntimeException('Không thể tải phiên Facebook.');
        }
        return $conversation;
    }

    public function addFacebookMessage(int $conversationId, string $externalMessageId, string $content): void
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_messages` (conversation_id, sender_type, channel, external_message_id, content)
            VALUES (:conversation_id, \'user\', \'facebook\', :external_message_id, :content)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'external_message_id' => $externalMessageId,
            'content' => $content,
        ]);
        $this->touchConversation($conversationId);
    }

    public function activateFacebookConversationForAi(int $conversationId): void
    {
        $conversation = $this->findForUpdate($conversationId);
        if ($conversation === null || $conversation['channel'] !== 'facebook') {
            throw new \RuntimeException('Không thể tải hội thoại Facebook.');
        }
        if (!in_array($conversation['state'], ['waiting', 'error'], true)) {
            return;
        }
        if ($conversation['state'] === 'waiting' && $this->hasPendingCustomerHandoff($conversationId)) {
            return;
        }
        $this->updateState(
            $conversationId,
            (string) $conversation['state'],
            'ai_active',
            null,
            'system',
            'facebook_inbound_message'
        );
    }

    public function facebookConversationIdForMessage(string $externalMessageId): ?int
    {
        $stmt = self::$db->prepare('
            SELECT conversation_id
            FROM `vc_ai_messages`
            WHERE channel = \'facebook\' AND external_message_id = :external_message_id
            LIMIT 1
        ');
        $stmt->execute(['external_message_id' => $externalMessageId]);
        $conversationId = $stmt->fetchColumn();
        return $conversationId === false ? null : (int) $conversationId;
    }

    public function hasRecentFacebookInbound(int $conversationId): bool
    {
        $stmt = self::$db->prepare('
            SELECT 1
            FROM `vc_ai_messages`
            WHERE conversation_id = :conversation_id
              AND channel = \'facebook\'
              AND sender_type = \'user\'
              AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
            LIMIT 1
        ');
        $stmt->execute(['conversation_id' => $conversationId]);
        return $stmt->fetchColumn() !== false;
    }

    public function findForAdmin(int $id): ?array
    {
        $stmt = self::$db->prepare('
            SELECT c.id, c.channel, c.state, c.user_id, c.guest_name, c.created_at, u.username,
                   lm.content AS last_message, COALESCE(c.last_message_at, lm.created_at, c.created_at) AS activity_at
            FROM `vc_ai_conversations` c
            LEFT JOIN `vc_users` u ON u.id = c.user_id
            LEFT JOIN `vc_ai_messages` lm ON lm.id = (
                SELECT MAX(m.id) FROM `vc_ai_messages` m WHERE m.conversation_id = c.id
            )
            WHERE c.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $conversation = $stmt->fetch();
        return $conversation ?: null;
    }

    public function findOrCreateWebsiteConversation(string $externalId): array
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_conversations` (channel, external_id, state)
            VALUES (\'website\', :external_id, \'ai_active\')
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ');
        $stmt->execute(['external_id' => $externalId]);
        $id = (int) self::$db->lastInsertId();
        $conversation = $this->findForUpdate($id);
        if ($conversation === null) {
            throw new \RuntimeException('Không thể tải phiên chat Website.');
        }
        return $conversation;
    }

    public function associateWebsiteConversationWithUser(int $conversationId, int $userId): void
    {
        if ($conversationId < 1 || $userId < 1) {
            throw new \InvalidArgumentException('Tài khoản khách hàng không hợp lệ.');
        }
        $stmt = self::$db->prepare('
            UPDATE `vc_ai_conversations`
            SET user_id = :user_id
            WHERE id = :id AND channel = \'website\' AND user_id IS NULL
        ');
        $stmt->execute([
            'user_id' => $userId,
            'id' => $conversationId,
        ]);
    }

    public function findWebsiteConversation(string $externalId): ?array
    {
        $stmt = self::$db->prepare('
            SELECT id, channel, state
            FROM `vc_ai_conversations`
            WHERE channel = \'website\' AND external_id = :external_id
            LIMIT 1
        ');
        $stmt->execute(['external_id' => $externalId]);
        $conversation = $stmt->fetch();
        return $conversation ?: null;
    }

    public function externalMessageExists(string $channel, string $externalMessageId): bool
    {
        $stmt = self::$db->prepare('
            SELECT 1
            FROM `vc_ai_messages`
            WHERE channel = :channel AND external_message_id = :external_message_id
            LIMIT 1
        ');
        $stmt->execute([
            'channel' => $channel,
            'external_message_id' => $externalMessageId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function addWebsiteMessage(int $conversationId, string $sender, string $externalMessageId, string $content): void
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_messages` (conversation_id, sender_type, channel, external_message_id, content)
            VALUES (:conversation_id, :sender_type, \'website\', :external_message_id, :content)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_type' => $sender,
            'external_message_id' => $externalMessageId,
            'content' => $content,
        ]);
        $this->touchConversation($conversationId);
    }

    public function addAiMessage(
        int $conversationId,
        string $content,
        string $provider,
        int $responseMs,
        ?int $promptTokens,
        ?int $completionTokens,
        string $channel = 'website',
        ?string $externalMessageId = null
    ): void {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_messages`
                (conversation_id, sender_type, channel, external_message_id, content, provider, provider_response_ms, token_usage_prompt, token_usage_completion)
            VALUES
                (:conversation_id, \'ai\', :channel, :external_message_id, :content, :provider, :response_ms, :prompt_tokens, :completion_tokens)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'channel' => $channel,
            'external_message_id' => $externalMessageId,
            'content' => $content,
            'provider' => $provider,
            'response_ms' => $responseMs,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
        ]);
        $this->touchConversation($conversationId);
    }

    public function latestMessage(int $conversationId): ?array
    {
        $stmt = self::$db->prepare('
            SELECT id, sender_type, channel
            FROM `vc_ai_messages`
            WHERE conversation_id = :conversation_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['conversation_id' => $conversationId]);
        $message = $stmt->fetch();
        return $message ?: null;
    }

    public function recentWebsiteMessages(int $conversationId, int $limit = 20): array
    {
        $stmt = self::$db->prepare('
            SELECT id, sender_type, content, created_at
            FROM (
                SELECT id, sender_type, content, created_at
                FROM `vc_ai_messages`
                WHERE conversation_id = :conversation_id
                ORDER BY id DESC
                LIMIT :limit
            ) recent_messages
            ORDER BY id ASC
        ');
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countUnansweredWebsiteMessages(int $conversationId): int
    {
        $stmt = self::$db->prepare('
            SELECT COUNT(*)
            FROM `vc_ai_messages` customer_message
            WHERE customer_message.conversation_id = :conversation_id
              AND customer_message.channel = \'website\'
              AND customer_message.sender_type = \'user\'
              AND customer_message.id > COALESCE((
                    SELECT MAX(reply.id)
                    FROM `vc_ai_messages` reply
                    WHERE reply.conversation_id = :reply_conversation_id
                      AND reply.channel = \'website\'
                      AND reply.sender_type IN (\'ai\', \'staff\')
              ), 0)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'reply_conversation_id' => $conversationId,
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function websiteMessageExists(int $conversationId, string $externalMessageId): bool
    {
        $stmt = self::$db->prepare('
            SELECT 1
            FROM `vc_ai_messages`
            WHERE conversation_id = :conversation_id
              AND channel = \'website\'
              AND external_message_id = :external_message_id
            LIMIT 1
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'external_message_id' => $externalMessageId,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function websiteImagePathForMessage(int $messageId, ?string $externalId = null): ?string
    {
        $stmt = self::$db->prepare('
            SELECT m.content
            FROM `vc_ai_messages` m
            INNER JOIN `vc_ai_conversations` c ON c.id = m.conversation_id
            WHERE m.id = :message_id
              AND m.channel = \'website\'
              AND c.channel = \'website\'
              AND (:external_id_check IS NULL OR c.external_id = :external_id)
            LIMIT 1
        ');
        $stmt->execute([
            'message_id' => $messageId,
            'external_id_check' => $externalId,
            'external_id' => $externalId,
        ]);
        $content = $stmt->fetchColumn();
        if (!is_string($content)
            || preg_match('/\[\[vc-ai-image:([a-f0-9]{32}\.(?:jpg|png|webp|gif))\]\]/', $content, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function touchConversation(int $conversationId): void
    {
        $stmt = self::$db->prepare('
            UPDATE `vc_ai_conversations`
            SET last_message_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $stmt->execute(['id' => $conversationId]);
    }

    public function getMessages(int $conversationId): array
    {
        $stmt = self::$db->prepare('
            SELECT id, sender_type, content, created_at
            FROM (
                SELECT id, sender_type, content, created_at
                FROM `vc_ai_messages`
                WHERE conversation_id = :conversation_id
                ORDER BY id DESC
                LIMIT 200
            ) recent_messages
            ORDER BY id ASC
        ');
        $stmt->execute(['conversation_id' => $conversationId]);
        return $stmt->fetchAll() ?: [];
    }

    public function updateState(
        int $id,
        string $expectedState,
        string $newState,
        ?int $staffId,
        string $triggeredBy = 'system',
        ?string $triggerReason = null
    ): bool
    {
        $stmt = self::$db->prepare('
            UPDATE `vc_ai_conversations`
            SET state = :new_state, handoff_staff_id = :staff_id
            WHERE id = :id AND state = :expected_state
        ');
        $updated = $stmt->execute([
            'new_state' => $newState,
            'staff_id' => $staffId,
            'id' => $id,
            'expected_state' => $expectedState,
        ]) && $stmt->rowCount() === 1;
        if ($updated) {
            $this->recordStateTransition($id, $expectedState, $newState, $triggeredBy, $triggerReason, $staffId);
        }
        return $updated;
    }

    public function recordStateTransition(
        int $conversationId,
        ?string $fromState,
        string $toState,
        string $triggeredBy,
        ?string $triggerReason,
        ?int $staffId
    ): void {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_state_transitions`
                (conversation_id, from_state, to_state, triggered_by, trigger_reason, staff_id)
            VALUES
                (:conversation_id, :from_state, :to_state, :triggered_by, :trigger_reason, :staff_id)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'from_state' => $fromState,
            'to_state' => $toState,
            'triggered_by' => $triggeredBy,
            'trigger_reason' => $triggerReason,
            'staff_id' => $staffId,
        ]);
    }

    public function hasPendingCustomerHandoff(int $conversationId): bool
    {
        $stmt = self::$db->prepare('
            SELECT to_state, trigger_reason
            FROM `vc_ai_state_transitions`
            WHERE conversation_id = :conversation_id
            ORDER BY id DESC
            LIMIT 1
        ');
        $stmt->execute(['conversation_id' => $conversationId]);
        $transition = $stmt->fetch();
        return $transition !== false
            && $transition['to_state'] === 'waiting'
            && $transition['trigger_reason'] === 'customer_requests_human';
    }

    public function addHandoffAcknowledgment(int $conversationId, string $content): void
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_messages` (conversation_id, sender_type, channel, content)
            VALUES (:conversation_id, \'ai\', \'website\', :content)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'content' => $content,
        ]);
        $this->touchConversation($conversationId);
    }

    public function deleteConversation(int $conversationId): bool
    {
        if ($conversationId < 1) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }
        foreach (['vc_ai_external_ids', 'vc_ai_state_transitions', 'vc_ai_messages'] as $table) {
            $stmt = self::$db->prepare("DELETE FROM `$table` WHERE conversation_id = :conversation_id");
            $stmt->execute(['conversation_id' => $conversationId]);
        }
        $stmt = self::$db->prepare('DELETE FROM `vc_ai_conversations` WHERE id = :id');
        $stmt->execute(['id' => $conversationId]);
        return $stmt->rowCount() === 1;
    }

    public function inactiveConversationIds(string $cutoff, int $limit): array
    {
        $stmt = self::$db->prepare('
            SELECT c.id
            FROM `vc_ai_conversations` c
            WHERE c.created_at < :cutoff
              AND COALESCE(c.last_message_at, c.created_at) < :activity_cutoff
              AND NOT EXISTS (
                    SELECT 1
                    FROM `vc_ai_messages` m
                    WHERE m.conversation_id = c.id
                      AND m.created_at >= :message_cutoff
              )
            ORDER BY COALESCE(c.last_message_at, c.created_at) ASC, c.id ASC
            LIMIT :limit
        ');
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->bindValue(':activity_cutoff', $cutoff);
        $stmt->bindValue(':message_cutoff', $cutoff);
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'id'));
    }

    public function hasRecentActivityForRetention(int $conversationId, string $cutoff): bool
    {
        $stmt = self::$db->prepare('
            SELECT 1
            FROM `vc_ai_conversations` c
            WHERE c.id = :id
              AND (
                    c.created_at >= :created_cutoff
                    OR COALESCE(c.last_message_at, c.created_at) >= :activity_cutoff
                    OR EXISTS (
                        SELECT 1
                        FROM `vc_ai_messages` m
                        WHERE m.conversation_id = c.id
                          AND m.created_at >= :message_cutoff
                    )
              )
            LIMIT 1
        ');
        $stmt->execute([
            'id' => $conversationId,
            'created_cutoff' => $cutoff,
            'activity_cutoff' => $cutoff,
            'message_cutoff' => $cutoff,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    public function imageFileNamesForConversation(int $conversationId): array
    {
        $stmt = self::$db->prepare('
            SELECT content
            FROM `vc_ai_messages`
            WHERE conversation_id = :conversation_id
        ');
        $stmt->execute(['conversation_id' => $conversationId]);
        $fileNames = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $content) {
            if (!is_string($content)
                || preg_match_all('/\[\[vc-ai-image:([a-f0-9]{32}\.(?:jpg|png|webp|gif))\]\]/', $content, $matches) === false) {
                continue;
            }
            foreach ($matches[1] ?? [] as $fileName) {
                $fileNames[] = $fileName;
            }
        }
        return array_values(array_unique($fileNames));
    }

    public function addStaffMessage(
        int $conversationId,
        string $channel,
        int $staffId,
        string $content,
        ?string $externalMessageId = null
    ): void
    {
        $stmt = self::$db->prepare('
            INSERT INTO `vc_ai_messages` (conversation_id, sender_type, sender_id, channel, external_message_id, content)
            VALUES (:conversation_id, \'staff\', :sender_id, :channel, :external_message_id, :content)
        ');
        $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $staffId,
            'channel' => $channel,
            'external_message_id' => $externalMessageId,
            'content' => $content,
        ]);

        $update = self::$db->prepare('
            UPDATE `vc_ai_conversations`
            SET last_message_at = CURRENT_TIMESTAMP
            WHERE id = :id AND state = \'staff_active\'
        ');
        $update->execute(['id' => $conversationId]);
    }

    private function inboxSelect(): string
    {
        return '
            SELECT c.id, c.channel, c.state, c.external_id, c.user_id, c.guest_name, u.username,
                   lm.content AS last_message, COALESCE(c.last_message_at, lm.created_at, c.created_at) AS activity_at
            FROM `vc_ai_conversations` c
            LEFT JOIN `vc_users` u ON u.id = c.user_id
            LEFT JOIN `vc_ai_messages` lm ON lm.id = (
                SELECT MAX(m.id) FROM `vc_ai_messages` m WHERE m.conversation_id = c.id
            )
        ';
    }

    private function inboxWhere(): string
    {
        return '
            WHERE (:channel_filter = \'\' OR c.channel = :channel_value)
              AND (:state_filter = \'\' OR c.state = :state_value)
              AND (
                    :search_filter = \'\'
                    OR (
                        c.channel = \'website\'
                        AND c.user_id IS NOT NULL
                        AND (
                            LOCATE(LOWER(:search_username), LOWER(COALESCE(u.username, \'\'))) > 0
                            OR LOCATE(LOWER(:search_email), LOWER(COALESCE(u.email, \'\'))) > 0
                        )
                    )
              )
        ';
    }

    private function bindInboxFilters(\PDOStatement $stmt, string $channel, string $state, string $search): void
    {
        $stmt->bindValue(':channel_filter', $channel);
        $stmt->bindValue(':channel_value', $channel);
        $stmt->bindValue(':state_filter', $state);
        $stmt->bindValue(':state_value', $state);
        $stmt->bindValue(':search_filter', $search);
        $stmt->bindValue(':search_username', $search);
        $stmt->bindValue(':search_email', $search);
    }
}
