<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BaseModel;
use App\Models\Setting;
use PDOException;
use RuntimeException;

final class AiCustomerServicePolicyService
{
    private Setting $settings;

    public function __construct()
    {
        $this->settings = new Setting();
    }

    public function adminSettings(): array
    {
        return [
            'runtime_state' => $this->runtimeState(),
            'web_chat_enabled' => $this->isWebsiteChatEnabled(),
            'retention_enabled' => $this->retentionEnabled(),
            'handoff_triggers' => $this->handoffTriggers(),
            'disable_on_handoff' => $this->disableOnHandoff(),
        ];
    }

    public function retentionEnabled(): bool
    {
        $stored = $this->settings->getByKey('ai_cs_retention_enabled');
        if ($stored === null || $stored === '' || $stored === '0') {
            return false;
        }
        if ($stored !== '1') {
            throw new RuntimeException('Cấu hình xóa hội thoại cũ không hợp lệ.');
        }
        return true;
    }

    private function disableOnHandoff(): bool
    {
        $stored = $this->settings->getByKey('ai_cs_disable_on_handoff');
        if ($stored === null || $stored === '') {
            return true;
        }
        if (!in_array($stored, ['0', '1'], true)) {
            throw new RuntimeException('Cấu hình khóa phản hồi khi tiếp quản không hợp lệ.');
        }
        return $stored === '1';
    }

    public function handoffTriggers(): array
    {
        $stored = $this->settings->getByKey('ai_cs_handoff_triggers');
        if ($stored === null || $stored === '') {
            return [];
        }
        try {
            $triggers = json_decode($stored, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Không thể đọc cấu hình quy tắc bàn giao.', 0, $exception);
        }
        if (!is_array($triggers)) {
            throw new RuntimeException('Cấu hình quy tắc bàn giao không hợp lệ.');
        }
        return array_values(array_intersect(
            ['customer_requests_human', 'ai_no_answer', 'customer_needs_more_help'],
            array_filter($triggers, 'is_string')
        ));
    }

    public function runtimeState(): string
    {
        $state = $this->settings->getByKey('ai_cs_runtime_state');
        if ($state === null || $state === '') {
            return 'active';
        }
        if (!in_array($state, ['active', 'paused'], true)) {
            throw new RuntimeException('Cấu hình trạng thái AI không hợp lệ.');
        }
        return $state;
    }

    public function isWebsiteChatEnabled(): bool
    {
        $enabled = $this->settings->getByKey('ai_cs_web_chat_enabled');
        if ($enabled === null || $enabled === '0') {
            return false;
        }
        if ($enabled !== '1') {
            throw new RuntimeException('Cấu hình chat Website không hợp lệ.');
        }
        return true;
    }

    public function saveMonitorSettings(array $input, int $adminId): array
    {
        $runtimeState = $input['runtime_state'] ?? null;
        $webChatEnabled = $input['web_chat_enabled'] ?? null;
        $retentionEnabled = $input['retention_enabled'] ?? null;
        if (!is_string($runtimeState) || !in_array($runtimeState, ['active', 'paused'], true)
            || !is_bool($webChatEnabled) || !is_bool($retentionEnabled) || $adminId < 1) {
            throw new \InvalidArgumentException('Cấu hình giám sát không hợp lệ.');
        }

        $retentionWasEnabled = $this->retentionEnabled();
        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            if (!$this->settings->setByKey('ai_cs_runtime_state', $runtimeState)
                || !$this->settings->setByKey('ai_cs_web_chat_enabled', $webChatEnabled ? '1' : '0')
                || !$this->settings->setByKey('ai_cs_retention_enabled', $retentionEnabled ? '1' : '0')) {
                throw new RuntimeException('Không thể lưu trạng thái vận hành AI.');
            }
            (new AiCustomerServiceAuditService())->record($adminId, 'runtime_updated');
            if ($retentionWasEnabled !== $retentionEnabled) {
                (new AiCustomerServiceAuditService())->record($adminId, 'retention_updated');
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể lưu trạng thái vận hành AI.', 0, $exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $this->adminSettings();
    }

    public function saveHandoffRules(array $input, int $adminId): array
    {
        $triggers = $input['handoff_triggers'] ?? null;
        $disableOnHandoff = $input['disable_on_handoff'] ?? null;
        $allowed = ['customer_requests_human', 'ai_no_answer', 'customer_needs_more_help'];
        if (!is_array($triggers) || $triggers === [] || !is_bool($disableOnHandoff) || !$disableOnHandoff || $adminId < 1
            || count($triggers) > count($allowed)) {
            throw new \InvalidArgumentException('Quy tắc bàn giao không hợp lệ.');
        }
        foreach ($triggers as $trigger) {
            if (!is_string($trigger) || !in_array($trigger, $allowed, true)) {
                throw new \InvalidArgumentException('Quy tắc bàn giao không hợp lệ.');
            }
        }

        $triggers = array_values(array_unique($triggers));
        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            if (!$this->settings->setByKey(
                'ai_cs_handoff_triggers',
                json_encode($triggers, JSON_THROW_ON_ERROR)
            ) || !$this->settings->setByKey('ai_cs_disable_on_handoff', '1')) {
                throw new RuntimeException('Không thể lưu quy tắc bàn giao.');
            }
            (new AiCustomerServiceAuditService())->record($adminId, 'rules_saved');
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể lưu quy tắc bàn giao.', 0, $exception);
        } catch (\JsonException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new RuntimeException('Không thể mã hóa quy tắc bàn giao.', 0, $exception);
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return $this->adminSettings();
    }
}
