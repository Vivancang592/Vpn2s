<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\AiCustomerServiceAuditService;
use App\Services\AiCustomerServiceConversationService;
use App\Services\AiCustomerServiceKnowledgeService;
use App\Services\AiCustomerServiceMetaService;
use App\Services\AiCustomerServicePolicyService;
use App\Services\AiCustomerServiceProvider;
use App\Services\AiCustomerServiceTypingPresenceService;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use RuntimeException;

class AiCustomerServiceController extends BaseController
{
    private AiCustomerServiceProvider $providerService;
    private AiCustomerServiceConversationService $conversationService;
    private AiCustomerServiceKnowledgeService $knowledgeService;
    private AiCustomerServicePolicyService $policyService;
    private AiCustomerServiceMetaService $metaService;
    private AiCustomerServiceAuditService $auditService;

    public function __construct()
    {
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            $this->redirect('/login');
        }

        $this->settings = [
            'currency_symbol' => 'đ',
            'currency_position' => 'right',
            'currency_decimals' => 0,
        ];
        $this->providerService = new AiCustomerServiceProvider();
        $this->conversationService = new AiCustomerServiceConversationService();
        $this->knowledgeService = new AiCustomerServiceKnowledgeService();
        $this->policyService = new AiCustomerServicePolicyService();
        $this->metaService = new AiCustomerServiceMetaService();
        $this->auditService = new AiCustomerServiceAuditService();
    }

    public function index(): void
    {
        $stats = $this->conversationService->dashboardOverview();
        $providerSettings = $this->providerService->settingsForAdmin();
        $providerConfigured = $providerSettings['api_key_configured']
            && $providerSettings['secret_storage_ready']
            && $providerSettings['model'] !== ''
            && $providerSettings['model'] !== 'auto';
        $providerVerified = $providerConfigured && !empty($providerSettings['connection_verified']);
        $metaSettings = $this->metaService->settingsForAdmin();
        $metaConfigured = $metaSettings['meta_app_id'] !== ''
            && $metaSettings['meta_page_id'] !== ''
            && $metaSettings['meta_app_secret_configured']
            && $metaSettings['verify_token_configured']
            && $metaSettings['page_access_token_configured']
            && $metaSettings['secret_storage_ready'];
        $stats['status'] = $providerConfigured
            ? ($providerVerified
                ? 'Đã kiểm tra kết nối ' . ($providerSettings['provider'] ?: 'provider')
                : 'Đã cấu hình ' . ($providerSettings['provider'] ?: 'provider') . ' · chưa kiểm tra kết nối')
            : 'Chưa đủ API Key, kho bí mật hoặc model cụ thể';
        $stats['pending_summary'] = sprintf(
            'Chờ phản hồi: %s · Lỗi: %s',
            number_format($stats['waiting']),
            number_format($stats['errors'])
        );

        $conversations = $this->conversationService->listInbox([])['conversations'];

        $knowledgeBase = $this->knowledgeService->list();
        $knowledgePending = $this->knowledgeService->pendingCount();

        $aiSettings = array_merge($providerSettings, $this->metaService->settingsForAdmin(), [
            'webhook_url' => '',
            'handoff_rule' => '',
        ]);
        $monitor = $this->monitorSnapshot($stats);

        $this->render('admin.ai-customer-service.index', [
            'activeMenu' => 'ai-customer-service',
            'stats' => $stats,
            'conversations' => $conversations,
            'knowledgeBase' => $knowledgeBase,
            'knowledgePending' => $knowledgePending,
            'overviewProviderConfigured' => $providerConfigured,
            'overviewProviderVerified' => $providerVerified,
            'overviewMetaConfigured' => $metaConfigured,
            'overviewProviderName' => $providerSettings['provider'],
            'aiSettings' => $aiSettings,
            'monitorSettings' => $monitor['settings'],
            'monitorStats' => $monitor['stats'],
            'monitorProvider' => $monitor['provider'],
            'monitorMeta' => $monitor['meta'],
            'activeConversationId' => $conversations[0]['id'] ?? '',
        ]);
    }

    public function monitorStatus(): void
    {
        try {
            $stats = $this->conversationService->dashboardOverview();
            $this->respond(['ok' => true, ...$this->monitorSnapshot($stats)]);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải dữ liệu giám sát.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function typingStatus(): void
    {
        try {
            $conversationId = $this->conversationId($_GET['id'] ?? null);
            $status = (new AiCustomerServiceTypingPresenceService())->adminStatus($conversationId);
            $this->respond(['ok' => true, ...$status]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
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
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $conversationId = $this->conversationId($input['conversation_id'] ?? null);
            (new AiCustomerServiceTypingPresenceService())->setStaffTyping($conversationId, $input['typing']);
            $this->respond(['ok' => true]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (DomainException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 409);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể cập nhật trạng thái đang soạn tin.'], 500);
        }
    }

    private function monitorSnapshot(array $stats): array
    {
        $provider = $this->providerService->settingsForAdmin();
        $meta = $this->metaService->settingsForAdmin();
        return [
            'settings' => $this->policyService->adminSettings(),
            'stats' => [
                'ai_active' => $stats['ai_active'] ?? 0,
                'human_handoff' => $stats['human_handoff'] ?? 0,
                'waiting' => $stats['waiting'] ?? 0,
                'errors' => $stats['errors'] ?? 0,
            ],
            'provider' => [
                'configured' => $provider['api_key_configured']
                    && $provider['secret_storage_ready']
                    && $provider['model'] !== ''
                    && $provider['model'] !== 'auto',
                'connection_verified' => !empty($provider['connection_verified']),
                'connection_verified_at' => $provider['connection_verified_at'],
                'name' => $provider['provider'],
                'model_selected' => $provider['model'] !== '' && $provider['model'] !== 'auto',
            ],
            'meta' => [
                'configured' => $meta['meta_app_id'] !== ''
                    && $meta['meta_page_id'] !== ''
                    && $meta['meta_app_secret_configured']
                    && $meta['verify_token_configured']
                    && $meta['page_access_token_configured']
                    && $meta['secret_storage_ready'],
            ],
        ];
    }

    public function conversations(): void
    {
        try {
            $result = $this->conversationService->listInbox([
                'channel' => $_GET['channel'] ?? '',
                'state' => $_GET['state'] ?? '',
                'q' => $_GET['q'] ?? '',
            ]);
            $this->respond(['ok' => true, ...$result]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải danh sách hội thoại.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải danh sách hội thoại.'], 500);
        }
    }

    public function audit(): void
    {
        try {
            $page = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
            $perPage = filter_var($_GET['per_page'] ?? '20', FILTER_VALIDATE_INT);
            if ($page === false || $page < 1 || $perPage === false || $perPage < 1 || $perPage > 60) {
                throw new InvalidArgumentException('Tham số phân trang nhật ký không hợp lệ.');
            }

            $systemLog = new \App\Models\SystemLog();
            $systemLog->pruneAiCustomerServiceLogs(60);
            $total = $systemLog->countAiCustomerServiceLogs();
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $logs = $systemLog->recentAiCustomerService($perPage, ($page - 1) * $perPage);
            $entries = array_map(static function (array $row): array {
                $timestamp = strtotime((string) ($row['created_at'] ?? ''));
                return [
                    'action' => 'Trung tâm CSKH AI',
                    'result' => (string) $row['description'],
                    'time' => $timestamp === false ? '' : date('d/m/Y H:i', $timestamp),
                ];
            }, $logs);
            $this->respond([
                'ok' => true,
                'entries' => $entries,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'pages' => $pages,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải nhật ký cấu hình.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function conversationDetail(): void
    {
        try {
            $id = $this->conversationId($_GET['id'] ?? null);
            $this->respond(['ok' => true, 'conversation' => $this->conversationService->getConversation($id)]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải nội dung hội thoại.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải nội dung hội thoại.'], 500);
        }
    }

    public function updateConversationOwnership(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $id = $this->conversationId($input['conversation_id'] ?? null);
            $action = $input['action'] ?? null;
            if (!is_string($action)) {
                throw new InvalidArgumentException('Thao tác trạng thái không hợp lệ.');
            }
            $conversation = $this->conversationService->transition($id, $action, (int) $_SESSION['user_id']);
            $message = match ($conversation['resume_reply_status'] ?? null) {
                'replied' => 'Đã trả quyền cho AI; AI đã trả lời tin nhắn đang chờ.',
                'already_answered' => 'Đã trả quyền cho AI; tin nhắn cuối đã có phản hồi nên không gửi trùng.',
                'no_pending' => 'Đã trả quyền cho AI; hội thoại chưa có tin nhắn khách cần phản hồi.',
                'new_message_pending' => 'Có tin nhắn khách mới trong lúc AI đang tạo phản hồi; tin nhắn mới vẫn đang chờ.',
                'paused' => 'Đã trả quyền cho AI nhưng chế độ AI hoặc kênh Website đang tạm dừng.',
                'provider_unavailable' => 'Đã trả quyền cho AI nhưng chưa thể phản hồi vì cấu hình model chưa sẵn sàng.',
                'failed' => 'Đã trả quyền cho AI nhưng chưa gửi được phản hồi; hội thoại cần được kiểm tra.',
                'not_ready' => 'Đã trả quyền xử lý nhưng hội thoại không ở trạng thái AI có thể phản hồi.',
                default => ($action === 'handoff'
                    ? 'Đã tiếp nhận hội thoại và gửi thông báo cho khách hàng.'
                    : 'Đã cập nhật trạng thái hội thoại.'),
            };
            $this->respond(['ok' => true, 'message' => $message, 'conversation' => $conversation]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (DomainException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 409);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể cập nhật trạng thái hội thoại.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function replyToConversation(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $id = $this->conversationId($input['conversation_id'] ?? null);
            $message = $input['message'] ?? null;
            if (!is_string($message)) {
                throw new InvalidArgumentException('Nội dung phản hồi không hợp lệ.');
            }
            $conversation = $this->conversationService->reply($id, (int) $_SESSION['user_id'], $message);
            $this->respond(['ok' => true, 'message' => 'Đã lưu phản hồi nhân viên.', 'conversation' => $conversation]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (DomainException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 409);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu phản hồi hội thoại.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function deleteConversation(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $conversationId = $this->conversationId($input['conversation_id'] ?? null);
            $this->conversationService->deleteConversation($conversationId, (int) $_SESSION['user_id']);
            $this->respond([
                'ok' => true,
                'message' => 'Đã xóa toàn bộ hội thoại.',
            ]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể xóa toàn bộ hội thoại.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function saveKnowledge(): void
    {
        $input = $this->readJsonInput(65536);
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $item = $this->knowledgeService->save($input, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu tài liệu kiến thức.', 'item' => $item]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu tài liệu kiến thức.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function fetchKnowledgeUrl(): void
    {
        $input = $this->readJsonInput(4096);
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }
        try {
            $url = $input['url'] ?? null;
            if (!is_string($url)) {
                throw new InvalidArgumentException('Đường dẫn trang web không hợp lệ.');
            }
            $document = $this->knowledgeService->fetchFromUrl($url);
            $this->respond(['ok' => true, 'message' => 'Đã đọc nội dung trang. Hãy kiểm tra và lưu tài liệu.', 'document' => $document]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 502);
        }
    }

    public function deleteKnowledge(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $id = $this->conversationId($input['id'] ?? null);
            $this->knowledgeService->archive($id, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu trữ tài liệu kiến thức.']);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (OutOfBoundsException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 404);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu trữ tài liệu kiến thức.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu trữ tài liệu kiến thức.'], 500);
        }
    }

    public function saveMonitorSettings(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $settings = $this->policyService->saveMonitorSettings($input, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu trạng thái vận hành.', 'settings' => $settings]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (DomainException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 409);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu trạng thái vận hành.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu trạng thái vận hành.'], 500);
        }
    }

    public function saveHandoffRules(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $settings = $this->policyService->saveHandoffRules($input, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu quy tắc bàn giao.', 'settings' => $settings]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu quy tắc bàn giao.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    public function saveMetaSettings(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $settings = $this->metaService->saveConfiguration($input, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu cấu hình Meta an toàn.', 'settings' => $settings]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu cấu hình Meta.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 503);
        }
    }

    public function providerModels(): void
    {
        $provider = $_GET['provider'] ?? '';
        $endpoint = $_GET['endpoint'] ?? '';
        if (!is_string($provider) || !is_string($endpoint)) {
            $this->respond(['ok' => false, 'message' => 'Provider hoặc endpoint không hợp lệ.'], 422);
        }

        try {
            $models = $this->providerService->fetchModels($provider, $endpoint);
            $this->respond(['ok' => true, 'models' => $models]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể tải danh sách model.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 502);
        }
    }

    public function saveProviderSettings(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }

        try {
            $settings = $this->providerService->saveConfiguration($input, (int) $_SESSION['user_id']);
            $this->respond(['ok' => true, 'message' => 'Đã lưu cấu hình provider an toàn.', 'settings' => $settings]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể lưu cấu hình provider.'], 500);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 503);
        }
    }

    public function testProviderConnection(): void
    {
        $input = $this->readJsonInput();
        if ($input === null) {
            $this->respond(['ok' => false, 'message' => 'Nội dung yêu cầu không hợp lệ.'], 400);
        }
        if (!$this->validateCsrfToken(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
            $this->respond(['ok' => false, 'message' => 'Phiên bảo mật đã hết hạn. Hãy tải lại trang rồi thử lại.'], 419);
        }
        $lastTestAt = (int) ($_SESSION['ai_cs_provider_test_at'] ?? 0);
        if (time() - $lastTestAt < 10) {
            $this->respond(['ok' => false, 'message' => 'Vui lòng đợi 10 giây trước khi kiểm tra provider lần tiếp theo.'], 429);
        }
        $_SESSION['ai_cs_provider_test_at'] = time();

        try {
            $result = $this->providerService->testConnection($input);
            $this->auditService->record((int) $_SESSION['user_id'], 'provider_test_succeeded');
            $this->respond(['ok' => true, ...$result]);
        } catch (InvalidArgumentException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 422);
        } catch (PDOException $exception) {
            $this->respond(['ok' => false, 'message' => 'Không thể kiểm tra kết nối provider.'], 502);
        } catch (RuntimeException $exception) {
            $this->respond(['ok' => false, 'message' => $exception->getMessage()], 502);
        }
    }

    private function readJsonInput(int $maxBytes = 16384): ?array
    {
        $rawInput = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
        if (!is_string($rawInput) || $rawInput === '' || strlen($rawInput) > $maxBytes) {
            return null;
        }

        try {
            $input = json_decode($rawInput, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($input) ? $input : null;
    }

    private function conversationId(mixed $value): int
    {
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }
        return $id;
    }

    private function respond(array $payload, int $statusCode = 200): void
    {
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        $this->json($payload, $statusCode);
    }
}
