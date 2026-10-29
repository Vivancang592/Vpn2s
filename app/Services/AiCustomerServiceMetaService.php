<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiConversation;
use App\Models\BaseModel;
use App\Models\Setting;
use DomainException;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class AiCustomerServiceMetaService
{
    private Setting $settings;
    private AiCustomerServiceSecretVault $vault;
    private AiConversation $conversations;
    private AiCustomerServiceAuditService $audit;

    public function __construct()
    {
        $this->settings = new Setting();
        $this->vault = new AiCustomerServiceSecretVault();
        $this->conversations = new AiConversation();
        $this->audit = new AiCustomerServiceAuditService();
    }

    public function settingsForAdmin(): array
    {
        return [
            'meta_app_id' => $this->settings->getByKey('ai_cs_meta_app_id') ?? '',
            'meta_page_id' => $this->settings->getByKey('ai_cs_meta_page_id') ?? '',
            'meta_app_secret_configured' => $this->isConfigured('ai_cs_meta_app_secret_encrypted'),
            'verify_token_configured' => $this->isConfigured('ai_cs_meta_verify_token_encrypted'),
            'page_access_token_configured' => $this->isConfigured('ai_cs_meta_page_access_token_encrypted'),
            'secret_storage_ready' => $this->vault->isReady(),
        ];
    }

    public function saveConfiguration(array $input, int $adminId): array
    {
        $appId = $input['meta_app_id'] ?? '';
        $pageId = $input['meta_page_id'] ?? '';
        $appSecret = $input['meta_app_secret'] ?? '';
        $verifyToken = $input['verify_token'] ?? '';
        $pageToken = $input['page_access_token'] ?? '';
        foreach ([$appId, $pageId, $appSecret, $verifyToken, $pageToken] as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Định dạng cấu hình Meta không hợp lệ.');
            }
        }

        $appId = trim($appId);
        $pageId = trim($pageId);
        $appSecret = trim($appSecret);
        $verifyToken = trim($verifyToken);
        $pageToken = trim($pageToken);
        if ($appId !== '' && preg_match('/^[0-9]{5,24}$/', $appId) !== 1) {
            throw new InvalidArgumentException('Meta App ID không hợp lệ.');
        }
        if ($pageId !== '' && preg_match('/^[0-9]{5,32}$/', $pageId) !== 1) {
            throw new InvalidArgumentException('Page ID không hợp lệ.');
        }
        if ($appSecret !== '' && preg_match('/^[a-f0-9]{32}$/i', $appSecret) !== 1) {
            throw new InvalidArgumentException('Meta App Secret không đúng định dạng.');
        }
        if ($verifyToken !== '' && preg_match('/^[A-Za-z0-9._~-]{16,128}$/', $verifyToken) !== 1) {
            throw new InvalidArgumentException('Verify Token phải dài từ 16 đến 128 ký tự an toàn.');
        }
        if ($pageToken !== '' && (strlen($pageToken) > 4096 || preg_match('/[\x00-\x20\x7f]/', $pageToken) === 1)) {
            throw new InvalidArgumentException('Page Access Token không hợp lệ.');
        }

        $encrypted = [];
        foreach ([
            'ai_cs_meta_app_secret_encrypted' => $appSecret,
            'ai_cs_meta_verify_token_encrypted' => $verifyToken,
            'ai_cs_meta_page_access_token_encrypted' => $pageToken,
        ] as $key => $secret) {
            if ($secret !== '') {
                $encrypted[$key] = $this->vault->encrypt($secret);
            }
        }

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            $this->settings->setByKey('ai_cs_meta_app_id', $appId);
            $this->settings->setByKey('ai_cs_meta_page_id', $pageId);
            foreach ($encrypted as $key => $value) {
                $this->settings->setByKey($key, $value);
            }
            $this->audit->record($adminId, 'meta_saved');
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể lưu cấu hình Meta.', 0, $exception);
        }
        return $this->settingsForAdmin();
    }

    public function verifyWebhookHandshake(?string $mode, ?string $verifyToken, ?string $challenge): string
    {
        $savedToken = $this->vault->decrypt($this->settings->getByKey('ai_cs_meta_verify_token_encrypted'));
        if ($savedToken === '') {
            throw new RuntimeException('Webhook Meta chưa được cấu hình Verify Token.');
        }
        if ($mode !== 'subscribe' || !is_string($verifyToken) || !hash_equals($savedToken, $verifyToken)
            || !is_string($challenge) || preg_match('/^[0-9]{1,2048}$/', $challenge) !== 1) {
            throw new DomainException('Yêu cầu xác minh webhook không hợp lệ.');
        }
        return $challenge;
    }

    public static function signatureIsValid(string $rawBody, string $signature, string $appSecret): bool
    {
        if (preg_match('/^sha256=[a-f0-9]{64}$/i', $signature) !== 1) {
            return false;
        }
        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $appSecret), strtolower($signature));
    }

    public function receiveWebhook(string $rawBody, string $signature): array
    {
        if ($rawBody === '' || strlen($rawBody) > 1048576) {
            throw new InvalidArgumentException('Payload webhook không hợp lệ.');
        }
        $appSecret = $this->vault->decrypt($this->settings->getByKey('ai_cs_meta_app_secret_encrypted'));
        $pageId = $this->settings->getByKey('ai_cs_meta_page_id') ?? '';
        if ($appSecret === '' || $pageId === '') {
            throw new RuntimeException('Webhook Meta chưa được cấu hình đầy đủ.');
        }
        if (!self::signatureIsValid($rawBody, $signature, $appSecret)) {
            throw new DomainException('Chữ ký webhook không hợp lệ.');
        }

        try {
            $payload = json_decode($rawBody, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Payload webhook không phải JSON hợp lệ.');
        }
        if (!is_array($payload) || ($payload['object'] ?? null) !== 'page'
            || !is_array($payload['entry'] ?? null) || count($payload['entry']) > 100) {
            throw new InvalidArgumentException('Payload webhook không đúng cấu trúc Page.');
        }

        $accepted = 0;
        $duplicates = 0;
        $eventCount = 0;
        foreach ($payload['entry'] as $entry) {
            if (!is_array($entry) || !is_array($entry['messaging'] ?? null)) {
                continue;
            }
            if (isset($entry['id']) && (string) $entry['id'] !== $pageId) {
                continue;
            }
            foreach ($entry['messaging'] as $event) {
                if (++$eventCount > 100) {
                    throw new InvalidArgumentException('Webhook chứa quá nhiều sự kiện.');
                }
                $message = is_array($event) ? ($event['message'] ?? null) : null;
                $senderId = is_array($event) ? ($event['sender']['id'] ?? null) : null;
                $recipientId = is_array($event) ? ($event['recipient']['id'] ?? null) : null;
                if (!is_array($message) || !empty($message['is_echo'])
                    || !is_string($senderId) || preg_match('/^[0-9]{1,64}$/', $senderId) !== 1
                    || (string) $recipientId !== $pageId
                    || !is_string($message['mid'] ?? null)
                    || $message['mid'] === '' || strlen($message['mid']) > 255) {
                    continue;
                }

                $content = $message['text'] ?? null;
                if (!is_string($content)) {
                    if (!is_array($message['attachments'] ?? null)) {
                        continue;
                    }
                    $content = 'Khách hàng đã gửi tệp đính kèm.';
                }
                $content = trim($content);
                if ($content === '' || strlen($content) > 4000 || str_contains($content, "\0")) {
                    continue;
                }

                $pdo = BaseModel::getPdo();
                try {
                    $pdo->beginTransaction();
                    $conversation = $this->conversations->findOrCreateFacebookConversation($senderId);
                    $this->conversations->addFacebookMessage(
                        (int) $conversation['id'],
                        $message['mid'],
                        $content
                    );
                    $pdo->commit();
                    $accepted++;
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (($exception->errorInfo[0] ?? '') === '23000') {
                        try {
                            if ($this->conversations->externalMessageExists('facebook', $message['mid'])) {
                                $duplicates++;
                                continue;
                            }
                        } catch (PDOException $lookupException) {
                            throw new RuntimeException('Không thể xác minh sự kiện Facebook trùng lặp.', 0, $lookupException);
                        }
                        throw new RuntimeException('Không thể lưu sự kiện tin nhắn Facebook.', 0, $exception);
                    }
                    throw new RuntimeException('Không thể lưu sự kiện tin nhắn Facebook.', 0, $exception);
                } catch (RuntimeException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        }

        return ['accepted' => $accepted, 'duplicates' => $duplicates];
    }

    public function sendReply(string $pageScopedId, string $content, int $conversationId): string
    {
        if (preg_match('/^[0-9]{1,64}$/', $pageScopedId) !== 1
            || preg_match('/^.{1,2000}$/us', $content) !== 1) {
            throw new InvalidArgumentException('Tin nhắn Facebook không hợp lệ.');
        }
        $pageId = $this->settings->getByKey('ai_cs_meta_page_id') ?? '';
        $pageToken = $this->vault->decrypt($this->settings->getByKey('ai_cs_meta_page_access_token_encrypted'));
        if ($pageId === '' || $pageToken === '') {
            throw new RuntimeException('Page Access Token chưa được cấu hình.');
        }
        if (!$this->conversations->hasRecentFacebookInbound($conversationId)) {
            throw new DomainException('Không thể gửi: cửa sổ phản hồi Messenger 24 giờ đã hết hạn.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Máy chủ chưa bật cURL; không thể gửi tin nhắn Facebook.');
        }

        $body = '';
        $url = 'https://graph.facebook.com/v26.0/' . rawurlencode($pageId) . '/messages';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối tới Meta.');
        }
        $payload = json_encode([
            'recipient' => ['id' => $pageScopedId],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => $content],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        try {
            $options = [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $pageToken,
                ],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 32768) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ];
            if (!curl_setopt_array($handle, $options)) {
                throw new RuntimeException('Không thể cấu hình kết nối tới Meta.');
            }
            $success = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
        if ($success === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Meta không nhận tin nhắn. Kiểm tra quyền Page và trạng thái hội thoại.');
        }
        try {
            $result = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Meta trả về phản hồi gửi tin không hợp lệ.');
        }
        $messageId = $result['message_id'] ?? null;
        if (!is_string($messageId) || $messageId === '' || strlen($messageId) > 255) {
            throw new RuntimeException('Meta không trả về mã tin nhắn hợp lệ.');
        }
        return $messageId;
    }

    private function isConfigured(string $key): bool
    {
        $value = $this->settings->getByKey($key);
        return is_string($value) && $value !== '';
    }
}
