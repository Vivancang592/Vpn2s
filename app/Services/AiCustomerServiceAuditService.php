<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemLog;
use InvalidArgumentException;

final class AiCustomerServiceAuditService
{
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

    public function record(?int $adminId, string $event): void
    {
        if (!isset(self::EVENTS[$event]) || ($adminId !== null && $adminId < 1)) {
            throw new InvalidArgumentException('Sự kiện audit không hợp lệ.');
        }

        $created = (new SystemLog())->create([
            'user_id' => $adminId,
            'action' => 'AI Customer Service',
            'description' => self::EVENTS[$event],
            'ip_address' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$created) {
            throw new \RuntimeException('Không thể ghi nhật ký AI Customer Service.');
        }
    }
}
