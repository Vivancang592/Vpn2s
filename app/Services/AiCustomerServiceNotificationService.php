<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Setting;

final class AiCustomerServiceNotificationService
{
    public function notifyTechnicianHandoff(int $conversationId, string $reason): void
    {
        $recipients = (new User())->getAll('', 'admin', 'active');
        $mail = new MailService();
        $audit = new AiCustomerServiceAuditService();
        $siteUrl = (new Setting())->getByKey('site_url') ?? '';
        $configuredUrl = rtrim((string) (getenv('APP_URL') ?: $siteUrl), '/');
        $urlParts = parse_url($configuredUrl);
        $baseUrl = is_array($urlParts)
            && in_array(strtolower((string) ($urlParts['scheme'] ?? '')), ['http', 'https'], true)
            && is_string($urlParts['host'] ?? null)
            ? $configuredUrl
            : '';
        $inboxUrl = $baseUrl . '/admin/ai-customer-service';
        $subject = 'Hội thoại cần kỹ thuật viên hỗ trợ · WS-' . $conversationId;
        $safeReason = htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeInboxUrl = htmlspecialchars($inboxUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = '<p>Khách hàng đang chờ kỹ thuật viên hỗ trợ trong Hộp thư AI Customer Service.</p>'
            . '<p><strong>Mã hội thoại:</strong> WS-' . $conversationId . '<br>'
            . '<strong>Lý do:</strong> ' . $safeReason . '</p>'
            . ($baseUrl === ''
                ? '<p>Đăng nhập trang quản trị và mở Hộp thư AI Customer Service.</p>'
                : '<p><a href="' . $safeInboxUrl . '">Mở Hộp thư quản trị</a></p>');

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $failed++;
                continue;
            }
            if ($mail->sendRaw($email, $subject, $body)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if ($sent === 0 || $failed > 0) {
            $audit->record(null, 'technician_handoff_email_failed');
        } else {
            $audit->record(null, 'technician_handoff_email_sent');
        }
    }
}
