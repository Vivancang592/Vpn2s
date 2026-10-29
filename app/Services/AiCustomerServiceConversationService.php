<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiConversation;
use App\Models\BaseModel;
use PDO;
use PDOException;
use RuntimeException;

final class AiCustomerServiceConversationService
{
    private const STATES = [
        'AI đang xử lý' => 'ai_active',
        'AI tạm dừng' => 'ai_paused',
        'Kỹ thuật viên tiếp quản' => 'staff_active',
        'Chờ phản hồi' => 'waiting',
        'Lỗi cần xử lý' => 'error',
    ];

    private AiConversation $conversations;
    private AiCustomerServiceMetaService $meta;
    private AiCustomerServiceAuditService $audit;

    public function __construct()
    {
        $this->conversations = new AiConversation();
        $this->meta = new AiCustomerServiceMetaService();
        $this->audit = new AiCustomerServiceAuditService();
    }

    public function listInbox(array $filters): array
    {
        $channel = $filters['channel'] ?? '';
        $state = $filters['state'] ?? '';
        $searchValue = $filters['q'] ?? '';
        if (!is_string($searchValue)) {
            throw new \InvalidArgumentException('Từ khóa tìm kiếm không hợp lệ.');
        }
        $search = trim($searchValue);
        if (!is_string($channel) || !in_array($channel, ['', 'website', 'facebook'], true)) {
            throw new \InvalidArgumentException('Bộ lọc kênh không hợp lệ.');
        }
        if (!is_string($state) || ($state !== '' && !in_array($state, self::STATES, true))) {
            throw new \InvalidArgumentException('Bộ lọc trạng thái không hợp lệ.');
        }
        if (strlen($search) > 100) {
            throw new \InvalidArgumentException('Từ khóa tìm kiếm không được vượt quá 100 ký tự.');
        }

        $rows = $this->conversations->listInbox($channel, $state, $search, 200);
        $items = array_map(fn(array $row): array => $this->presentSummary($row), $rows);

        return [
            'conversations' => $items,
            'total' => $this->conversations->countInbox($channel, $state, $search),
        ];
    }

    public function dashboardOverview(): array
    {
        $overview = $this->conversations->dashboardOverview();
        $daily = [];
        foreach ($overview['daily'] as $row) {
            $daily[(string) $row['day']] = (int) $row['total'];
        }

        $labels = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
        $dates = [];
        $today = new \DateTimeImmutable('today');
        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $today->modify('-' . $offset . ' days');
            $key = $day->format('Y-m-d');
            $dates[] = [
                'label' => $labels[(int) $day->format('N')],
                'count' => $daily[$key] ?? 0,
            ];
        }
        $max = max(array_column($dates, 'count') ?: [0]);
        $overview['trend'] = array_map(static function (array $day) use ($max): array {
            $day['height'] = $max > 0 ? (int) round($day['count'] / $max * 90) : 0;
            return $day;
        }, $dates);
        unset($overview['daily']);
        return $overview;
    }

    public function getConversation(int $id): array
    {
        if ($id < 1) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }
        $conversation = $this->conversations->findForAdmin($id);
        if ($conversation === null) {
            throw new \OutOfBoundsException('Không tìm thấy hội thoại.');
        }

        $summary = $this->presentSummary($conversation);
        $customerType = $summary['customer_type'];
        $messages = array_map(static function (array $message) use ($customerType): array {
            $kind = match ($message['sender_type']) {
                'user' => 'customer',
                'ai' => 'ai',
                'staff' => 'staff',
                default => 'staff',
            };
            $timestamp = strtotime((string) ($message['created_at'] ?? ''));
            $content = (string) $message['content'];
            $imageUrl = null;
            if (preg_match('/\[\[vc-ai-image:([a-f0-9]{32}\.(?:jpg|png|webp|gif))\]\]/', $content, $matches) === 1) {
                $imageUrl = '/api/ai-customer-service/image?message_id=' . rawurlencode((string) $message['id']);
                $content = trim(str_replace($matches[0], '', $content));
                if ($content === '') {
                    $content = 'Đã gửi một ảnh đính kèm.';
                }
            }

            return [
                'id' => (string) $message['id'],
                'kind' => $kind,
                'sender_label' => $kind === 'customer'
                    ? match ($customerType) {
                        'customer' => 'Khách hàng',
                        'facebook' => 'Khách Facebook',
                        default => 'Khách vãng lai',
                    }
                    : ($kind === 'ai' ? 'Nhân viên' : 'Kỹ thuật viên'),
                'text' => $content,
                'image_url' => $imageUrl,
                'time' => $timestamp === false ? '' : date('d/m/Y H:i', $timestamp),
            ];
        }, $this->conversations->getMessages($id));

        $summary['messages'] = $messages;
        return $summary;
    }

    public function transition(int $id, string $action, int $staffId): array
    {
        if ($id < 1 || $staffId < 1) {
            throw new \InvalidArgumentException('Mã hội thoại hoặc nhân viên không hợp lệ.');
        }
        if (!in_array($action, ['handoff', 'resume'], true)) {
            throw new \InvalidArgumentException('Thao tác trạng thái không hợp lệ.');
        }

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            $conversation = $this->conversations->findForUpdate($id);
            if ($conversation === null) {
                throw new \OutOfBoundsException('Không tìm thấy hội thoại.');
            }

            $currentState = (string) $conversation['state'];
            if ($action === 'handoff') {
                if ($currentState === 'staff_active') {
                    throw new \DomainException('Hội thoại đã do kỹ thuật viên tiếp quản.');
                }
                $newState = 'staff_active';
                $handoffStaffId = $staffId;
            } else {
                if ($currentState !== 'staff_active') {
                    throw new \DomainException('Chỉ có thể trả quyền cho AI khi kỹ thuật viên đang tiếp quản.');
                }
                $newState = 'ai_active';
                $handoffStaffId = null;
            }

            if (!$this->conversations->updateState(
                $id,
                $currentState,
                $newState,
                $handoffStaffId,
                'staff',
                $action === 'handoff' ? 'staff_took_over' : 'staff_returned_to_ai'
            )) {
                throw new \DomainException('Trạng thái hội thoại vừa thay đổi; hãy tải lại rồi thử lại.');
            }
            if ($action === 'handoff') {
                $acknowledgment = 'Kỹ thuật viên đã tiếp nhận hội thoại và sẽ hỗ trợ bạn tại đây.';
                $externalMessageId = $conversation['channel'] === 'facebook'
                    ? $this->meta->sendReply((string) $conversation['external_id'], $acknowledgment, $id)
                    : null;
                $this->conversations->addStaffMessage(
                    $id,
                    (string) $conversation['channel'],
                    $staffId,
                    $acknowledgment,
                    $externalMessageId
                );
            }
            $this->audit->record($staffId, 'ownership_changed');
            $pdo->commit();
        } catch (\DomainException | \OutOfBoundsException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể cập nhật trạng thái hội thoại.', 0, $exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($action === 'resume') {
            $replyStatus = (new AiCustomerServiceWebsiteChatService())
                ->respondToUnansweredCustomerMessage($id);
            $result = $this->getConversation($id);
            $result['resume_reply_status'] = $replyStatus;
            return $result;
        }
        return $this->getConversation($id);
    }

    public function deleteConversation(int $conversationId, int $staffId): void
    {
        if ($conversationId < 1 || $staffId < 1) {
            throw new \InvalidArgumentException('Mã hội thoại không hợp lệ.');
        }

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            if ($this->conversations->findForUpdate($conversationId) === null) {
                throw new \OutOfBoundsException('Không tìm thấy hội thoại.');
            }
            if (!$this->conversations->deleteConversation($conversationId)) {
                throw new \OutOfBoundsException('Hội thoại đã bị xóa hoặc không còn tồn tại.');
            }
            $this->audit->record($staffId, 'conversation_deleted');
            $pdo->commit();
        } catch (\OutOfBoundsException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể xóa toàn bộ hội thoại.', 0, $exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

    }

    public function reply(int $id, int $staffId, string $content): array
    {
        $content = trim($content);
        if ($id < 1 || $staffId < 1) {
            throw new \InvalidArgumentException('Mã hội thoại hoặc nhân viên không hợp lệ.');
        }
        if ($content === '' || strlen($content) > 12000 || str_contains($content, "\0")) {
            throw new \InvalidArgumentException('Nội dung phản hồi phải từ 1 đến 12000 byte.');
        }

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            $conversation = $this->conversations->findForUpdate($id);
            if ($conversation === null) {
                throw new \OutOfBoundsException('Không tìm thấy hội thoại.');
            }
            if ($conversation['state'] !== 'staff_active') {
                throw new \DomainException('Chỉ có thể phản hồi khi kỹ thuật viên đang tiếp quản hội thoại.');
            }

            $externalMessageId = null;
            if ($conversation['channel'] === 'facebook') {
                $externalMessageId = $this->meta->sendReply(
                    (string) $conversation['external_id'],
                    $content,
                    $id
                );
            }

            $this->conversations->addStaffMessage(
                $id,
                (string) $conversation['channel'],
                $staffId,
                $content,
                $externalMessageId
            );
            $this->audit->record($staffId, 'staff_reply_saved');
            $pdo->commit();
        } catch (\InvalidArgumentException | \DomainException | \OutOfBoundsException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể lưu phản hồi hội thoại.', 0, $exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $this->getConversation($id);
    }

    private function presentSummary(array $row): array
    {
        $id = (string) $row['id'];
        $channel = (string) $row['channel'];
        $state = (string) $row['state'];
        $customerType = !empty($row['user_id'])
            ? 'customer'
            : ($channel === 'facebook' ? 'facebook' : 'guest');
        $customer = $customerType === 'customer'
            ? trim((string) ($row['username'] ?? ''))
            : trim((string) ($row['guest_name'] ?? ''));
        if ($customer === '' && $customerType !== 'customer') {
            $customer = trim((string) ($row['username'] ?? ''));
        }
        if ($customer === '') {
            $customer = 'Khách ' . $id;
        }

        $lastMessage = trim((string) ($row['last_message'] ?? ''));
        if (preg_match('/\[\[vc-ai-image:[a-f0-9]{32}\.(?:jpg|png|webp|gif)\]\]/', $lastMessage) === 1) {
            $lastMessage = trim((string) preg_replace('/\[\[vc-ai-image:[a-f0-9]{32}\.(?:jpg|png|webp|gif)\]\]/', '', $lastMessage));
            if ($lastMessage === '') {
                $lastMessage = 'Đã gửi một ảnh đính kèm.';
            }
        }
        $preview = $lastMessage === ''
            ? 'Chưa có tin nhắn'
            : (function_exists('mb_substr') ? mb_substr($lastMessage, 0, 180) : substr($lastMessage, 0, 180));
        $activity = $row['activity_at'] ?? $row['created_at'] ?? null;
        $customerTypeLabel = match ($customerType) {
            'customer' => 'Khách hàng',
            'facebook' => 'Khách Facebook',
            default => 'Khách vãng lai',
        };

        return [
            'id' => $id,
            'display_id' => strtoupper($channel === 'website' ? 'WS-' : 'FB-') . $id,
            'customer' => $customer,
            'customer_type' => $customerType,
            'customer_type_label' => $customerTypeLabel,
            'channel' => $channel,
            'state' => $state,
            'status' => $this->stateLabel($state),
            'status_class' => $this->stateClass($state),
            'preview' => $preview,
            'time' => $this->relativeTime($activity),
            'unread' => false,
            'messages' => [],
        ];
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'ai_active' => 'AI đang xử lý',
            'ai_paused' => 'AI tạm dừng',
            'staff_active' => 'Kỹ thuật viên tiếp quản',
            'waiting' => 'Chờ phản hồi',
            'error' => 'Lỗi cần xử lý',
            default => 'Lỗi cần xử lý',
        };
    }

    private function stateClass(string $state): string
    {
        return match ($state) {
            'staff_active' => 'label-human',
            'waiting', 'error' => 'label-waiting',
            default => 'label-ai',
        };
    }

    private function relativeTime(mixed $value): string
    {
        $timestamp = is_string($value) ? strtotime($value) : false;
        if ($timestamp === false) {
            return 'Không rõ thời gian';
        }

        $minutes = max(0, (int) floor((time() - $timestamp) / 60));
        if ($minutes < 1) {
            return 'Vừa xong';
        }
        if ($minutes < 60) {
            return $minutes . ' phút trước';
        }
        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return $hours . ' giờ trước';
        }
        $days = (int) floor($hours / 24);
        return $days < 7 ? $days . ' ngày trước' : date('d/m/Y H:i', $timestamp);
    }
}
