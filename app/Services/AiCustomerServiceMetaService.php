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
        $settings = [
            'meta_app_id' => $this->settings->getByKey('ai_cs_meta_app_id') ?? '',
            'meta_page_id' => $this->settings->getByKey('ai_cs_meta_page_id') ?? '',
            'meta_app_secret_configured' => $this->isConfigured('ai_cs_meta_app_secret_encrypted'),
            'verify_token_configured' => $this->isConfigured('ai_cs_meta_verify_token_encrypted'),
            'page_access_token_configured' => $this->isConfigured('ai_cs_meta_page_access_token_encrypted'),
            'secret_storage_ready' => $this->vault->isReady(),
            'webhook_events' => $this->configuredWebhookEvents(),
            'connection_verified_at' => $this->settings->getByKey('ai_cs_meta_connection_verified_at') ?? '',
            'last_webhook_received_at' => $this->settings->getByKey('ai_cs_meta_last_webhook_received_at') ?? '',
        ];
        $settings['missing_fields'] = $this->missingConfigurationFields($settings);
        $settings['configured'] = $settings['missing_fields'] === [];
        $settings['connection_verified'] = $settings['configured'] && $settings['connection_verified_at'] !== '';

        return $settings;
    }

    public function saveConfiguration(array $input, int $adminId): array
    {
        $appId = $input['meta_app_id'] ?? '';
        $pageId = $input['meta_page_id'] ?? '';
        $appSecret = $input['meta_app_secret'] ?? '';
        $verifyToken = $input['verify_token'] ?? '';
        $pageToken = $input['page_access_token'] ?? '';
        $webhookEvents = $input['webhook_events'] ?? $this->configuredWebhookEvents();
        foreach ([$appId, $pageId, $appSecret, $verifyToken, $pageToken] as $value) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('Định dạng cấu hình Meta không hợp lệ.');
            }
        }
        if (!is_array($webhookEvents)
            || array_filter($webhookEvents, 'is_string') !== $webhookEvents) {
            throw new InvalidArgumentException('Danh sách sự kiện webhook không hợp lệ.');
        }
        $allowedWebhookEvents = ['messages', 'messaging_postbacks', 'message_deliveries', 'message_reads'];
        $webhookEvents = array_values(array_unique($webhookEvents));
        if (array_diff($webhookEvents, $allowedWebhookEvents) !== []
            || !in_array('messages', $webhookEvents, true)) {
            throw new InvalidArgumentException('Phải chọn sự kiện messages để AI nhận tin nhắn Facebook.');
        }
        $webhookEvents = array_values(array_filter(
            $allowedWebhookEvents,
            static fn(string $event): bool => in_array($event, $webhookEvents, true)
        ));

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
            $this->settings->setByKey(
                'ai_cs_meta_webhook_events',
                json_encode($webhookEvents, JSON_THROW_ON_ERROR)
            );
            $this->settings->setByKey('ai_cs_meta_connection_verified_at', '');
            $this->settings->setByKey('ai_cs_meta_last_webhook_received_at', '');
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

    public function testConnection(): array
    {
        $settings = $this->settingsForAdmin();
        if ($settings['missing_fields'] !== []) {
            throw new InvalidArgumentException(
                'Thiếu cấu hình Fanpage: ' . implode(', ', $settings['missing_fields']) . '.'
            );
        }
        if (!$this->settings->setByKey('ai_cs_meta_connection_verified_at', '')) {
            throw new RuntimeException('Không thể cập nhật trạng thái kiểm tra kết nối Meta.');
        }

        $pageId = (string) $settings['meta_page_id'];
        $pageToken = $this->vault->decrypt($this->settings->getByKey('ai_cs_meta_page_access_token_encrypted'));
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Máy chủ chưa bật cURL; không thể kiểm tra kết nối Meta.');
        }

        $body = '';
        $url = 'https://graph.facebook.com/v26.0/' . rawurlencode($pageId) . '?fields=id';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối tới Meta.');
        }
        try {
            $options = [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $pageToken,
                ],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 10,
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
            $curlError = $success === false ? curl_error($handle) : '';
        } finally {
            curl_close($handle);
        }

        if ($success === false || $status < 200 || $status >= 300) {
            $errorMessage = '';
            try {
                $response = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
                $errorMessage = is_string($response['error']['message'] ?? null)
                    ? trim($response['error']['message'])
                    : '';
            } catch (\JsonException) {
                $errorMessage = '';
            }
            $this->settings->setByKey('ai_cs_meta_connection_verified_at', '');
            if ($errorMessage !== '') {
                throw new RuntimeException('Meta từ chối kiểm tra Page Access Token: ' . substr($errorMessage, 0, 400));
            }
            if ($curlError !== '') {
                throw new RuntimeException('Không kết nối được tới Meta: ' . substr($curlError, 0, 300));
            }
            throw new RuntimeException('Meta từ chối Page ID hoặc Page Access Token (HTTP ' . $status . ').');
        }

        try {
            $response = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->settings->setByKey('ai_cs_meta_connection_verified_at', '');
            throw new RuntimeException('Meta trả về phản hồi kiểm tra kết nối không hợp lệ.');
        }
        if (!is_array($response) || (string) ($response['id'] ?? '') !== $pageId) {
            $this->settings->setByKey('ai_cs_meta_connection_verified_at', '');
            throw new RuntimeException('Page Access Token không khớp với Page ID đã lưu.');
        }

        $verifiedAt = date('Y-m-d H:i:s');
        if (!$this->settings->setByKey('ai_cs_meta_connection_verified_at', $verifiedAt)) {
            throw new RuntimeException('Không thể lưu trạng thái kiểm tra kết nối Meta.');
        }
        return [
            'message' => 'Kết nối Meta thành công; Page ID và Page Access Token hợp lệ.',
            'connection_verified_at' => $verifiedAt,
        ];
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
        $conversationIds = [];
        $webhookPageConfirmed = false;
        foreach ($payload['entry'] as $entry) {
            if (!is_array($entry) || !is_array($entry['messaging'] ?? null)) {
                continue;
            }
            if (isset($entry['id']) && (string) $entry['id'] !== $pageId) {
                continue;
            }
            if (!$webhookPageConfirmed && (string) ($entry['id'] ?? '') === $pageId) {
                if (!$this->settings->setByKey('ai_cs_meta_last_webhook_received_at', date('Y-m-d H:i:s'))) {
                    throw new RuntimeException('Không thể ghi nhận sự kiện webhook Meta.');
                }
                $webhookPageConfirmed = true;
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
                    $this->conversations->activateFacebookConversationForAi((int) $conversation['id']);
                    $pdo->commit();
                    $accepted++;
                    $conversationIds[] = (int) $conversation['id'];
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (($exception->errorInfo[0] ?? '') === '23000') {
                        try {
                            if ($this->conversations->externalMessageExists('facebook', $message['mid'])) {
                                $duplicates++;
                                $duplicateConversationId = $this->conversations->facebookConversationIdForMessage($message['mid']);
                                if ($duplicateConversationId !== null) {
                                    try {
                                        $pdo->beginTransaction();
                                        $this->conversations->activateFacebookConversationForAi($duplicateConversationId);
                                        $pdo->commit();
                                    } catch (PDOException | RuntimeException $lookupException) {
                                        if ($pdo->inTransaction()) {
                                            $pdo->rollBack();
                                        }
                                        throw new RuntimeException(
                                            'Không thể khôi phục xử lý hội thoại Facebook.',
                                            0,
                                            $lookupException
                                        );
                                    }
                                    $conversationIds[] = $duplicateConversationId;
                                }
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

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'conversation_ids' => array_values(array_unique($conversationIds)),
        ];
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
            $curlError = $success === false ? curl_error($handle) : '';
        } finally {
            curl_close($handle);
        }
        if ($success === false || $status < 200 || $status >= 300) {
            $errorCode = $status >= 400 && $status <= 599 ? $status : 0;
            $message = $curlError !== ''
                ? 'Không kết nối được tới Meta để gửi tin nhắn.'
                : 'Meta không nhận tin nhắn. Kiểm tra quyền Page và trạng thái hội thoại.';
            throw new RuntimeException($message, $errorCode);
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

    private function missingConfigurationFields(array $settings): array
    {
        $required = [
            'meta_app_id' => 'Meta App ID',
            'meta_page_id' => 'Page ID',
            'meta_app_secret_configured' => 'Meta App Secret',
            'verify_token_configured' => 'Verify Token',
            'page_access_token_configured' => 'Page Access Token',
            'secret_storage_ready' => 'khóa mã hóa bí mật',
        ];
        $missing = [];
        foreach ($required as $key => $label) {
            $configured = match ($key) {
                'meta_app_id' => preg_match('/^[0-9]{5,24}$/', (string) ($settings[$key] ?? '')) === 1,
                'meta_page_id' => preg_match('/^[0-9]{5,32}$/', (string) ($settings[$key] ?? '')) === 1,
                default => !empty($settings[$key]),
            };
            if (!$configured) {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    private function configuredWebhookEvents(): array
    {
        $stored = $this->settings->getByKey('ai_cs_meta_webhook_events');
        if ($stored === null || $stored === '') {
            return ['messages'];
        }
        try {
            $events = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Không thể đọc danh sách sự kiện webhook Meta.', 0, $exception);
        }
        if (!is_array($events) || array_filter($events, 'is_string') !== $events) {
            throw new RuntimeException('Danh sách sự kiện webhook Meta đã lưu không hợp lệ.');
        }
        $allowed = ['messages', 'messaging_postbacks', 'message_deliveries', 'message_reads'];
        $events = array_values(array_unique(array_filter(
            $events,
            static fn(string $event): bool => in_array($event, $allowed, true)
        )));
        return in_array('messages', $events, true) ? $events : ['messages'];
    }
}
