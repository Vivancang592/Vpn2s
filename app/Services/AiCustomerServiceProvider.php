<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;
use App\Models\Setting;
use PDOException;
use RuntimeException;

final class AiCustomerServiceProvider
{
    private const DEFAULT_ENDPOINTS = [
        'openrouter' => 'https://openrouter.ai/api/v1',
        'kiraai' => 'https://kiraai.vn/api/v1',
    ];

    private const SETTING_KEYS = [
        'provider' => 'ai_cs_provider',
        'model' => 'ai_cs_model',
        'endpoint' => 'ai_cs_endpoint',
        'temperature' => 'ai_cs_temperature',
        'max_tokens' => 'ai_cs_max_tokens',
        'verified_connection' => 'ai_cs_provider_verified_connection',
    ];

    private Setting $settings;
    private AiCustomerServiceSecretVault $vault;
    private AiCustomerServiceAuditService $audit;

    public function __construct()
    {
        $this->settings = new Setting();
        $this->vault = new AiCustomerServiceSecretVault();
        $this->audit = new AiCustomerServiceAuditService();
    }

    public function settingsForAdmin(): array
    {
        $provider = $this->settings->getByKey(self::SETTING_KEYS['provider']) ?? '';
        $providerKey = in_array($provider, ['openrouter', 'kiraai'], true)
            ? $this->settings->getByKey($this->apiKeySetting($provider))
            : null;
        $model = $this->settings->getByKey(self::SETTING_KEYS['model']) ?? 'auto';
        $endpoint = $this->settings->getByKey(self::SETTING_KEYS['endpoint']) ?? '';
        $verifiedAt = $this->verifiedAtForCurrentConfiguration($provider, $model, $endpoint);

        return [
            'provider' => match ($provider) {
                'openrouter' => 'OpenRouter',
                'kiraai' => 'KiraAI',
                default => '',
            },
            'model' => $model,
            'endpoint' => $endpoint,
            'temperature' => $this->settings->getByKey(self::SETTING_KEYS['temperature']) ?? '0.7',
            'max_tokens' => $this->settings->getByKey(self::SETTING_KEYS['max_tokens']) ?? '2048',
            'api_key_configured' => $providerKey !== null && $providerKey !== '',
            'secret_storage_ready' => $this->vault->isReady(),
            'connection_verified' => $verifiedAt !== null,
            'connection_verified_at' => $verifiedAt,
        ];
    }

    public function saveConfiguration(array $input, int $adminId): array
    {
        $configuration = $this->validateConfiguration($input);
        $providedKey = trim((string) ($input['api_key'] ?? ''));
        if ($providedKey !== '') {
            $configuration['encrypted_api_key'] = $this->vault->encrypt($providedKey);
        }

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            $this->settings->setByKey(self::SETTING_KEYS['provider'], $configuration['provider']);
            $this->settings->setByKey(self::SETTING_KEYS['model'], $configuration['model']);
            $this->settings->setByKey(self::SETTING_KEYS['endpoint'], $configuration['endpoint']);
            $this->settings->setByKey(self::SETTING_KEYS['temperature'], (string) $configuration['temperature']);
            $this->settings->setByKey(self::SETTING_KEYS['max_tokens'], (string) $configuration['max_tokens']);
            $this->settings->setByKey(self::SETTING_KEYS['verified_connection'], null);
            if (isset($configuration['encrypted_api_key'])) {
                $this->settings->setByKey(
                    $this->apiKeySetting($configuration['provider']),
                    $configuration['encrypted_api_key']
                );
            }
            $this->audit->record($adminId, 'provider_saved');
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể lưu cấu hình provider vào cơ sở dữ liệu.', 0, $exception);
        }

        return $this->settingsForAdmin();
    }

    public function fetchModels(string $provider, string $endpoint = ''): array
    {
        $provider = $this->normalizeProvider($provider);
        $endpoint = $this->endpointFor($provider, $endpoint);
        $apiKey = $this->storedApiKey($provider);
        if ($apiKey === '') {
            throw new RuntimeException('Hãy lưu API Key của provider trước khi tải các model được cấp quyền.');
        }
        $modelsEndpoint = $provider === 'openrouter'
            ? $endpoint . '/models/user'
            : $endpoint . '/models';
        $response = $this->requestWithRetry($modelsEndpoint, 'GET', $apiKey);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(
                $this->providerErrorMessage($response['status']),
                $response['status']
            );
        }

        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Provider trả về danh sách model không hợp lệ.');
        }

        if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
            throw new RuntimeException('Provider không trả về danh sách model theo định dạng hỗ trợ.');
        }

        $models = [];
        foreach (array_slice($payload['data'], 0, 1000) as $item) {
            if (!is_array($item) || !isset($item['id']) || !is_string($item['id'])) {
                continue;
            }
            $id = trim($item['id']);
            if ($id === '' || strlen($id) > 200 || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*(?:\/[A-Za-z0-9][A-Za-z0-9._:-]*)?$/', $id) !== 1) {
                continue;
            }
            $name = isset($item['name']) && is_string($item['name']) ? $item['name'] : '';
            $models[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'supports_images' => $this->modelSupportsImages($item),
            ];
        }

        if ($models === []) {
            throw new RuntimeException('Provider chưa trả về model có định dạng được hỗ trợ.');
        }

        return $models;
    }

    public function configuredModelSupportsImages(): ?bool
    {
        $configuration = $this->settingsForAdmin();
        $provider = strtolower((string) $configuration['provider']);
        $model = (string) $configuration['model'];
        if ($model === '' || $model === 'auto' || !in_array($provider, ['openrouter', 'kiraai'], true)) {
            return false;
        }

        foreach ($this->fetchModels($provider, (string) $configuration['endpoint']) as $availableModel) {
            if ($availableModel['id'] === $model) {
                return $availableModel['supports_images'];
            }
        }

        return false;
    }

    public function testConnection(array $input): array
    {
        $configuration = $this->validateConfiguration($input);
        $providedKey = trim((string) ($input['api_key'] ?? ''));
        $apiKey = $providedKey !== '' ? $providedKey : $this->storedApiKey($configuration['provider']);
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Nhập API Key để kiểm tra kết nối hoặc lưu key trước.');
        }
        if ($configuration['model'] === 'auto') {
            throw new \InvalidArgumentException('Chọn một model cụ thể trước khi kiểm tra kết nối.');
        }

        $startedAt = microtime(true);
        $response = $this->requestWithRetry(
            $configuration['endpoint'] . '/chat/completions',
            'POST',
            $apiKey,
            [
                'model' => $configuration['model'],
                'messages' => [['role' => 'user', 'content' => 'Reply with OK.']],
                'temperature' => 0,
                'max_tokens' => min($configuration['max_tokens'], 2048),
                'stream' => false,
            ]
        );
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(
                $this->providerErrorMessage($response['status']),
                $response['status']
            );
        }

        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Provider trả về phản hồi kiểm tra không hợp lệ.');
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('Provider đã kết nối nhưng không tạo nội dung phản hồi.');
        }

        $verifiedAt = $this->recordSuccessfulTestForSavedConfiguration($configuration, $apiKey);

        return [
            'message' => 'Provider đã phản hồi thành công.',
            'duration_ms' => $durationMs,
            'connection_verified' => $verifiedAt !== null,
            'connection_verified_at' => $verifiedAt,
        ];
    }

    private function recordSuccessfulTestForSavedConfiguration(array $configuration, string $testedApiKey): ?string
    {
        $savedProvider = $this->settings->getByKey(self::SETTING_KEYS['provider']) ?? '';
        $savedModel = $this->settings->getByKey(self::SETTING_KEYS['model']) ?? '';
        $savedEndpoint = $this->settings->getByKey(self::SETTING_KEYS['endpoint']) ?? '';
        if ($savedProvider === $configuration['provider']) {
            $savedEndpoint = $this->endpointFor($savedProvider, $savedEndpoint);
        }
        if ($savedProvider !== $configuration['provider']
            || $savedModel !== $configuration['model']
            || $savedEndpoint !== $configuration['endpoint']) {
            return null;
        }

        $savedApiKey = $this->storedApiKey($configuration['provider']);
        if ($savedApiKey === '' || !hash_equals($savedApiKey, $testedApiKey)) {
            return null;
        }

        $testedAt = date(DATE_ATOM);
        $record = [
            'provider' => $configuration['provider'],
            'model' => $configuration['model'],
            'endpoint' => $configuration['endpoint'],
            'tested_at' => $testedAt,
        ];
        if (!$this->settings->setByKey(
            self::SETTING_KEYS['verified_connection'],
            json_encode($record, JSON_THROW_ON_ERROR)
        )) {
            throw new RuntimeException('Không thể lưu trạng thái kiểm tra kết nối provider.');
        }
        return $testedAt;
    }

    private function verifiedAtForCurrentConfiguration(string $provider, string $model, string $endpoint): ?string
    {
        $stored = $this->settings->getByKey(self::SETTING_KEYS['verified_connection']);
        if (!is_string($stored) || $stored === '') {
            return null;
        }

        try {
            $record = json_decode($stored, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($record)
            || ($record['provider'] ?? null) !== $provider
            || ($record['model'] ?? null) !== $model
            || ($record['endpoint'] ?? null) !== $endpoint
            || !is_string($record['tested_at'] ?? null)) {
            return null;
        }

        return $record['tested_at'];
    }

    public function generateResponse(array $messages, int $maxTokens): array
    {
        $configuration = $this->validateConfiguration([
            'provider' => $this->settings->getByKey(self::SETTING_KEYS['provider']) ?? '',
            'model' => $this->settings->getByKey(self::SETTING_KEYS['model']) ?? '',
            'endpoint' => $this->settings->getByKey(self::SETTING_KEYS['endpoint']) ?? '',
            'temperature' => $this->settings->getByKey(self::SETTING_KEYS['temperature']) ?? '0.7',
            'max_tokens' => $this->settings->getByKey(self::SETTING_KEYS['max_tokens']) ?? '512',
        ]);
        if ($configuration['model'] === 'auto') {
            throw new RuntimeException('Hãy chọn model cụ thể trong cài đặt trước khi bật phản hồi AI.');
        }

        $apiKey = $this->storedApiKey($configuration['provider']);
        if ($apiKey === '') {
            throw new RuntimeException('Chưa cấu hình API Key cho provider đã chọn.');
        }
        if ($messages === [] || count($messages) > 24) {
            throw new \InvalidArgumentException('Lịch sử hội thoại không hợp lệ.');
        }
        foreach ($messages as $message) {
            if (!is_array($message)
                || !in_array($message['role'] ?? null, ['system', 'user', 'assistant'], true)
                || !$this->isValidMessageContent($message['content'] ?? null)) {
                throw new \InvalidArgumentException('Nội dung gửi tới provider không hợp lệ.');
            }
        }

        $tokenLimit = min(max(1, $maxTokens), $configuration['max_tokens'], 2048);
        $startedAt = microtime(true);
        $requestMessages = $messages;
        $content = '';
        $promptTokens = 0;
        $completionTokens = 0;
        $hasPromptUsage = false;
        $hasCompletionUsage = false;
        $completed = false;

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = $this->requestWithRetry(
                    $configuration['endpoint'] . '/chat/completions',
                    'POST',
                    $apiKey,
                    [
                        'model' => $configuration['model'],
                        'messages' => $requestMessages,
                        'temperature' => $configuration['temperature'],
                        'max_tokens' => $tokenLimit,
                        'stream' => false,
                    ]
                );
            } catch (RuntimeException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
                usleep(350000 * ($attempt + 1));
                continue;
            }
            if ($response['status'] < 200 || $response['status'] >= 300) {
                if ($attempt < 2 && in_array($response['status'], [408, 429, 500, 502, 503, 504], true)) {
                    usleep(350000 * ($attempt + 1));
                    continue;
                }
                throw new RuntimeException(
                    $this->providerErrorMessage($response['status']),
                    $response['status']
                );
            }

            try {
                $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                if ($attempt < 2) {
                    usleep(350000 * ($attempt + 1));
                    continue;
                }
                throw new RuntimeException('Provider trả về phản hồi không hợp lệ.', 0, $exception);
            }

            $choice = $payload['choices'][0] ?? null;
            $part = is_array($choice) ? ($choice['message']['content'] ?? null) : null;
            if (!is_string($part) || trim($part) === '') {
                if ($attempt < 2) {
                    usleep(350000 * ($attempt + 1));
                    continue;
                }
                throw new RuntimeException('Provider không trả về nội dung phản hồi.');
            }
            $content .= $part;
            if (strlen($content) > 15000) {
                throw new RuntimeException('Phản hồi provider vượt quá giới hạn nội dung an toàn.');
            }
            if (isset($payload['usage']['prompt_tokens']) && is_numeric($payload['usage']['prompt_tokens'])) {
                $promptTokens += max(0, (int) $payload['usage']['prompt_tokens']);
                $hasPromptUsage = true;
            }
            if (isset($payload['usage']['completion_tokens']) && is_numeric($payload['usage']['completion_tokens'])) {
                $completionTokens += max(0, (int) $payload['usage']['completion_tokens']);
                $hasCompletionUsage = true;
            }

            $finishReason = is_array($choice) ? ($choice['finish_reason'] ?? '') : '';
            $hasUnclosedDelimiter = preg_match('/[\(\[\{][^)\]\}]*$/u', $content) === 1;
            if (!in_array($finishReason, ['length', 'max_tokens'], true) && !$hasUnclosedDelimiter) {
                $completed = true;
                break;
            }
            if ($attempt === 2) {
                break;
            }

            $requestMessages[] = ['role' => 'assistant', 'content' => $part];
            $requestMessages[] = [
                'role' => 'user',
                'content' => 'Câu trả lời vừa rồi bị giới hạn độ dài. Hãy tiếp tục ngay từ đúng chỗ đang dở, không lặp lại nội dung trước, rồi kết thúc câu trả lời đầy đủ.',
            ];
            if (count($requestMessages) > 24) {
                $requestMessages = array_merge([$messages[0]], array_slice($requestMessages, -22));
            }
        }

        if (!$completed) {
            throw new RuntimeException('Provider liên tục cắt ngắn phản hồi; không gửi nội dung chưa hoàn chỉnh cho khách.');
        }

        $content = trim(str_replace('*', '', $content));
        if ($content === '') {
            throw new RuntimeException('Provider không trả về nội dung phản hồi hợp lệ.');
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            'content' => $content,
            'provider' => $configuration['provider'],
            'response_ms' => $durationMs,
            'prompt_tokens' => $hasPromptUsage ? $promptTokens : null,
            'completion_tokens' => $hasCompletionUsage ? $completionTokens : null,
        ];
    }

    private function validateConfiguration(array $input): array
    {
        $providerValue = $input['provider'] ?? '';
        $modelValue = $input['model'] ?? 'auto';
        $endpointValue = $input['endpoint'] ?? '';
        $apiKeyValue = $input['api_key'] ?? '';
        if (!is_string($providerValue) || !is_string($modelValue)
            || !is_string($endpointValue) || !is_string($apiKeyValue)) {
            throw new \InvalidArgumentException('Định dạng cấu hình provider không hợp lệ.');
        }

        $provider = $this->normalizeProvider($providerValue);
        $model = trim($modelValue);
        if ($model === '' || strlen($model) > 200
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*(?:\/[A-Za-z0-9][A-Za-z0-9._:-]*)?$/', $model) !== 1) {
            throw new \InvalidArgumentException('Model ID không hợp lệ.');
        }

        $endpoint = $this->endpointFor($provider, trim($endpointValue));
        $temperature = $input['temperature'] ?? '0.7';
        if (!is_numeric($temperature) || !is_finite((float) $temperature)
            || (float) $temperature < 0 || (float) $temperature > 2) {
            throw new \InvalidArgumentException('Temperature phải nằm trong khoảng 0 đến 2.');
        }

        $maxTokens = $input['max_tokens'] ?? '2048';
        if (!is_scalar($maxTokens) || preg_match('/^[1-9][0-9]{0,5}$/', (string) $maxTokens) !== 1
            || (int) $maxTokens > 128000) {
            throw new \InvalidArgumentException('Giới hạn token phải nằm trong khoảng 1 đến 128000.');
        }

        $apiKey = trim($apiKeyValue);
        if (strlen($apiKey) > 4096 || preg_match('/[\x00-\x20\x7f]/', $apiKey) === 1) {
            throw new \InvalidArgumentException('API Key có định dạng không hợp lệ.');
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'endpoint' => $endpoint,
            'temperature' => (float) $temperature,
            'max_tokens' => (int) $maxTokens,
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!isset(self::DEFAULT_ENDPOINTS[$provider])) {
            throw new \InvalidArgumentException('Provider không được hỗ trợ.');
        }
        return $provider;
    }

    private function modelSupportsImages(array $model): ?bool
    {
        $architecture = is_array($model['architecture'] ?? null) ? $model['architecture'] : [];
        $modalities = $architecture['input_modalities']
            ?? $model['input_modalities']
            ?? $model['modalities']
            ?? [];
        $modalities = is_array($modalities)
            ? array_filter($modalities, static fn(mixed $modality): bool => is_string($modality))
            : [];
        if ($modalities !== []) {
            return in_array('image', array_map('strtolower', $modalities), true);
        }
        foreach (['supports_vision', 'vision'] as $capability) {
            if (is_bool($model[$capability] ?? null)) {
                return $model[$capability];
            }
        }
        $capabilities = $model['capabilities'] ?? null;
        if (!is_array($capabilities)) {
            return null;
        }
        if (in_array('vision', $capabilities, true) || in_array('image', $capabilities, true)
            || ($capabilities['vision'] ?? false) === true || ($capabilities['image'] ?? false) === true) {
            return true;
        }
        return null;
    }

    private function isValidMessageContent(mixed $content): bool
    {
        if (is_string($content)) {
            return strlen($content) <= 16000;
        }
        if (!is_array($content) || count($content) > 8) {
            return false;
        }
        $totalBytes = 0;
        foreach ($content as $part) {
            if (!is_array($part) || !is_string($part['type'] ?? null)) {
                return false;
            }
            if ($part['type'] === 'text' && is_string($part['text'] ?? null)) {
                $totalBytes += strlen($part['text']);
                continue;
            }
            if ($part['type'] === 'image_url' && is_array($part['image_url'] ?? null)) {
                $url = $part['image_url']['url'] ?? null;
                if (!is_string($url)
                    || preg_match('/^data:image\/(?:jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/', $url) !== 1
                    || strlen($url) > 8 * 1024 * 1024) {
                    return false;
                }
                $totalBytes += strlen($url);
                continue;
            }
            return false;
        }
        return $totalBytes <= 8 * 1024 * 1024;
    }

    private function endpointFor(string $provider, string $endpoint): string
    {
        $default = self::DEFAULT_ENDPOINTS[$provider];
        if ($endpoint === '') {
            return $default;
        }

        $parts = parse_url($endpoint);
        $expectedHost = $provider === 'openrouter' ? 'openrouter.ai' : 'kiraai.vn';
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== $expectedHost
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array(rtrim((string) ($parts['path'] ?? ''), '/'), ['/api/v1'], true)) {
            throw new \InvalidArgumentException('Endpoint phải dùng HTTPS và đúng địa chỉ API chính thức của provider đã chọn.');
        }

        return rtrim($endpoint, '/');
    }

    private function apiKeySetting(string $provider): string
    {
        return 'ai_cs_' . $provider . '_api_key_encrypted';
    }

    private function storedApiKey(string $provider): string
    {
        $encrypted = $this->settings->getByKey($this->apiKeySetting($provider));
        return $this->vault->decrypt($encrypted);
    }

    private function requestWithRetry(string $url, string $method, string $apiKey, ?array $payload = null, int $maxAttempts = 3): array
    {
        $lastException = null;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $response = $this->request($url, $method, $apiKey, $payload);
                if ($response['status'] >= 200 && $response['status'] < 300) {
                    return $response;
                }
                if ($attempt < $maxAttempts - 1 && in_array($response['status'], [408, 429, 500, 502, 503, 504], true)) {
                    usleep(350000 * ($attempt + 1));
                    continue;
                }
                return $response;
            } catch (RuntimeException $exception) {
                $lastException = $exception;
                if ($attempt >= $maxAttempts - 1) {
                    throw $exception;
                }
                usleep(350000 * ($attempt + 1));
            }
        }

        if ($lastException instanceof RuntimeException) {
            throw $lastException;
        }

        throw new RuntimeException('Provider không phản hồi sau nhiều lần thử kết nối lại.');
    }

    private function request(string $url, string $method, string $apiKey, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Máy chủ chưa bật cURL; không thể kết nối provider.');
        }

        $headers = ['Accept: application/json'];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $body = '';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối tới provider.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ];
        if ($method === 'POST' && $payload !== null) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        try {
            if (!curl_setopt_array($handle, $options)) {
                throw new RuntimeException('Không thể cấu hình kết nối tới provider.');
            }
            $success = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } catch (\JsonException) {
            throw new RuntimeException('Không thể tạo yêu cầu kiểm tra provider.');
        } finally {
            curl_close($handle);
        }

        if ($success === false) {
            throw new RuntimeException('Không thể kết nối provider trong thời hạn cho phép.');
        }

        return ['status' => $status, 'body' => $body];
    }

    private function providerErrorMessage(int $status): string
    {
        return match ($status) {
            401 => 'Provider trả HTTP 401: API Key không hợp lệ, hết hạn hoặc chưa được cấp quyền gọi API.',
            403 => 'Provider trả HTTP 403: tài khoản/API Key không có quyền dùng model này hoặc cần nạp số dư.',
            429 => 'Provider đang giới hạn yêu cầu; hãy thử lại sau.',
            default => $status >= 500
                ? 'Provider đang gặp sự cố; hãy thử lại sau.'
                : 'Provider trả về lỗi HTTP ' . $status . '.',
        };
    }
}
