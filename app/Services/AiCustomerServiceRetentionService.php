<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiConversation;
use App\Models\BaseModel;
use PDOException;
use RuntimeException;

final class AiCustomerServiceRetentionService
{
    private AiConversation $conversations;
    private AiCustomerServicePolicyService $policies;
    private AiCustomerServiceAuditService $audit;

    public function __construct()
    {
        $this->conversations = new AiConversation();
        $this->policies = new AiCustomerServicePolicyService();
        $this->audit = new AiCustomerServiceAuditService();
    }

    public function cleanupInactiveConversations(int $days = 7, int $limit = 100): int
    {
        if ($days < 1 || $days > 3650) {
            throw new \InvalidArgumentException('Thời hạn lưu hội thoại không hợp lệ.');
        }
        if (!$this->policies->retentionEnabled()) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        $conversationIds = $this->conversations->inactiveConversationIds($cutoff, $limit);
        $deleted = 0;
        $pdo = BaseModel::getPdo();

        foreach ($conversationIds as $conversationId) {
            $imageFileNames = [];
            try {
                $pdo->beginTransaction();
                if ($this->conversations->findForUpdate($conversationId) === null
                    || $this->conversations->hasRecentActivityForRetention($conversationId, $cutoff)) {
                    $pdo->commit();
                    continue;
                }

                $imageFileNames = $this->conversations->imageFileNamesForConversation($conversationId);
                if (!$this->conversations->deleteConversation($conversationId)) {
                    throw new RuntimeException('Không thể xóa hội thoại hết hạn lưu trữ.');
                }
                $this->audit->record(null, 'conversation_retention_deleted');
                $pdo->commit();
                $deleted++;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw new RuntimeException('Không thể dọn hội thoại hết hạn lưu trữ.', 0, $exception);
            } catch (RuntimeException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            $this->removePrivateImages($imageFileNames);
        }

        return $deleted;
    }

    private function removePrivateImages(array $fileNames): void
    {
        $directory = BASE_PATH . '/storage/ai-chat';
        foreach ($fileNames as $fileName) {
            if (!is_string($fileName)
                || preg_match('/^[a-f0-9]{32}\.(?:jpg|png|webp|gif)$/', $fileName) !== 1) {
                continue;
            }
            $path = $directory . '/' . $fileName;
            if (is_file($path) && !unlink($path)) {
                error_log('AI chat retention could not remove a private attachment.');
            }
        }
    }
}
