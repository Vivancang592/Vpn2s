<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemLog;
use InvalidArgumentException;

final class AiCustomerServiceAuditService
{
    private const DIAGNOSTICS = [
        'provider_unavailable' => 'Provider AI chưa có API Key, model cụ thể hoặc kho bí mật hợp lệ.',
        'provider_http_400' => 'Provider AI từ chối yêu cầu; kiểm tra model và định dạng yêu cầu.',
        'provider_http_401' => 'Provider AI trả HTTP 401; kiểm tra API Key.',
        'provider_http_403' => 'Provider AI trả HTTP 403; kiểm tra quyền model hoặc tài khoản provider.',
        'provider_rate_limited' => 'Provider AI giới hạn yêu cầu (HTTP 408/429); thử lại sau.',
        'provider_server_error' => 'Provider AI hoặc mạng trung gian trả lỗi máy chủ/tạm thời.',
        'provider_connection_error' => 'Không kết nối được tới provider AI hoặc phản hồi không hợp lệ.',
        'meta_window_expired' => 'Không thể gửi qua Meta vì cửa sổ phản hồi Messenger đã hết hạn.',
        'meta_http_400' => 'Meta từ chối dữ liệu gửi tin (HTTP 400); kiểm tra người nhận hoặc nội dung.',
        'meta_http_401' => 'Meta từ chối Page Access Token (HTTP 401).',
        'meta_http_403' => 'Meta từ chối quyền gửi tin của Page (HTTP 403).',
        'meta_rate_limited' => 'Meta giới hạn yêu cầu gửi tin (HTTP 429); thử lại sau.',
        'meta_http_4xx' => 'Meta từ chối yêu cầu gửi tin; kiểm tra Page Access Token, quyền và trạng thái Page.',
        'meta_http_5xx' => 'Meta gặp lỗi máy chủ khi gửi tin; có thể thử lại sau.',
        'meta_connection_error' => 'Không kết nối được tới Meta để gửi tin.',
        'meta_recipient_invalid' => 'Thông báo Meta cho biết người nhận/PSID có thể không hợp lệ hoặc không nhận được tin.',
        'meta_permission_denied' => 'Thông báo Meta cho biết thiếu quyền hoặc không được phép gửi tin.',
        'meta_window_policy' => 'Thông báo Meta liên quan đến cửa sổ nhắn tin hoặc chính sách gửi tin.',
        'meta_content_invalid' => 'Thông báo Meta cho biết định dạng hoặc nội dung tin nhắn không được hỗ trợ.',
        'meta_parameter_invalid' => 'Thông báo Meta cho biết một tham số của yêu cầu không hợp lệ.',
        'database_error' => 'Lỗi lưu dữ liệu hội thoại trong cơ sở dữ liệu.',
        'conversation_changed' => 'Hội thoại đã đổi trạng thái hoặc có tin mới trước khi AI hoàn tất.',
        'ai_paused' => 'AI đang tạm dừng.',
        'invalid_signature' => 'Meta gửi webhook có chữ ký không hợp lệ; kiểm tra App Secret đã lưu và cấu hình ứng dụng Meta.',
        'invalid_payload' => 'Webhook bị từ chối do payload không đúng cấu trúc Page/Messenger.',
        'unexpected_failure' => 'Xử lý phản hồi tự động dừng do lỗi nội bộ; kiểm tra nhật ký PHP của máy chủ.',
    ];

    private const EVENTS = [
        'provider_saved' => 'Đã cập nhật cấu hình provider; không ghi giá trị bí mật.',
        'provider_test_succeeded' => 'Kiểm tra kết nối provider thành công; không ghi nội dung phản hồi.',
        'provider_generation_401' => 'Provider trả HTTP 401 khi tạo phản hồi AI; kiểm tra API Key/ủy quyền. Không ghi nội dung chat hoặc bí mật.',
        'provider_generation_403' => 'Provider trả HTTP 403 khi tạo phản hồi AI; kiểm tra quyền model và số dư tài khoản. Không ghi nội dung chat hoặc bí mật.',
        'provider_generation_failed' => 'Provider không tạo được phản hồi AI; kiểm tra trạng thái kết nối và quyền model. Không ghi nội dung chat hoặc bí mật.',
        'resume_reply_failed' => 'Không thể tạo hoặc lưu phản hồi AI khi tiếp tục hội thoại; kiểm tra trạng thái provider và nhật ký hệ thống. Không ghi nội dung chat hoặc bí mật.',
        'facebook_auto_reply_sent' => 'Nhân viên AI đã gửi phản hồi tự động cho hội thoại Facebook.',
        'facebook_auto_reply_skipped' => 'Không gửi phản hồi tự động Facebook do trạng thái hội thoại, AI hoặc cấu hình provider; kiểm tra hộp thư và trạng thái AI.',
        'facebook_auto_reply_failed' => 'Xử lý phản hồi tự động Facebook gặp lỗi; kiểm tra nhật ký hệ thống và cấu hình provider/Meta. Không ghi nội dung chat hoặc bí mật.',
        'facebook_webhook_rejected' => 'Webhook Facebook bị từ chối trước khi đưa tin nhắn vào hội thoại.',
        'meta_saved' => 'Đã cập nhật cấu hình Meta; không ghi giá trị bí mật.',
        'meta_test_succeeded' => 'Kiểm tra kết nối Meta thành công; không ghi giá trị bí mật.',
        'runtime_updated' => 'Đã cập nhật trạng thái AI hoặc chat Website.',
        'retention_updated' => 'Đã bật hoặc tắt chính sách tự xóa hội thoại không hoạt động sau 7 ngày.',
        'conversation_retention_deleted' => 'Đã tự động xóa một hội thoại không hoạt động quá thời hạn lưu trữ; không ghi nội dung tin nhắn.',
        'rules_saved' => 'Đã cập nhật quy tắc bàn giao hội thoại.',
        'knowledge_saved' => 'Đã tạo hoặc cập nhật tài liệu kiến thức; không ghi nội dung.',
        'knowledge_archived' => 'Đã lưu trữ tài liệu kiến thức; không ghi nội dung.',
        'ownership_changed' => 'Đã cập nhật trạng thái tiếp quản hội thoại.',
        'staff_reply_saved' => 'Đã lưu hoặc gửi phản hồi nhân viên; không ghi nội dung tin nhắn.',
        'technician_handoff_email_sent' => 'Đã gửi email thông báo cho quản trị viên về hội thoại đang chờ kỹ thuật viên.',
        'technician_handoff_email_failed' => 'Không gửi được email thông báo kỹ thuật viên tới toàn bộ quản trị viên; kiểm tra nhật ký email và cấu hình SMTP.',
        'conversation_deleted' => 'Quản trị viên đã xóa toàn bộ một hội thoại; không ghi nội dung tin nhắn.',
    ];

    public function record(
        ?int $adminId,
        string $event,
        ?string $diagnosticCode = null,
        ?int $metaErrorCode = null,
        ?int $metaErrorSubcode = null,
        ?string $metaErrorSummary = null,
        ?string $fbtraceId = null
    ): void
    {
        if (!isset(self::EVENTS[$event]) || ($adminId !== null && $adminId < 1)) {
            throw new InvalidArgumentException('Sự kiện audit không hợp lệ.');
        }
        if (($metaErrorCode !== null && ($metaErrorCode < 0 || $metaErrorCode > 2_147_483_647))
            || ($metaErrorSubcode !== null && ($metaErrorSubcode < 0 || $metaErrorSubcode > 2_147_483_647))) {
            throw new InvalidArgumentException('Mã lỗi Meta không hợp lệ.');
        }
        if ($metaErrorSummary !== null && !isset(self::DIAGNOSTICS[$metaErrorSummary])) {
            throw new InvalidArgumentException('Phân loại lỗi Meta không hợp lệ.');
        }
        if ($fbtraceId !== null && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $fbtraceId) !== 1) {
            throw new InvalidArgumentException('Mã truy vết Meta không hợp lệ.');
        }

        $description = self::EVENTS[$event];
        if ($diagnosticCode !== null) {
            if (!isset(self::DIAGNOSTICS[$diagnosticCode])) {
                throw new InvalidArgumentException('Mã chẩn đoán audit không hợp lệ.');
            }
            $description .= ' Nguyên nhân: ' . self::DIAGNOSTICS[$diagnosticCode];
        }
        if ($metaErrorCode !== null || $metaErrorSubcode !== null) {
            $description .= ' Mã Graph API: '
                . ($metaErrorCode === null ? 'không có' : (string) $metaErrorCode)
                . '; mã phụ: '
                . ($metaErrorSubcode === null ? 'không có' : (string) $metaErrorSubcode)
                . '.';
        }
        if ($metaErrorSummary !== null) {
            $description .= ' Chi tiết Meta: ' . self::DIAGNOSTICS[$metaErrorSummary];
        }
        if ($fbtraceId !== null) {
            $description .= ' fbtrace_id: ' . $fbtraceId . '.';
        }

        $created = (new SystemLog())->create([
            'user_id' => $adminId,
            'action' => 'AI Customer Service',
            'description' => $description,
            'ip_address' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$created) {
            throw new \RuntimeException('Không thể ghi nhật ký AI Customer Service.');
        }
    }
}
