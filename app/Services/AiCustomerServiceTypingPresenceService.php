<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiConversation;
use RuntimeException;

final class AiCustomerServiceTypingPresenceService
{
    private const ACTIVE_WINDOW_SECONDS = 3.0;

    private AiConversation $conversations;

    public function __construct()
    {
        $this->conversations = new AiConversation();
    }

    public function setCustomerTyping(string $externalId, bool $typing): void
    {
        $conversation = $this->conversations->findWebsiteConversation($externalId);
        if ($conversation === null) {
            return;
        }

        $this->setTyping((int) $conversation['id'], 'customer', $typing);
    }

    public function setStaffTyping(int $conversationId, bool $typing): void
    {
        $conversation = $this->conversations->findForAdmin($conversationId);
        if ($conversation === null || $conversation['channel'] !== 'website') {
            throw new \OutOfBoundsException('Không tìm thấy hội thoại Website.');
        }
        if ($typing && $conversation['state'] !== 'staff_active') {
            throw new \DomainException('Chỉ gửi trạng thái soạn tin khi kỹ thuật viên đang tiếp quản.');
        }

        $this->setTyping($conversationId, 'staff', $typing);
    }

    public function websiteStatus(string $externalId): array
    {
        $conversation = $this->conversations->findWebsiteConversation($externalId);
        if ($conversation === null) {
            return ['customer_typing' => false, 'staff_typing' => false];
        }

        return $this->status((int) $conversation['id']);
    }

    public function adminStatus(int $conversationId): array
    {
        $conversation = $this->conversations->findForAdmin($conversationId);
        if ($conversation === null || $conversation['channel'] !== 'website') {
            throw new \OutOfBoundsException('Không tìm thấy hội thoại Website.');
        }

        $status = $this->status($conversationId);
        if ($conversation['state'] !== 'staff_active') {
            $status['staff_typing'] = false;
        }
        return $status;
    }

    private function setTyping(int $conversationId, string $actor, bool $typing): void
    {
        $path = $this->presencePath($conversationId);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Không thể mở trạng thái gõ chat.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Không thể khóa trạng thái gõ chat.');
            }
            $state = $this->readState($handle);
            $state[$actor] = $typing ? microtime(true) : 0.0;
            $this->writeState($handle, $state);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function status(int $conversationId): array
    {
        $path = $this->presencePath($conversationId);
        if (!is_file($path)) {
            return ['customer_typing' => false, 'staff_typing' => false];
        }

        $handle = fopen($path, 'r+');
        if ($handle === false) {
            throw new RuntimeException('Không thể đọc trạng thái gõ chat.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Không thể khóa trạng thái gõ chat.');
            }
            $state = $this->readState($handle);
            $now = microtime(true);
            $status = [
                'customer_typing' => $this->isActive($state['customer'] ?? 0, $now),
                'staff_typing' => $this->isActive($state['staff'] ?? 0, $now),
            ];
            foreach (['customer', 'staff'] as $actor) {
                if (!$this->isActive($state[$actor] ?? 0, $now)) {
                    $state[$actor] = 0.0;
                }
            }
            $this->writeState($handle, $state);
            flock($handle, LOCK_UN);
            return $status;
        } finally {
            fclose($handle);
        }
    }

    private function isActive(mixed $timestamp, float $now): bool
    {
        return is_numeric($timestamp)
            && (float) $timestamp > 0
            && $now - (float) $timestamp <= self::ACTIVE_WINDOW_SECONDS;
    }

    private function readState($handle): array
    {
        if (!rewind($handle)) {
            throw new RuntimeException('Không thể đọc trạng thái gõ chat.');
        }
        $contents = stream_get_contents($handle);
        if ($contents === false) {
            throw new RuntimeException('Không thể đọc trạng thái gõ chat.');
        }
        if ($contents === '') {
            return ['customer' => 0.0, 'staff' => 0.0];
        }

        try {
            $state = json_decode($contents, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Dữ liệu trạng thái gõ chat không hợp lệ.', 0, $exception);
        }
        if (!is_array($state)) {
            throw new RuntimeException('Dữ liệu trạng thái gõ chat không hợp lệ.');
        }

        return [
            'customer' => $state['customer'] ?? 0.0,
            'staff' => $state['staff'] ?? 0.0,
        ];
    }

    private function writeState($handle, array $state): void
    {
        $contents = json_encode($state, JSON_THROW_ON_ERROR);
        if (!rewind($handle) || !ftruncate($handle, 0)
            || fwrite($handle, $contents) !== strlen($contents)
            || !fflush($handle)) {
            throw new RuntimeException('Không thể lưu trạng thái gõ chat.');
        }
    }

    private function presencePath(int $conversationId): string
    {
        if ($conversationId < 1) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }

        $directory = BASE_PATH . '/storage/ai-chat-typing';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Không thể tạo vùng lưu trạng thái gõ chat.');
        }
        if (is_link($directory) || !is_writable($directory)) {
            throw new RuntimeException('Vùng lưu trạng thái gõ chat không an toàn hoặc không thể ghi.');
        }

        return $directory . '/' . $conversationId . '.json';
    }
}
