<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AiCustomerServicePolicyService;
use App\Services\AiCustomerServiceTypingPresenceService;
use App\Services\AiCustomerServiceWebsiteChatService;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class AiCustomerServiceWebsiteChatController
{
    private AiCustomerServicePolicyService $policies;
    private AiCustomerServiceWebsiteChatService $chat;
    private AiCustomerServiceTypingPresenceService $typing;

    public function __construct()
    {
        $this->policies = new AiCustomerServicePolicyService();
        $this->chat = new AiCustomerServiceWebsiteChatService();
        $this->typing = new AiCustomerServiceTypingPresenceService();
    }

    public function bootstrap(): void
    {
        try {
            if (!$this->policies->isWebsiteChatEnabled()) {
                $this->respond(['ok' => true, 'enabled' => false]);
            }
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $externalId = $this->conversationExternalId();
            $this->respond([
                'ok' => true,
                'enabled' => true,
                'csrf_token' => $_SESSION['csrf_token'],
                'messages' => $this->chat->recentMessages($externalId),
                'state' => $this->chat->stateForExternalId($externalId),
                'pending_messages' => $this->chat->unansweredMessageCount($externalId),
                'max_pending_messages' => AiCustomerServiceWebsiteChatService::MAX_UNANSWERED_MESSAGES,
            ]);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải cuộc trò chuyện.'], 500);
        }
    }

    public function messages(): void
    {
        try {
            if (!$this->policies->isWebsiteChatEnabled()) {
                $this->respond(['ok' => false, 'message' => 'Chat Website hiện đang tắt.'], 404);
            }
            $externalId = $this->conversationExternalId();
            $this->respond([
                'ok' => true,
                'messages' => $this->chat->recentMessages($externalId),
                'state' => $this->chat->stateForExternalId($externalId),
                'pending_messages' => $this->chat->unansweredMessageCount($externalId),
                'max_pending_messages' => AiCustomerServiceWebsiteChatService::MAX_UNANSWERED_MESSAGES,
            ]);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể đồng bộ cuộc trò chuyện.'], 500);
        }
    }

    public function typingStatus(): void
    {
        try {
            if (!$this->policies->isWebsiteChatEnabled()) {
                $this->respond(['ok' => false, 'message' => 'Chat Website hiện đang tắt.'], 404);
            }
            $this->respond([
                'ok' => true,
                ...$this->typing->websiteStatus($this->conversationExternalId()),
            ]);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể đồng bộ trạng thái đang soạn tin.'], 500);
        }
    }

    public function updateTypingStatus(): void
    {
        $input = $this->readJsonInput();
        if ($input === null || !is_bool($input['typing'] ?? null)) {
            $this->respond(['ok' => false, 'message' => 'Trạng thái đang soạn tin không hợp lệ.'], 422);
        }
        $csrfToken = $input['csrf_token'] ?? null;
        if (!is_string($csrfToken) || empty($_SESSION['csrf_token'])
            || !hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang.'], 419);
        }

        try {
            if (!$this->policies->isWebsiteChatEnabled()) {
                $this->respond(['ok' => false, 'message' => 'Chat Website hiện đang tắt.'], 404);
            }
            $externalId = $this->conversationExternalId();
            session_write_close();
            $this->typing->setCustomerTyping($externalId, $input['typing']);
            $this->respond(['ok' => true]);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể cập nhật trạng thái đang soạn tin.'], 500);
        }
    }

    public function sendMessage(): void
    {
        try {
            if (!$this->policies->isWebsiteChatEnabled()) {
                $this->respond(['ok' => false, 'message' => 'Chat Website hiện đang tắt.'], 404);
            }
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể xác minh trạng thái chat Website.'], 500);
        }
        $isMultipart = isset($_FILES['image']);
        $input = $isMultipart
            ? $_POST
            : $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung tin nhắn không hợp lệ.'], 400);
        }
        $csrfToken = $input['csrf_token'] ?? null;
        if (!is_string($csrfToken) || empty($_SESSION['csrf_token'])
            || !hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang.'], 419);
        }

        $message = $input['message'] ?? null;
        $messageId = $input['message_id'] ?? null;
        if ((!is_string($message) && !($isMultipart && $message === null))
            || !is_string($messageId)) {
            $this->respond(['ok' => false, 'message' => 'Nội dung tin nhắn không hợp lệ.'], 422);
        }
        $message = is_string($message) ? $message : '';
        $imageUpload = $isMultipart ? $_FILES['image'] : null;
        if ($isMultipart && (!is_array($imageUpload) || ($imageUpload['error'] ?? null) !== UPLOAD_ERR_OK)) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải ảnh lên. Vui lòng chọn ảnh khác.'], 422);
        }
        if (trim($message) === '' && $imageUpload === null) {
            $this->respond(['ok' => false, 'message' => 'Hãy nhập tin nhắn hoặc chọn ảnh để gửi.'], 422);
        }

        $now = time();
        $attempts = $_SESSION['ai_cs_web_chat_attempts'] ?? [];
        $attempts = is_array($attempts)
            ? array_values(array_filter($attempts, static fn($time): bool => is_int($time) && $time > $now - 60))
            : [];
        if (count($attempts) >= 5) {
            $this->respond(['ok' => false, 'message' => 'Bạn gửi tin quá nhanh. Vui lòng đợi một phút rồi thử lại.'], 429);
        }
        $attempts[] = $now;
        $_SESSION['ai_cs_web_chat_attempts'] = $attempts;
        $externalId = $this->conversationExternalId();
        $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        session_write_close();

        try {
            $result = $this->chat->receiveMessage(
                $externalId,
                $messageId,
                $message,
                $userId === false ? null : $userId,
                $imageUpload
            );
            $result['pending_messages'] = $this->chat->unansweredMessageCount($externalId);
            $result['max_pending_messages'] = AiCustomerServiceWebsiteChatService::MAX_UNANSWERED_MESSAGES;
            $this->respond(['ok' => true, ...$result]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (DomainException $exception) {
            $this->respond([
                'ok' => false,
                'message' => $exception->getMessage(),
                'pending_messages' => $this->chat->unansweredMessageCount($externalId),
                'max_pending_messages' => AiCustomerServiceWebsiteChatService::MAX_UNANSWERED_MESSAGES,
            ], 409);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể xử lý tin nhắn lúc này.'], 502);
        }
    }

    public function image(): void
    {
        $messageId = filter_var($_GET['message_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($messageId === false) {
            http_response_code(404);
            exit;
        }
        $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
        $externalId = $isAdmin ? null : $this->conversationExternalId();
        $path = $this->chat->imagePathForMessage((int) $messageId, $externalId);
        if ($path === null) {
            http_response_code(404);
            exit;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function conversationExternalId(): string
    {
        $externalId = $_SESSION['ai_cs_web_conversation'] ?? null;
        if (!is_string($externalId) || preg_match('/^[a-f0-9]{64}$/', $externalId) !== 1) {
            $externalId = bin2hex(random_bytes(32));
            $_SESSION['ai_cs_web_conversation'] = $externalId;
        }
        return $externalId;
    }

    private function readJsonInput(): ?array
    {
        $raw = file_get_contents('php://input', false, null, 0, 8193);
        if (!is_string($raw) || $raw === '' || strlen($raw) > 8192) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
