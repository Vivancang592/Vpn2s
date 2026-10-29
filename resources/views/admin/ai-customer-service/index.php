<?php
$pageTitle = 'AI Customer Service - Quản Trị Hệ Thống';
$activeMenu = 'ai-customer-service';
$knowledgeTagLabels = [
    'faq' => 'FAQ',
    'pricing' => 'Bảng giá',
    'policy' => 'Chính sách',
    'guide' => 'Hướng dẫn',
];

ob_start();
?>

<div class="ai-page">
    <div class="page-header">
        <div>
            <h1 class="page-title">AI Customer Service</h1>
            <p class="page-subtitle">Tổng quan · Hộp thư · Giám sát · Kiến thức · Cấu hình</p>
        </div>
    </div>

    <div class="ai-tabs">
        <button type="button" class="ai-tab-button active" data-ai-section="overview">Tổng quan</button>
        <button type="button" class="ai-tab-button" data-ai-section="inbox">Hộp thư</button>
        <button type="button" class="ai-tab-button" data-ai-section="monitor">Giám sát</button>
        <button type="button" class="ai-tab-button" data-ai-section="knowledge">Kiến thức</button>
        <button type="button" class="ai-tab-button" data-ai-section="settings">Cài đặt</button>
    </div>

    <section id="overview" class="ai-panel-section active">
        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div class="stat-head">
                    <span class="title">Provider AI</span>
                    <span class="mono stat-icon"><?= vc_admin_icon('AI', 18) ?></span>
                </div>
                <div class="value warning"><?= !empty($overviewProviderConfigured) ? 'Đã cấu hình' : 'Chưa sẵn sàng' ?></div>
                <div class="muted"><?= htmlspecialchars($stats['status'] ?? 'Chưa xác định trạng thái cấu hình', ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-head">
                    <span class="title">Hội thoại</span>
                    <span class="mono stat-icon"><?= vc_admin_icon('MSG', 18) ?></span>
                </div>
                <div class="value"><?= number_format($stats['total'] ?? 0) ?></div>
                <div class="channel-counts" aria-label="Số hội thoại theo kênh">
                    <span><i class="channel-indicator website"></i>Website <strong><?= number_format($stats['website'] ?? 0) ?></strong></span>
                    <span><i class="channel-indicator facebook"></i>Facebook <strong><?= number_format($stats['facebook'] ?? 0) ?></strong></span>
                </div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-head">
                    <span class="title">AI đã phản hồi</span>
                    <span class="mono stat-icon"><?= vc_admin_icon('BOT', 18) ?></span>
                </div>
                <div class="value cyan"><?= number_format($stats['ai_handled'] ?? 0) ?></div>
                <div class="muted">Số hội thoại từng có phản hồi AI</div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-head">
                    <span class="title">Kỹ thuật viên</span>
                    <span class="mono stat-icon"><?= vc_admin_icon('HUM', 18) ?></span>
                </div>
                <div class="value warning"><?= number_format($stats['human_handoff'] ?? 0) ?></div>
                <div class="muted">Hội thoại đang do kỹ thuật viên tiếp quản</div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-head">
                    <span class="title">Cần xử lý</span>
                    <span class="mono stat-icon"><?= vc_admin_icon('TME', 18) ?></span>
                </div>
                <div class="value danger"><?= number_format($stats['pending'] ?? 0) ?></div>
                <div class="muted"><?= htmlspecialchars($stats['pending_summary'] ?? 'Hội thoại chờ phản hồi hoặc lỗi cần kiểm tra', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <div class="ai-overview-grid">
            <div class="glass-card ai-card trend-card">
                <div class="card-header">
                    <h3>Xu hướng hội thoại 7 ngày</h3>
                    <span class="badge badge-info">7 ngày</span>
                </div>
                <div class="trend-chart">
                    <?php foreach ($stats['trend'] ?? [] as $day): ?>
                        <span style="height: <?= (int) $day['height'] ?>%" title="<?= htmlspecialchars($day['label'] . ': ' . $day['count'], ENT_QUOTES, 'UTF-8') ?> hội thoại"></span>
                    <?php endforeach; ?>
                </div>
                <div class="trend-labels">
                    <?php foreach ($stats['trend'] ?? [] as $day): ?>
                        <span><?= htmlspecialchars($day['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass-card ai-card">
                <div class="card-header">
                    <h3>Nhiệm vụ cần xử lý</h3>
                    <button type="button" class="text-btn" data-open-section="inbox">Xem hộp thư</button>
                </div>
                <ul class="task-list">
                    <li>
                        <span class="task-dot <?= (int) ($stats['pending'] ?? 0) > 0 ? 'warning' : 'success' ?>"></span>
                        <div>
                            <strong><?= number_format($stats['pending'] ?? 0) ?> hội thoại cần xử lý</strong>
                            <small><?= htmlspecialchars($stats['pending_summary'] ?? 'Chờ phản hồi hoặc lỗi cần kiểm tra', ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <button type="button" class="text-btn small" data-open-section="inbox">Mở</button>
                    </li>
                    <li>
                        <span class="task-dot <?= (int) ($knowledgePending ?? 0) > 0 ? 'warning' : 'success' ?>"></span>
                        <div>
                            <strong><?= number_format($knowledgePending ?? 0) ?> tài liệu cần duyệt</strong>
                            <small>Kiến thức AI</small>
                        </div>
                        <button type="button" class="text-btn small" data-open-section="knowledge">Duyệt</button>
                    </li>
                    <li>
                        <span class="task-dot <?= !empty($overviewProviderVerified) ? 'success' : (!empty($overviewProviderConfigured) ? 'warning' : 'danger') ?>" data-overview-provider-dot></span>
                        <div>
                            <strong data-overview-provider-status><?= !empty($overviewProviderVerified)
                                ? 'Provider AI đã kết nối thành công'
                                : (!empty($overviewProviderConfigured)
                                    ? 'Provider AI đã đủ cấu hình · chưa kiểm tra kết nối'
                                    : 'Provider AI chưa đủ cấu hình để phản hồi') ?></strong>
                            <small><?= htmlspecialchars((string) ($overviewProviderName ?: 'Chưa chọn provider'), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <button type="button" class="text-btn small" data-open-settings="model">Cài đặt</button>
                    </li>
                    <li>
                        <span class="task-dot <?= !empty($monitorMeta['connection_verified']) ? 'success' : (!empty($overviewMetaConfigured) ? 'warning' : 'danger') ?>" data-overview-meta-dot></span>
                        <div>
                            <strong data-overview-meta-status><?= !empty($monitorMeta['connection_verified'])
                                ? 'Kết nối Page với Meta thành công'
                                : (!empty($overviewMetaConfigured)
                                    ? 'Fanpage đủ cấu hình · chưa kiểm tra kết nối Meta'
                                    : 'Fanpage chưa đủ thông tin cấu hình') ?></strong>
                            <small data-overview-meta-detail><?= !empty($monitorMeta['last_webhook_received_at'])
                                ? 'Đã nhận sự kiện webhook: ' . htmlspecialchars((string) $monitorMeta['last_webhook_received_at'], ENT_QUOTES, 'UTF-8')
                                : (!empty($overviewMetaConfigured) ? 'Kiểm tra Page ID và Page Access Token trong Cài đặt.' : 'Kênh Facebook chưa sẵn sàng') ?></small>
                        </div>
                        <button type="button" class="text-btn small" data-open-settings="fanpage">Cài đặt</button>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <section id="inbox" class="ai-panel-section">
        <div class="inbox-layout">
                <div class="thread-column" id="thread-column">
                    <div class="thread-column-header">
                        <span id="thread-count" class="muted"><?= count($conversations) ?> hội thoại</span>
                        <button type="button" class="thread-list-toggle" id="thread-list-toggle" aria-expanded="true" aria-controls="thread-column" title="Thu gọn danh sách hội thoại" aria-label="Thu gọn danh sách hội thoại">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v13H4zM9 5.5v13m8-9-3 2.5 3 2.5" /></svg>
                            <span>Hộp thư</span>
                        </button>
                    </div>
                    <div class="thread-list-tools">
                        <div class="searchbar">
                            <span>⌕</span>
                            <input type="search" id="thread-search" placeholder="Tìm tên hoặc email" aria-label="Tìm theo tên hoặc email người dùng Website" />
                        </div>
                        <div class="chip-group">
                            <button type="button" class="chip active" data-thread-filter="all">Tất cả</button>
                            <button type="button" class="chip" data-thread-filter="website">Website</button>
                            <button type="button" class="chip" data-thread-filter="facebook">Facebook</button>
                            <button type="button" class="chip" data-thread-filter="AI đang xử lý">AI xử lý</button>
                            <button type="button" class="chip" data-thread-filter="Kỹ thuật viên tiếp quản">Kỹ thuật viên</button>
                            <button type="button" class="chip" data-thread-filter="Chờ phản hồi">Chờ phản hồi</button>
                        </div>
                    </div>

                    <div class="thread-list">
                        <?php foreach ($conversations as $index => $thread): ?>
                        <div class="thread-item<?= $index === 0 ? ' active' : '' ?>" data-thread-id="<?= htmlspecialchars((string) $thread['id'], ENT_QUOTES, 'UTF-8') ?>" data-display-id="<?= htmlspecialchars($thread['display_id'], ENT_QUOTES, 'UTF-8') ?>" data-channel="<?= htmlspecialchars($thread['channel'], ENT_QUOTES, 'UTF-8') ?>" data-customer-type="<?= htmlspecialchars($thread['customer_type'], ENT_QUOTES, 'UTF-8') ?>" data-unread="false">
                            <button type="button" class="thread-select" aria-label="Mở hội thoại với <?= htmlspecialchars($thread['customer'], ENT_QUOTES, 'UTF-8') ?>">
                                <div class="thread-topline">
                                    <span class="thread-name-time">
                                        <strong><?= htmlspecialchars($thread['customer'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= htmlspecialchars($thread['time'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </span>
                                    <span class="label-badge <?= htmlspecialchars($thread['status_class'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($thread['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div class="thread-preview-row">
                                    <p><?= htmlspecialchars($thread['preview'], ENT_QUOTES, 'UTF-8') ?></p>
                                </div>
                                <div class="thread-subline">
                                    <div class="thread-meta"><?= $thread['channel'] === 'website' ? 'Website' : 'Facebook' ?> · <?= htmlspecialchars($thread['display_id'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <span class="customer-type-badge"><?= htmlspecialchars($thread['customer_type_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </button>
                            <button type="button" class="thread-delete-btn" aria-label="Xóa toàn bộ hội thoại với <?= htmlspecialchars($thread['customer'], ENT_QUOTES, 'UTF-8') ?>">×</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="thread-detail">
                    <div class="thread-detail-header">
                        <button type="button" class="inbox-back-button" data-inbox-back aria-label="Quay lại danh sách hội thoại" title="Quay lại danh sách hội thoại">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6M9 12h12" /></svg>
                        </button>
                        <div class="thread-profile">
                            <span class="thread-avatar" id="detail-avatar" aria-hidden="true"><?= htmlspecialchars(substr((string) ($conversations[0]['customer'] ?? '—'), 0, 2), ENT_QUOTES, 'UTF-8') ?></span>
                            <div>
                            <h4 id="detail-heading"><?= htmlspecialchars($conversations[0]['customer'] ?? 'Chưa có hội thoại', ENT_QUOTES, 'UTF-8') ?></h4>
                            <div class="thread-detail-meta" id="detail-meta"><?= isset($conversations[0]) ? (($conversations[0]['channel'] === 'website' ? 'Website' : 'Facebook') . ' · ' . htmlspecialchars($conversations[0]['display_id'], ENT_QUOTES, 'UTF-8')) : 'Chưa có hội thoại' ?></div>
                            </div>
                        </div>
                        <div class="thread-header-actions">
                            <span class="label-badge <?= htmlspecialchars($conversations[0]['status_class'] ?? 'label-waiting', ENT_QUOTES, 'UTF-8') ?>" id="detail-badge"><?= htmlspecialchars($conversations[0]['status'] ?? 'Chưa có hội thoại', ENT_QUOTES, 'UTF-8') ?></span>
                            <div class="detail-actions">
                                <button type="button" class="glass-btn primary" data-thread-action="handoff">Tiếp quản</button>
                                <button type="button" class="glass-btn" data-thread-action="resume">Trả cho AI</button>
                            </div>
                        </div>
                    </div>

                    <div class="conversation-history" id="message-history" aria-live="polite"></div>
                    <div class="reply-composer" id="reply-composer" hidden>
                        <div class="composer-input-row">
                            <label class="sr-only" for="reply-message">Soạn phản hồi</label>
                            <textarea id="reply-message" rows="1" placeholder="Nhắn tin..." aria-label="Soạn phản hồi"></textarea>
                            <button type="button" class="composer-send" data-thread-action="send" aria-label="Gửi tin nhắn" title="Gửi tin nhắn">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6" /></svg>
                            </button>
                        </div>
                        <div class="composer-footer">
                            <span class="muted">Enter để gửi · Shift+Enter để xuống dòng. Facebook gửi qua API khi đã cấu hình.</span>
                        </div>
                    </div>

                </div>
        </div>
    </section>

    <section id="monitor" class="ai-panel-section">
        <div class="two-column-layout">
            <div class="glass-card ai-card">
                <div class="card-header">
                    <h3>Trạng thái vận hành</h3>
                </div>
                <div class="status-stack">
                    <div class="status-row" data-runtime-status-row>
                        <span>Trạng thái AI tự động <strong data-runtime-state-label>Đang hoạt động</strong></span>
                        <span class="dot oxygen" data-runtime-state-indicator></span>
                    </div>
                    <div class="status-row">
                        <span>Hội thoại AI đang xử lý <strong data-monitor-count="ai_active"><?= number_format($monitorStats['ai_active'] ?? 0) ?></strong></span>
                        <span class="dot oxygen"></span>
                    </div>
                    <div class="status-row <?= (int) ($monitorStats['human_handoff'] ?? 0) > 0 ? 'warning' : 'muted' ?>" data-monitor-status="human_handoff">
                        <span>Kỹ thuật viên tiếp quản <strong data-monitor-count="human_handoff"><?= number_format($monitorStats['human_handoff'] ?? 0) ?></strong></span>
                        <span class="dot <?= (int) ($monitorStats['human_handoff'] ?? 0) > 0 ? 'warning' : 'neutral' ?>" data-monitor-dot="human_handoff"></span>
                    </div>
                    <div class="status-row <?= (int) ($monitorStats['waiting'] ?? 0) > 0 ? 'warning' : 'muted' ?>" data-monitor-status="waiting">
                        <span>Chờ phản hồi <strong data-monitor-count="waiting"><?= number_format($monitorStats['waiting'] ?? 0) ?></strong></span>
                        <span class="dot <?= (int) ($monitorStats['waiting'] ?? 0) > 0 ? 'warning' : 'neutral' ?>" data-monitor-dot="waiting"></span>
                    </div>
                    <div class="status-row <?= (int) ($monitorStats['errors'] ?? 0) > 0 ? 'danger' : 'muted' ?>" data-monitor-status="errors">
                        <span>Lỗi cần kiểm tra <strong data-monitor-count="errors"><?= number_format($monitorStats['errors'] ?? 0) ?></strong></span>
                        <span class="dot <?= (int) ($monitorStats['errors'] ?? 0) > 0 ? 'danger' : 'neutral' ?>" data-monitor-dot="errors"></span>
                    </div>
                    <div class="status-row">
                        <span>Provider AI</span>
                        <strong data-monitor-provider><?= !empty($monitorProvider['configured']) ? 'Đã cấu hình' : 'Chưa đủ cấu hình' ?></strong>
                    </div>
                    <div class="status-row">
                        <span>Fanpage</span>
                        <strong data-monitor-meta><?= !empty($monitorMeta['connection_verified'])
                            ? 'Kết nối Page với Meta thành công'
                            : (!empty($monitorMeta['configured']) ? 'Đủ cấu hình · chưa kiểm tra kết nối Meta' : 'Chưa đủ thông tin cấu hình') ?></strong>
                    </div>
                </div>
            </div>

            <div class="glass-card ai-card">
                <div class="card-header">
                    <h3>Điều khiển</h3>
                </div>
                <div class="monitor-toggle-list">
                    <label class="monitor-toggle-row" for="monitor-ai-enabled">
                        <span class="monitor-toggle-copy">
                            <strong>AI tự động phản hồi</strong>
                            <small>Bật/tắt xử lý tự động phía máy chủ</small>
                        </span>
                        <span class="switch-control">
                            <input type="checkbox" id="monitor-ai-enabled" data-monitor-toggle="ai" />
                            <span class="switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="monitor-toggle-row" for="monitor-web-chat">
                        <span class="monitor-toggle-copy">
                            <strong>Bong bóng chat Website</strong>
                            <small>Hiển thị/ẩn khung chat phía khách truy cập website</small>
                        </span>
                        <span class="switch-control">
                            <input type="checkbox" id="monitor-web-chat" data-monitor-toggle="web-chat" />
                            <span class="switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                    <label class="monitor-toggle-row" for="monitor-retention">
                        <span class="monitor-toggle-copy">
                            <strong>Xóa hội thoại cũ</strong>
                            <small>Xóa vĩnh viễn hội thoại không có tin nhắn mới trong hơn 7 ngày; chỉ chạy khi bật và cron máy chủ hoạt động</small>
                        </span>
                        <span class="switch-control">
                            <input type="checkbox" id="monitor-retention" data-monitor-toggle="retention" aria-label="Bật hoặc tắt tự xóa hội thoại không hoạt động" />
                            <span class="switch-track" aria-hidden="true"></span>
                        </span>
                    </label>
                </div>
                <p class="monitor-preview-note" data-retention-note>Trạng thái AI, Website và chính sách lưu trữ được lưu trên máy chủ. Mặc định không tự động xóa hội thoại.</p>
            </div>
        </div>

        <div class="glass-card ai-card">
            <div class="card-header">
                <h3>Quy tắc tự động</h3>
                <button type="button" class="text-btn" data-open-settings="rules">Sửa quy tắc</button>
            </div>
            <div class="rules-grid">
                <div class="rule-box">
                    <strong>Điều kiện chuyển kỹ thuật viên</strong>
                    <p data-monitor-rule="handoff">Đang tải quy tắc đã lưu...</p>
                </div>
                <div class="rule-box">
                    <strong>Giới hạn auto-reply</strong>
                    <p data-monitor-rule="ownership">Đang tải cấu hình...</p>
                </div>
                <div class="rule-box">
                    <strong>Fanpage webhook</strong>
                    <p data-monitor-rule="meta">Đang tải trạng thái cấu hình...</p>
                </div>
            </div>
        </div>
    </section>

    <section id="knowledge" class="ai-panel-section">
        <div class="glass-card ai-card full-width">
            <div class="card-header split">
                <h3>Kiến thức AI</h3>
                <button type="button" class="glass-btn tiny" data-knowledge-action="add">+ Thêm tài liệu</button>
            </div>
            <div class="knowledge-toolbar">
                <input class="field-input" type="search" id="knowledge-search" placeholder="Tìm tiêu đề hoặc nội dung..." aria-label="Tìm kiếm tài liệu kiến thức" />
                <select class="field-input" id="knowledge-status-filter" aria-label="Lọc trạng thái tài liệu">
                    <option value="all">Mọi trạng thái</option>
                    <option value="Đã xuất bản">Đã xuất bản</option>
                    <option value="Chờ duyệt">Chờ duyệt</option>
                    <option value="Nháp">Nháp</option>
                </select>
                <span class="muted" id="knowledge-count"></span>
            </div>
            <div class="knowledge-grid">
                <?php foreach ($knowledgeBase as $item): ?>
                    <div class="knowledge-item" data-knowledge-tag="<?= htmlspecialchars($item['tag'], ENT_QUOTES, 'UTF-8') ?>" data-knowledge-status="<?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="knowledge-top">
                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                            <span class="label-badge <?= strtolower(str_replace([' ', 'đ'], ['', 'd'], $item['tag'])) ?>"><?= htmlspecialchars($item['status']) ?></span>
                        </div>
                        <div class="tags-row">
                            <span class="mini-tag"><?= htmlspecialchars($knowledgeTagLabels[$item['tag']] ?? 'Khác', ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="mini-tag">VPN</span>
                            <span class="mini-tag">Hỗ trợ</span>
                        </div>
                        <div class="knowledge-actions">
                            <button type="button" class="text-btn small" data-knowledge-action="view">Xem</button>
                            <button type="button" class="text-btn small" data-knowledge-action="edit">Sửa</button>
                            <button type="button" class="text-btn small" data-knowledge-action="status">Đổi trạng thái</button>
                            <button type="button" class="text-btn small danger-text" data-knowledge-action="delete">Xóa</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="settings" class="ai-panel-section">
        <div class="glass-card ai-card full-width">
            <div class="card-header split">
                <h3>Cài đặt AI</h3>
                <small class="provider-key-status" id="provider-key-status" role="status" aria-live="polite" hidden></small>
            </div>

            <div class="setting-tabs">
                <button type="button" class="setting-tab active" data-setting-tab="model">Mô hình</button>
                <button type="button" class="setting-tab" data-setting-tab="fanpage">Fanpage</button>
                <button type="button" class="setting-tab" data-setting-tab="rules">Quy tắc</button>
            </div>
            <p class="setting-tab-description" id="setting-tab-description" aria-live="polite">Cấu hình nhà cung cấp AI, API Key, model và tham số. Để trống Endpoint API nếu dùng địa chỉ mặc định của nhà cung cấp.</p>

            <div class="setting-panel active" data-setting-panel="model">
                <div class="settings-grid">
                    <div class="field-block">
                        <label>Nhà cung cấp</label>
                        <select class="field-input" name="provider" aria-label="Nhà cung cấp">
                            <option value="">Chọn nhà cung cấp</option>
                            <option value="OpenRouter" <?= ($aiSettings['provider'] ?? '') === 'OpenRouter' ? 'selected' : '' ?>>OpenRouter</option>
                            <option value="KiraAI" <?= ($aiSettings['provider'] ?? '') === 'KiraAI' ? 'selected' : '' ?>>KiraAI (kiraai.vn)</option>
                        </select>
                    </div>
                    <div class="field-block">
                        <label>Model ID</label>
                        <div class="model-picker">
                            <select class="field-input" name="model" aria-label="Model ID">
                                <option value="auto" selected>Lưu API Key để tải danh sách model</option>
                            </select>
                        </div>
                    </div>
                    <div class="field-block">
                        <label>API Key</label>
                        <div class="secret-field">
                            <input class="field-input" name="api_key" type="password" data-preview-secret placeholder="Nhập API Key để lưu mã hóa an toàn" autocomplete="off" />
                            <button type="button" class="field-icon-button" data-toggle-secret aria-label="Hiện API Key" title="Hiện API Key">
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-open><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-closed hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4.1-.8"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-block wide">
                        <label>Endpoint API (tùy chọn)</label>
                        <input class="field-input" name="endpoint" value="<?= htmlspecialchars($aiSettings['endpoint'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Để trống để dùng địa chỉ mặc định của nhà cung cấp" />
                    </div>
                    <div class="field-block">
                        <label for="model-temperature">Temperature</label>
                        <input class="field-input" id="model-temperature" name="temperature" type="number" min="0" max="2" step="0.1" value="0.7" />
                    </div>
                    <div class="field-block">
                        <label for="model-max-tokens">Giới hạn token</label>
                        <input class="field-input" id="model-max-tokens" name="max_tokens" type="number" min="1" max="128000" step="1" value="2048" />
                    </div>
                </div>
                <div class="setting-panel-actions">
                    <button type="button" class="glass-btn tiny" data-settings-action="test" data-settings-group="model">Kiểm tra cấu hình mô hình</button>
                    <button type="button" class="glass-btn primary tiny" data-settings-action="save" data-settings-group="model">Lưu cấu hình mô hình</button>
                </div>
            </div>

            <div class="setting-panel" data-setting-panel="fanpage">
                <div class="settings-grid">
                    <div class="field-block">
                        <label>Meta App ID</label>
                        <input class="field-input" name="meta_app_id" value="<?= htmlspecialchars($aiSettings['meta_app_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Chỉ nhập dữ liệu mẫu" />
                    </div>
                    <div class="field-block">
                        <label>Meta App Secret</label>
                        <div class="secret-field">
                            <input class="field-input" name="meta_app_secret" type="password" data-preview-secret placeholder="Không nhập secret thật trong preview" autocomplete="off" />
                            <button type="button" class="field-icon-button" data-toggle-secret aria-label="Hiện Meta App Secret" title="Hiện Meta App Secret">
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-open><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-closed hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4.1-.8"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-block">
                        <label>Page ID</label>
                        <input class="field-input" name="meta_page_id" value="<?= htmlspecialchars($aiSettings['meta_page_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Chỉ nhập dữ liệu mẫu" />
                    </div>
                    <div class="field-block">
                        <label>Verify Token</label>
                        <div class="secret-field verify-token-field">
                            <input class="field-input" name="verify_token" type="text" data-preview-secret placeholder="Tạo token để nhập vào Meta" autocomplete="off" />
                            <button type="button" class="field-icon-button" data-copy-field="verify_token" aria-label="Sao chép Verify Token" title="Sao chép Verify Token">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                            </button>
                            <button type="button" class="field-icon-button generate-token-button" data-generate-token aria-label="Tạo Verify Token mới" title="Tạo Verify Token mới">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5"/><path d="M4.9 9A8 8 0 0 1 19.3 7L20 12"/><path d="M4 17v-5h5"/><path d="M19.1 15A8 8 0 0 1 4.7 17L4 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-block">
                        <label>Page Access Token</label>
                        <div class="secret-field">
                            <input class="field-input" name="page_access_token" type="password" data-preview-secret placeholder="Không nhập token thật trong preview" autocomplete="off" />
                            <button type="button" class="field-icon-button" data-toggle-secret aria-label="Hiện Page Access Token" title="Hiện Page Access Token">
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-open><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg viewBox="0 0 24 24" aria-hidden="true" data-eye-closed hidden><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15.7 15.7 0 0 1-3.1 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10.7 10.7 0 0 0 4.1-.8"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-block wide">
                        <label for="webhook-callback-url">Webhook Callback URL (dự kiến)</label>
                        <div class="secret-field webhook-url-field">
                            <input class="field-input" id="webhook-callback-url" name="webhook_url" readonly />
                            <button type="button" class="field-icon-button" data-copy-field="webhook_url" aria-label="Sao chép Webhook Callback URL" title="Sao chép Webhook Callback URL">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="field-block wide webhook-events-field">
                        <label>Sự kiện cần đăng ký trong Meta</label>
                        <p class="field-help">Danh sách này chỉ được lưu để nhắc cấu hình; cần tự bật sự kiện tương ứng trong ứng dụng Meta. Bắt buộc bật messages để Nhân viên AI nhận tin.</p>
                        <div class="event-options">
                            <?php $metaWebhookEvents = $aiSettings['webhook_events'] ?? ['messages']; ?>
                            <label><input type="checkbox" name="webhook_events[]" value="messages" <?= in_array('messages', $metaWebhookEvents, true) ? 'checked' : '' ?> /> messages</label>
                            <label><input type="checkbox" name="webhook_events[]" value="messaging_postbacks" <?= in_array('messaging_postbacks', $metaWebhookEvents, true) ? 'checked' : '' ?> /> messaging_postbacks</label>
                            <label><input type="checkbox" name="webhook_events[]" value="message_deliveries" <?= in_array('message_deliveries', $metaWebhookEvents, true) ? 'checked' : '' ?> /> message_deliveries</label>
                            <label><input type="checkbox" name="webhook_events[]" value="message_reads" <?= in_array('message_reads', $metaWebhookEvents, true) ? 'checked' : '' ?> /> message_reads</label>
                        </div>
                    </div>
                </div>
                <div class="setting-panel-actions">
                    <button type="button" class="glass-btn tiny" data-settings-action="test" data-settings-group="fanpage">Kiểm tra kết nối Meta</button>
                    <button type="button" class="glass-btn primary tiny" data-settings-action="save" data-settings-group="fanpage">Lưu cấu hình Fanpage</button>
                </div>
            </div>

            <div class="setting-panel" data-setting-panel="rules">
                <div class="settings-grid">
                    <div class="field-block wide rule-triggers-field">
                        <label>Điều kiện chuyển cho kỹ thuật viên</label>
                        <div class="event-options rule-options">
                            <label><input type="checkbox" name="handoff_triggers[]" value="customer_requests_human" /> Khách hàng yêu cầu gặp kỹ thuật viên</label>
                            <label><input type="checkbox" name="handoff_triggers[]" value="ai_no_answer" /> AI không tìm thấy câu trả lời phù hợp</label>
                            <label><input type="checkbox" name="handoff_triggers[]" value="customer_needs_more_help" /> Khách hàng cần hỗ trợ thêm</label>
                        </div>
                    </div>
                    <div class="field-block wide">
                        <label>Không gửi tự động khi tiếp quản</label>
                        <label class="toggle-field">
                            <input type="checkbox" name="disable_on_handoff" checked disabled />
                            <span>Luôn khóa auto-reply khi kỹ thuật viên tiếp quản hội thoại</span>
                        </label>
                    </div>
                </div>
                <div class="setting-panel-actions">
                    <button type="button" class="glass-btn tiny" data-settings-action="test" data-settings-group="rules">Kiểm tra quy tắc</button>
                    <button type="button" class="glass-btn primary tiny" data-settings-action="save" data-settings-group="rules">Lưu quy tắc</button>
                </div>
            </div>
        </div>
        <div class="glass-card ai-card">
            <div class="card-header">
                <h3>Nhật ký cấu hình · Preview</h3>
                <div class="audit-header-actions">
                    <span class="muted">Không ghi API key, App Secret hoặc token</span>
                    <button type="button" class="text-btn small" data-refresh-audit title="Tải lại nhật ký cấu hình từ máy chủ">
                        <span aria-hidden="true">↻</span> Làm mới nhật ký
                    </button>
                </div>
            </div>
            <div class="activity-log" id="settings-activity" aria-live="polite"></div>
            <p class="muted" id="settings-activity-status" role="status" aria-live="polite" hidden></p>
            <div class="audit-pagination" aria-label="Phân trang nhật ký cấu hình">
                <span class="muted" id="settings-activity-range">0 sự kiện</span>
                <label class="audit-page-size">
                    <span>Sự kiện mỗi trang</span>
                    <input type="number" id="settings-activity-page-size" value="20" min="1" max="60" step="1" inputmode="numeric" />
                </label>
                <div class="audit-page-navigation">
                    <button type="button" class="audit-page-button" data-audit-page-prev aria-label="Trang trước" title="Trang trước" disabled>‹</button>
                    <span id="settings-activity-page" aria-live="polite">Trang 1 / 1</span>
                    <button type="button" class="audit-page-button" data-audit-page-next aria-label="Trang tiếp theo" title="Trang tiếp theo" disabled>›</button>
                </div>
            </div>
        </div>
    </section>
</div>

<dialog class="knowledge-dialog" id="knowledge-dialog">
    <form method="dialog" id="knowledge-form">
        <div class="dialog-header">
            <div>
                <div class="mini-label">Thư viện tri thức</div>
                <h3 id="knowledge-dialog-title">Tài liệu mới</h3>
            </div>
            <button type="button" class="dialog-close" data-close-dialog aria-label="Đóng">×</button>
        </div>
        <label class="field-block">
            <span>Tiêu đề</span>
            <input class="field-input" name="title" required maxlength="120" />
        </label>
        <div class="settings-grid">
            <label class="field-block">
                <span>Chủ đề</span>
                <select class="field-input" name="tag">
                    <option value="faq">FAQ</option>
                    <option value="pricing">Bảng giá</option>
                    <option value="policy">Chính sách</option>
                    <option value="guide">Hướng dẫn</option>
                </select>
            </label>
            <label class="field-block">
                <span>Trạng thái</span>
                <select class="field-input" name="status">
                    <option>Nháp</option>
                    <option>Chờ duyệt</option>
                    <option>Đã xuất bản</option>
                </select>
            </label>
        </div>
        <div class="field-block">
            <label for="knowledge-source-url">Nhập tài liệu từ trang web (HTTPS)</label>
            <div class="secret-field">
                <input class="field-input" id="knowledge-source-url" type="url" placeholder="https://example.com/huong-dan" autocomplete="url" />
                <button type="button" class="glass-btn tiny" id="knowledge-fetch-url">Đọc trang</button>
            </div>
            <small class="muted">Chỉ tải trang HTTPS công khai. Nội dung cần được kiểm tra và lưu/duyệt trước khi AI sử dụng.</small>
        </div>
        <label class="field-block">
            <span>Nội dung tài liệu</span>
            <textarea class="field-input" name="content" rows="6" maxlength="12000" required placeholder="Nhập nội dung để AI tham chiếu..."></textarea>
        </label>
        <div class="dialog-actions">
            <button type="button" class="glass-btn" data-close-dialog>Hủy</button>
            <button type="submit" class="glass-btn primary" id="knowledge-save-button">Lưu tài liệu</button>
        </div>
    </form>
</dialog>
<div class="ai-toast" id="ai-toast" role="status" aria-live="polite"></div>

<style>
    .ai-page {
        display: flex;
        flex-direction: column;
        gap: 1.2rem;
    }

    .mini-label {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--ios-text-secondary);
    }

    .ai-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        padding: 0.3rem;
        background: rgba(255,255,255,0.02);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
    }
    .ai-tab-button, .setting-tab {
        border: 1px solid transparent;
        background: transparent;
        color: var(--ios-text-primary);
        cursor: pointer;
        border-radius: 999px;
        padding: 0.6rem 0.9rem;
        font-weight: 600;
        font-size: 0.76rem;
        transition: all 0.2s ease;
    }
    .ai-tab-button.active, .setting-tab.active {
        background: rgba(10, 132, 255, 0.12);
        color: var(--ios-blue);
        border-color: rgba(10, 132, 255, 0.25);
    }

    .ai-panel-section {
        display: none;
    }
    .ai-panel-section.active {
        display: block;
    }

    .ai-card {
        padding: 1.2rem;
    }
    .card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .card-header h3 {
        margin: 0;
        font-size: 0.96rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .card-header.split {
        align-items: center;
    }
    .text-btn {
        border: 0;
        background: transparent;
        color: var(--ios-blue);
        font-weight: 700;
        cursor: pointer;
        padding: 0;
    }
    .text-btn.small {
        font-size: 0.75rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.2rem;
    }
    .stat-card {
        padding: 1rem 1.05rem;
    }
    .channel-counts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem 0.75rem;
        margin-top: 0.5rem;
    }
    .channel-counts > span {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: var(--ios-text-secondary);
        font-size: 0.72rem;
    }
    .channel-counts strong {
        color: var(--ios-text-primary);
        font-variant-numeric: tabular-nums;
    }
    .channel-indicator {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--ios-blue);
    }
    .channel-indicator.facebook {
        background: #7c83ff;
    }
    .stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.7rem;
    }
    .title {
        color: var(--ios-text-primary);
        font-size: 0.82rem;
        font-weight: 600;
    }
    .value {
        font-size: clamp(1.4rem, 2vw, 2rem);
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 0.4rem;
    }
    .value.success { color: var(--soc-green); }
    .value.cyan { color: var(--soc-cyan); }
    .value.warning { color: var(--ios-warning); }
    .value.danger { color: var(--ios-danger); }
    .muted {
        color: var(--ios-text-secondary);
        font-size: 0.76rem;
    }

    .ai-overview-grid, .two-column-layout {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .trend-card {
        display: flex;
        flex-direction: column;
    }
    .trend-chart {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        align-items: end;
        height: 180px;
        flex: 0 0 180px;
        margin-top: auto;
        gap: 0.6rem;
        padding: 1rem 0.25rem 0.25rem;
    }
    .trend-chart span {
        display: block;
        border-radius: 999px 999px 0 0;
        background: linear-gradient(180deg, rgba(96, 165, 250, 0.95), rgba(59, 130, 246, 0.35));
        min-height: 28px;
    }
    .trend-labels {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        font-size: 0.72rem;
        color: var(--ios-text-secondary);
        text-align: center;
        padding-top: 0.2rem;
    }

    .task-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        gap: 0.85rem;
    }
    .task-list li {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.8rem 0.9rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
    }
    .task-list strong {
        display: block;
        margin-bottom: 0.15rem;
        font-size: 0.84rem;
    }
    .task-list small {
        color: var(--ios-text-secondary);
    }
    .task-list li > div {
        flex: 1;
    }
    .task-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-top: 0.35rem;
    }
    .task-dot.warning { background: var(--ios-warning); }
    .task-dot.blue { background: var(--ios-blue); }
    .task-dot.success { background: var(--ios-success); }
    .task-dot.danger { background: var(--ios-danger, #ff453a); }

    .chip-group {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .chip {
        border: 1px solid var(--glass-border);
        background: rgba(255,255,255,0.03);
        color: var(--ios-text-primary);
        border-radius: 999px;
        padding: 0.42rem 0.72rem;
        font-size: 0.7rem;
        cursor: pointer;
    }
    .chip.active {
        background: rgba(10, 132, 255, 0.12);
        color: var(--ios-blue);
        border-color: rgba(10, 132, 255, 0.25);
    }

    .inbox-layout {
        display: grid;
        grid-template-columns: minmax(245px, 300px) minmax(0, 1fr);
        gap: 0;
        align-items: stretch;
        min-height: 560px;
        overflow: hidden;
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        background: rgba(3, 8, 18, 0.2);
        transition: grid-template-columns 0.22s ease;
    }
    .inbox-layout.list-collapsed {
        grid-template-columns: 52px minmax(0, 1fr);
    }
    .thread-column {
        display: flex;
        flex-direction: column;
        gap: 0.7rem;
        min-width: 0;
        min-height: 0;
        padding: 0.75rem;
        border-right: 1px solid var(--glass-border);
        background: rgba(255,255,255,0.018);
        transition: padding 0.22s ease, background 0.18s ease;
    }
    .thread-column-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.45rem;
        min-width: 0;
    }
    .thread-column-header > .muted {
        flex: 0 0 auto;
        font-size: 0.68rem;
    }
    .thread-list-tools {
        display: grid;
        gap: 0.55rem;
        min-width: 0;
    }
    .thread-list-tools .searchbar {
        box-sizing: border-box;
        width: 100%;
        max-width: none;
        min-width: 0;
        flex: none;
        padding: 0.58rem 0.7rem;
    }
    .thread-list-tools .chip-group {
        gap: 0.35rem;
    }
    .thread-list-tools .chip {
        padding: 0.32rem 0.55rem;
        font-size: 0.64rem;
    }
    .searchbar {
        display: flex;
        align-items: center;
        flex: 1 1 360px;
        max-width: 520px;
        min-width: 220px;
        gap: 0.6rem;
        padding: 0.72rem 0.9rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
    }
    .searchbar input {
        flex: 1;
        border: 0;
        background: transparent;
        color: var(--ios-text-primary);
        font-size: 0.8rem;
        outline: none;
    }
    .inbox-toolbar, .composer-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .composer-footer {
        justify-content: center;
        text-align: center;
    }
    .thread-list {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        flex: 1;
        min-height: 180px;
        max-height: none;
        overflow: auto;
        overscroll-behavior: contain;
    }
    .inbox-layout.list-collapsed .thread-column {
        align-items: center;
        gap: 0;
        padding: 0.55rem 0.35rem;
    }
    .inbox-layout.list-collapsed .thread-column-header {
        justify-content: center;
        width: 100%;
    }
    .inbox-layout.list-collapsed .thread-column-header > .muted,
    .inbox-layout.list-collapsed .thread-list-tools,
    .inbox-layout.list-collapsed .thread-list {
        display: none;
    }
    .inbox-layout.list-collapsed .thread-list-toggle {
        width: 36px;
        height: 36px;
        min-height: 36px;
        padding: 0;
        border-radius: 12px;
    }
    .inbox-layout.list-collapsed .thread-list-toggle span {
        display: none;
    }
    .inbox-layout.list-collapsed .thread-list-toggle svg {
        transform: rotate(180deg);
    }
    .thread-item {
        position: relative;
        display: flex;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
        color: inherit;
        min-width: 0;
        padding: 0.42rem 0.48rem;
    }
    .thread-item.active {
        border-color: rgba(10, 132, 255, 0.5);
        background: rgba(10, 132, 255, 0.06);
    }
    .thread-item.unread {
        box-shadow: inset 3px 0 0 var(--ios-blue);
    }
    .thread-select {
        display: grid;
        gap: 0.24rem;
        flex: 1;
        min-width: 0;
        padding-right: 1.65rem;
        border: 0;
        padding: 0.1rem;
        background: transparent;
        color: inherit;
        text-align: left;
        cursor: pointer;
    }
    .thread-select:focus-visible {
        outline: 2px solid rgba(10, 132, 255, 0.65);
        outline-offset: 2px;
        border-radius: 6px;
    }
    .thread-delete-btn {
        position: absolute;
        top: 0.12rem;
        right: 0.12rem;
        z-index: 1;
        display: grid;
        place-items: center;
        width: 1.5rem;
        height: 1.5rem;
        border: 0;
        padding: 0;
        background: transparent;
        color: var(--ios-danger);
        font: inherit;
        font-size: 0.65rem;
        font-weight: 650;
        cursor: pointer;
    }
    .thread-delete-btn:hover,
    .thread-delete-btn:focus-visible {
        background: transparent;
    }
    .thread-delete-btn:disabled {
        cursor: wait;
        opacity: 0.55;
    }
    .thread-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        min-width: 0;
        padding-right: 1.5rem;
        margin: 0;
    }
    .thread-name-time {
        display: flex;
        align-items: baseline;
        gap: 0.4rem;
        min-width: 0;
    }
    .thread-topline strong {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 0.8rem;
    }
    .thread-topline small {
        flex: 0 0 auto;
        margin: 0;
        color: var(--ios-text-secondary);
        font-size: 0.64rem;
        white-space: nowrap;
    }
    .thread-meta {
        font-size: 0.72rem;
        color: var(--ios-text-secondary);
        line-height: 1.2;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .thread-subline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        min-width: 0;
    }
    .customer-type-badge {
        display: inline-flex;
        flex: 0 0 auto;
        margin: 0;
        padding: 0.08rem 0.34rem;
        border: 1px solid rgba(10, 132, 255, 0.22);
        border-radius: 999px;
        background: rgba(10, 132, 255, 0.08);
        color: var(--ios-blue);
        font-size: 0.65rem;
        line-height: 1.2;
        font-weight: 600;
    }
    .thread-item p {
        flex: 1;
        min-width: 0;
        margin: 0;
        color: var(--ios-text-primary);
        font-size: 0.74rem;
        line-height: 1.35;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        opacity: 0.78;
    }
    .thread-preview-row {
        min-width: 0;
    }
    .thread-preview-row p {
        width: 100%;
    }
    .thread-item small {
        flex: 0 0 auto;
        margin: 0;
        color: var(--ios-text-secondary);
        font-size: 0.66rem;
        white-space: nowrap;
    }

    .label-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.18rem 0.45rem;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        white-space: nowrap;
        border: 1px solid transparent;
    }
    .label-ai { background: rgba(52, 199, 89, 0.12); color: var(--soc-green); border-color: rgba(52,199,89,0.18); }
    .label-human { background: rgba(255, 149, 0, 0.12); color: var(--ios-warning); border-color: rgba(255,149,0,0.18); }
    .label-waiting { background: rgba(255, 59, 48, 0.12); color: var(--ios-danger); border-color: rgba(255,59,48,0.18); }
    .label-closed { background: rgba(142, 142, 147, 0.12); color: var(--ios-text-secondary); border-color: rgba(142,142,147,.18); }
    .label-faq { background: rgba(10, 132, 255, 0.12); color: var(--ios-blue); border-color: rgba(10,132,255,.18); }
    .label-pricing { background: rgba(175, 82, 222, 0.12); color: #d8a8ff; border-color: rgba(175,82,222,.18); }
    .label-policy { background: rgba(142, 142, 147, 0.12); color: var(--ios-text-secondary); border-color: rgba(142,142,147,.18); }
    .label-guide { background: rgba(52, 199, 89, 0.12); color: var(--soc-green); border-color: rgba(52,199,89,.18); }

    .thread-detail {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        border: 0;
        border-radius: 0;
        background: linear-gradient(160deg, rgba(20, 28, 44, 0.4), rgba(10,14,26,0.2));
        padding: 0.9rem;
        min-width: 0;
        min-height: 0;
    }
    .inbox-back-button {
        display: none;
    }
    .conversation-history {
        display: flex;
        flex-direction: column;
        flex: 1;
        gap: 1.2rem;
        min-height: 300px;
        max-height: 480px;
        overflow: auto;
        overflow-anchor: auto;
        overscroll-behavior: contain;
        padding: 0.35rem 0.15rem;
        scrollbar-width: auto;
        scrollbar-color: rgba(148, 163, 184, 0.7) rgba(255, 255, 255, 0.04);
    }
    .conversation-history::-webkit-scrollbar {
        width: 12px;
    }
    .conversation-history::-webkit-scrollbar-track {
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.04);
    }
    .conversation-history::-webkit-scrollbar-thumb {
        height: 56px;
        border: 3px solid transparent;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.7);
        background-clip: padding-box;
    }
    .conversation-history::-webkit-scrollbar-thumb:hover {
        background: rgba(203, 213, 225, 0.9);
        background-clip: padding-box;
    }
    .message-typing-presence {
        align-self: flex-start;
        flex: 0 0 1.2em;
        min-height: 1.2em;
        color: var(--ios-text-secondary);
        font-size: 0.78rem;
        font-style: italic;
        opacity: 1;
        transition: opacity 0.18s ease;
    }
    .message-typing-presence.is-hidden {
        visibility: hidden;
    }
    .message-typing-presence.is-hiding {
        opacity: 0;
    }
    .message-card.staff-message {
        align-self: flex-end;
        max-width: min(78%, 620px);
    }
    .message-card.customer-message {
        align-self: flex-start;
        max-width: min(78%, 620px);
    }
    .message-card.ai-response {
        align-self: flex-end;
        max-width: min(78%, 620px);
    }
    .message-card .message-meta {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0.45rem;
        margin: 0 0 0.25rem;
        color: var(--ios-text-secondary);
        font-size: 0.68rem;
    }
    .message-card.staff-message .message-meta,
    .message-card.ai-response .message-meta {
        justify-content: flex-end;
    }
    .message-card p {
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
    .message-attachment-image {
        display: block;
        width: auto;
        max-width: min(100%, 360px);
        max-height: 320px;
        margin-top: 0.5rem;
        border-radius: 10px;
        object-fit: contain;
    }
    .reply-composer {
        display: grid;
        gap: 0.4rem;
        padding: 0;
        flex: 0 0 auto;
    }
    .reply-composer[hidden] {
        display: none;
    }
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    .composer-input-row {
        display: flex;
        align-items: flex-end;
        gap: 0.55rem;
        padding: 0.55rem 0.6rem 0.55rem 1rem;
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        background: rgba(255,255,255,0.045);
        transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
    }
    .composer-input-row:focus-within {
        border-color: var(--ios-blue);
        background: rgba(255,255,255,0.06);
        box-shadow: 0 0 0 2px rgba(10,132,255,0.18);
    }
    .reply-composer textarea {
        flex: 1;
        min-width: 0;
        min-height: 30px;
        max-height: 160px;
        resize: none;
        overflow-y: auto;
        font: inherit;
        color: var(--ios-text-primary);
        background: transparent;
        border: 0;
        outline: 0;
        padding: 0.38rem 0;
        line-height: 1.45;
    }
    .composer-send {
        display: grid;
        place-items: center;
        flex: 0 0 34px;
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 50%;
        background: var(--ios-blue);
        color: #fff;
        cursor: pointer;
        transition: opacity 0.18s ease, transform 0.18s ease;
    }
    .composer-send:hover:not(:disabled) {
        transform: scale(1.05);
    }
    .composer-send:disabled {
        cursor: wait;
        opacity: 0.55;
    }
    .composer-send svg {
        width: 18px;
        height: 18px;
        fill: none;
        stroke: currentColor;
        stroke-width: 2;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    body.admin-area:has(.admin-app) .thread-header-actions .detail-actions .glass-btn {
        box-sizing: border-box;
        height: 21px;
        min-height: 21px;
        padding: 0 0.45rem;
        border-radius: 999px;
        font-size: 0.62rem;
        font-weight: 700;
        line-height: 1;
    }
    .field-input:focus {
        outline: 2px solid rgba(10, 132, 255, 0.35);
        border-color: var(--ios-blue);
    }
    .thread-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .thread-list-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.4rem;
        flex: 0 0 auto;
        min-height: 34px;
        padding: 0.35rem 0.6rem;
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: var(--ios-text-secondary);
        font: inherit;
        font-size: 0.68rem;
        cursor: pointer;
        transition: color 0.18s ease, background 0.18s ease;
    }
    .thread-list-toggle:hover {
        color: var(--ios-text-primary);
        background: transparent;
    }
    .thread-list-toggle svg {
        width: 16px;
        height: 16px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
        transition: transform 0.18s ease;
    }
    .thread-list-toggle[aria-expanded="false"] svg {
        transform: rotate(180deg);
    }
    .thread-header-actions {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 0.55rem;
    }
    .thread-profile {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        flex: 1;
        min-width: 0;
    }
    .thread-profile h4 {
        overflow-wrap: anywhere;
    }
    .thread-avatar {
        display: grid;
        place-items: center;
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        color: #fff;
        font-weight: 700;
        font-size: 0.78rem;
        background: linear-gradient(145deg, rgba(10,132,255,0.9), rgba(88,86,214,0.85));
        box-shadow: 0 4px 16px rgba(10,132,255,0.2);
    }
    .thread-detail-meta {
        margin-top: 0.18rem;
        color: var(--ios-text-secondary);
        font-size: 0.72rem;
    }
    .thread-detail-header h4 {
        margin: 0.2rem 0 0;
    }
    .message-card {
        display: flex;
        flex-direction: column;
        border: 0;
        border-radius: 0;
        padding: 0;
        background: transparent;
    }
    .message-card p {
        margin: 0;
        padding: 0.72rem 0.85rem;
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        color: var(--ios-text-primary);
        line-height: 1.6;
        font-size: 0.84rem;
        overflow-wrap: anywhere;
    }
    .message-card.customer-message p {
        background: rgba(255,255,255,0.02);
        border-radius: 15px 15px 15px 4px;
    }
    .message-card.staff-message p {
        background: linear-gradient(145deg, rgba(10, 132, 255, 0.24), rgba(10, 132, 255, 0.12));
        border-color: rgba(10, 132, 255, 0.3);
        border-radius: 15px 15px 4px 15px;
    }
    .message-card.ai-response p {
        background: rgba(52, 199, 89, 0.08);
        border-color: rgba(52, 199, 89, 0.25);
        border-radius: 15px 15px 4px 15px;
    }
    .detail-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.6rem;
        flex-wrap: wrap;
    }
    .detail-actions [hidden] {
        display: none;
    }

    .status-stack {
        display: flex;
        flex-direction: column;
        gap: 0.8rem;
    }
    .status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem 0.9rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(17,24,39,0.2);
    }
    .status-row.muted { opacity: 0.72; }
    .status-row.warning { color: var(--ios-warning); }
    .status-row.danger { color: var(--ios-danger); }
    .status-row.is-current { border-color: rgba(10, 132, 255, 0.55); background: rgba(10, 132, 255, 0.08); }
    .monitor-toggle-list {
        display: grid;
        gap: 0.65rem;
    }
    .monitor-toggle-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        min-width: 0;
        padding: 0.8rem 0.85rem;
        border: 1px solid var(--glass-border);
        border-radius: 14px;
        background: rgba(255,255,255,0.025);
        cursor: pointer;
        transition: border-color 0.18s ease, background 0.18s ease;
    }
    .monitor-toggle-row.is-enabled {
        border-color: rgba(10,132,255,0.38);
        background: rgba(10,132,255,0.07);
    }
    .monitor-toggle-copy {
        display: grid;
        gap: 0.25rem;
        min-width: 0;
    }
    .monitor-toggle-copy strong {
        color: var(--ios-text-primary);
        font-size: 0.8rem;
        font-weight: 650;
    }
    .monitor-toggle-copy small {
        color: var(--ios-text-secondary);
        font-size: 0.7rem;
        line-height: 1.4;
    }
    .monitor-preview-note {
        margin: 0.75rem 0 0;
        color: var(--ios-text-secondary);
        font-size: 0.7rem;
        line-height: 1.45;
    }
    .switch-control {
        position: relative;
        display: inline-flex;
        flex: 0 0 46px;
        width: 46px;
        height: 26px;
    }
    .switch-control input {
        position: absolute;
        inset: 0;
        z-index: 1;
        width: 100%;
        height: 100%;
        margin: 0;
        opacity: 0;
        cursor: pointer;
    }
    .switch-track {
        position: absolute;
        inset: 0;
        border: 1px solid rgba(255,255,255,0.12);
        border-radius: 999px;
        background: rgba(142,142,147,0.35);
        transition: background 0.18s ease, border-color 0.18s ease;
    }
    .switch-track::after {
        position: absolute;
        top: 2px;
        left: 2px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0,0,0,0.28);
        content: "";
        transition: transform 0.18s ease;
    }
    .switch-control input:checked + .switch-track {
        border-color: rgba(52,199,89,0.8);
        background: #34c759;
    }
    .switch-control input:checked + .switch-track::after {
        transform: translateX(20px);
    }
    .switch-control input:focus-visible + .switch-track {
        outline: 2px solid rgba(10,132,255,0.75);
        outline-offset: 3px;
    }
    .dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }
    .dot.oxygen { background: var(--ios-success); box-shadow: 0 0 10px rgba(52, 199, 89, 0.9); }
    .dot.neutral { background: var(--ios-text-secondary); }
    .dot.warning { background: var(--ios-warning); box-shadow: 0 0 10px rgba(255,149,0,0.8); }
    .dot.danger { background: var(--ios-danger); box-shadow: 0 0 10px rgba(255,59,48,0.8); }

    .stack-actions {
        display: grid;
        gap: 0.7rem;
    }
    .rules-grid, .knowledge-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }
    .rule-box, .knowledge-item {
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
        padding: 0.9rem 0.95rem;
    }
    .rule-box p {
        margin: 0.5rem 0 0;
        color: var(--ios-text-secondary);
        line-height: 1.6;
        font-size: 0.8rem;
    }

    .knowledge-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .knowledge-item strong {
        display: block;
        font-size: 0.9rem;
    }
    .tags-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-top: 0.8rem;
    }
    .mini-tag {
        display: inline-flex;
        padding: 0.22rem 0.48rem;
        border-radius: 999px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--glass-border);
        color: var(--ios-text-secondary);
        font-size: 0.65rem;
        text-transform: uppercase;
    }
    .knowledge-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.8rem;
    }
    .knowledge-toolbar {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(150px, 220px) auto;
        align-items: center;
        gap: 0.65rem;
        margin-bottom: 1rem;
    }
    .knowledge-preview {
        margin: 0.75rem 0 0;
        color: var(--ios-text-secondary);
        font-size: 0.78rem;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .danger-text { color: var(--ios-danger); }

    .setting-panel-actions {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        margin-top: 1rem;
    }
    .model-picker {
        display: flex;
        align-items: stretch;
        gap: 0.4rem;
    }
    .model-picker .field-input {
        flex: 1;
        min-width: 0;
    }
    .model-picker button {
        display: inline-grid;
        place-items: center;
        flex: 0 0 44px;
        min-width: 44px;
        padding: 0;
    }
    .model-picker button svg {
        width: 18px;
        height: 18px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .field-block > small.muted {
        line-height: 1.45;
    }
    .setting-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin: 1rem 0 0.65rem;
    }
    .setting-tab-description {
        margin: 0 0 1rem;
        color: var(--ios-text-secondary);
        font-size: 0.8rem;
        line-height: 1.5;
    }
    .provider-key-status {
        margin-left: auto;
        color: var(--ios-text-secondary);
        font-size: 0.8rem;
        white-space: nowrap;
    }
    .provider-key-status.is-saved {
        color: var(--ios-green, #30d158);
    }
    .setting-panel {
        display: none;
    }
    .setting-panel.active {
        display: block;
    }
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
    }
    .setting-panel .settings-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .setting-panel .field-block.wide {
        grid-column: auto;
    }
    .setting-panel .field-block.webhook-events-field {
        grid-column: 1 / -1;
    }
    .setting-panel .field-block.rule-triggers-field {
        grid-column: 1 / -1;
    }
    .field-block {
        display: grid;
        gap: 0.35rem;
    }
    .field-block.wide {
        grid-column: 1 / -1;
    }
    .field-block label {
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ios-text-secondary);
    }
    .field-block .field-help {
        margin: 0;
        color: var(--ios-text-secondary);
        font-size: 0.76rem;
        line-height: 1.5;
    }
    .field-value {
        background: rgba(255,255,255,0.02);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 0.82rem 0.9rem;
        color: var(--ios-text-primary);
        font-size: 0.82rem;
        line-height: 1.5;
    }
    .field-value.masked { color: var(--ios-text-secondary); }
    .field-input {
        width: 100%;
        box-sizing: border-box;
        color: var(--ios-text-primary);
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 0.78rem 0.85rem;
        font: inherit;
        font-size: 0.82rem;
    }
    .field-input option {
        color: #111827;
    }
    .field-block > span {
        font-size: 0.72rem;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--ios-text-secondary);
    }
    .secret-field {
        display: flex;
        align-items: center;
        gap: 0.15rem;
        min-width: 0;
        padding: 0 0.35rem 0 0.2rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.03);
        transition: border-color 0.18s ease, box-shadow 0.18s ease;
    }
    .secret-field:focus-within {
        border-color: var(--ios-blue);
        box-shadow: 0 0 0 2px rgba(10,132,255,0.18);
    }
    .secret-field .field-input {
        flex: 1;
        min-width: 0;
        width: 0;
        border: 0;
        outline: 0;
        background: transparent;
        padding-inline: 0.65rem;
    }
    .secret-field .field-input:focus {
        outline: 0;
        border-color: transparent;
    }
    .secret-field .field-input[readonly] {
        color: var(--ios-text-secondary);
        cursor: text;
    }
    .field-icon-button {
        display: inline-grid;
        place-items: center;
        flex: 0 0 34px;
        width: 34px;
        height: 36px;
        padding: 0;
        border: 0;
        border-radius: 9px;
        color: var(--ios-text-secondary);
        background: transparent;
        cursor: pointer;
        transition: color 0.18s ease, background 0.18s ease;
    }
    .field-icon-button:hover,
    .field-icon-button:focus-visible {
        color: var(--ios-blue);
        background: rgba(10,132,255,0.12);
        outline: none;
    }
    .field-icon-button svg {
        width: 18px;
        height: 18px;
        fill: none;
        stroke: currentColor;
        stroke-width: 1.8;
        stroke-linecap: round;
        stroke-linejoin: round;
    }
    .field-icon-button svg[hidden] {
        display: none;
    }
    .generate-token-button {
        flex: 0 0 auto;
        margin-left: 0.1rem;
    }
    .webhook-url-field .field-input {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.76rem;
    }
    .toggle-field {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.82rem 0.9rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
        color: var(--ios-text-primary);
    }
    .event-options {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.6rem 1rem;
        padding: 0.82rem 0.9rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.02);
        overflow-x: auto;
    }
    .event-options label {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 0.45rem;
        color: var(--ios-text-primary);
        font-size: 0.78rem;
        text-transform: none;
        letter-spacing: normal;
        white-space: nowrap;
    }
    .knowledge-dialog {
        position: fixed;
        inset: 50% auto auto 50%;
        transform: translate(-50%, -50%);
        margin: 0;
        width: min(620px, calc(100vw - 2rem));
        max-height: calc(100vh - 2rem);
        overflow: auto;
        color: var(--ios-text-primary);
        background: #101827;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 1.2rem;
    }
    .knowledge-dialog::backdrop { background: rgba(0,0,0,0.68); backdrop-filter: blur(4px); }
    .knowledge-dialog form { display: grid; gap: 1rem; }
    .dialog-header, .dialog-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .dialog-header h3 { margin: 0.25rem 0 0; }
    .dialog-close {
        border: 0;
        background: transparent;
        color: var(--ios-text-secondary);
        font-size: 1.6rem;
        cursor: pointer;
    }
    .ai-toast {
        position: fixed;
        right: 1.25rem;
        bottom: 1.25rem;
        z-index: 10000;
        max-width: min(420px, calc(100vw - 2.5rem));
        padding: 0.85rem 1rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        background: #111827;
        color: var(--ios-text-primary);
        box-shadow: 0 12px 40px rgba(0,0,0,0.35);
        opacity: 0;
        transform: translateY(10px);
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
    }
    .ai-toast.visible { opacity: 1; transform: translateY(0); }
    .knowledge-empty {
        grid-column: 1 / -1;
        padding: 2rem 1rem;
        text-align: center;
        color: var(--ios-text-secondary);
        border: 1px dashed var(--glass-border);
        border-radius: var(--radius-md);
    }
    .activity-log {
        display: grid;
        gap: 0.55rem;
    }
    .audit-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 1rem;
        padding-top: 0.85rem;
        border-top: 1px solid var(--glass-border);
        font-size: 0.78rem;
    }
    #settings.active {
        display: grid;
        gap: 1.25rem;
    }
    .audit-page-size,
    .audit-page-navigation {
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }
    .audit-page-button {
        display: inline-grid;
        place-items: center;
        width: 2rem;
        height: 2rem;
        padding: 0;
        border: 1px solid var(--glass-border);
        border-radius: 50%;
        background: var(--glass-bg);
        color: var(--ios-text-primary);
        font: inherit;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }
    .audit-page-button:hover:not(:disabled),
    .audit-page-button:focus-visible {
        border-color: var(--ios-blue);
        color: var(--ios-blue);
        outline: none;
    }
    .audit-page-button:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    .audit-page-size input {
        width: 4.5rem;
        padding: 0.35rem 0.45rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        background: var(--glass-bg);
        color: var(--ios-text-primary);
        font: inherit;
    }
    .audit-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.9rem;
    }
    .activity-log-item {
        display: grid;
        grid-template-columns: minmax(145px, auto) minmax(150px, 1fr) minmax(180px, 2fr);
        gap: 0.75rem;
        padding: 0.7rem 0.8rem;
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        color: var(--ios-text-secondary);
        font-size: 0.76rem;
    }
    .activity-log-item strong {
        color: var(--ios-text-primary);
        font-weight: 600;
    }

    @media (max-width: 900px) {
        .ai-overview-grid, .two-column-layout, .inbox-layout {
            grid-template-columns: 1fr;
        }
        .setting-panel .settings-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .detail-actions, .setting-panel-actions {
            flex-wrap: wrap;
        }
    }
    @media (max-width: 600px) {
        .ai-page > .page-header {
            align-items: stretch;
            flex-direction: column;
            gap: 0.75rem;
        }
        .ai-tabs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .ai-tab-button {
            width: 100%;
            padding-inline: 0.45rem;
        }
        .stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem;
        }
        .stat-card {
            min-width: 0;
            padding: 0.8rem;
        }
        .card-header.split {
            align-items: flex-start;
            flex-direction: column;
        }
        #settings .card-header {
            align-items: flex-start;
            flex-direction: column;
        }
        .audit-header-actions {
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            width: 100%;
        }
        .audit-pagination {
            align-items: flex-start;
            flex-direction: column;
        }
        .chip-group {
            max-width: 100%;
        }
        .knowledge-toolbar {
            grid-template-columns: 1fr;
        }
        .setting-panel .settings-grid {
            grid-template-columns: 1fr;
        }
        .inbox-toolbar, .composer-footer {
            align-items: flex-start;
            flex-wrap: wrap;
        }
        #inbox.active .inbox-layout {
            display: block;
            min-height: 0;
            overflow: visible;
            border: 0;
            border-radius: 0;
            background: transparent;
        }
        #inbox.active .thread-column {
            box-sizing: border-box;
            height: max(320px, calc(var(--ai-inbox-viewport-height, 100dvh) - 220px));
            max-height: calc(var(--ai-inbox-viewport-height, 100dvh) - 180px);
            min-height: 320px;
            padding: 0.75rem;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
        }
        #inbox.active .inbox-layout.list-collapsed .thread-column {
            align-items: stretch;
            gap: 0.7rem;
            padding: 0.75rem;
        }
        #inbox.active .thread-column-header > .muted {
            display: block;
        }
        #inbox.active .thread-list-toggle {
            display: none;
        }
        #inbox.active .inbox-layout.list-collapsed .thread-list-tools {
            display: grid;
        }
        #inbox.active .inbox-layout.list-collapsed .thread-list {
            display: flex;
        }
        #inbox.active .thread-list {
            min-height: 0;
        }
        #inbox.active .thread-detail {
            display: none;
        }
        #inbox.active .inbox-layout.thread-open .thread-column {
            display: none;
        }
        #inbox.active .inbox-layout.thread-open .thread-detail {
            position: fixed;
            z-index: 99999;
            inset: 0;
            display: flex;
            box-sizing: border-box;
            width: 100%;
            height: var(--ai-inbox-viewport-height, 100dvh);
            min-height: 0;
            max-height: var(--ai-inbox-viewport-height, 100dvh);
            overflow: hidden;
            padding: calc(0.65rem + env(safe-area-inset-top)) 0.75rem calc(0.65rem + env(safe-area-inset-bottom));
            background: #0b1220;
        }
        .thread-detail-header {
            flex: 0 0 auto;
            align-items: center;
            flex-direction: row;
        }
        .thread-header-actions {
            align-self: auto;
            align-items: flex-end;
            gap: 0.3rem;
        }
        .thread-header-actions .detail-actions {
            gap: 0.3rem;
        }
        .inbox-back-button {
            display: inline-grid;
            place-items: center;
            flex: 0 0 2.4rem;
            width: 2.4rem;
            height: 2.4rem;
            padding: 0;
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            color: var(--ios-text-primary);
            cursor: pointer;
        }
        .inbox-back-button svg {
            width: 1.2rem;
            height: 1.2rem;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .thread-detail {
            min-height: 0;
        }
        .conversation-history {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            overscroll-behavior: contain;
        }
        .message-card.staff-message,
        .message-card.customer-message,
        .message-card.ai-response {
            max-width: 90%;
        }
        .composer-input-row {
            align-items: stretch;
        }
        .knowledge-dialog {
            padding: 0.9rem;
        }
        .activity-log-item {
            grid-template-columns: 1fr;
            gap: 0.25rem;
        }
    }
</style>

<script>
    (() => {
        if (typeof window.aiCustomerServiceCleanup === 'function') {
            window.aiCustomerServiceCleanup();
        }
        const currentPagePath = window.location.pathname.replace(/\/+$/, '');
        const adminPagePath = '/admin/ai-customer-service';
        const appBasePath = currentPagePath.endsWith(adminPagePath)
            ? currentPagePath.slice(0, -adminPagePath.length)
            : '';
        const toast = document.getElementById('ai-toast');
        let toastTimer;
        const notify = (message) => {
            toast.textContent = message;
            toast.classList.add('visible');
            window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3200);
        };
        const readStored = (key, fallback) => {
            try {
                const value = localStorage.getItem(key);
                return value ? JSON.parse(value) : fallback;
            } catch (error) {
                console.error('Không đọc được dữ liệu preview từ trình duyệt.', error);
                notify('Không thể đọc dữ liệu preview đã lưu. Trang sẽ dùng dữ liệu mẫu.');
                return fallback;
            }
        };
        const writeStored = (key, value) => {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error('Không lưu được dữ liệu preview vào trình duyệt.', error);
                notify('Không thể lưu preview trong trình duyệt này.');
                return false;
            }
        };

        const sectionButtons = document.querySelectorAll('.ai-tab-button');
        const sections = document.querySelectorAll('.ai-panel-section');
        const activateSection = (target) => {
            if (target !== 'inbox') {
                document.querySelector('.inbox-layout')?.classList.remove('thread-open');
            }
            sectionButtons.forEach((button) => {
                const active = button.dataset.aiSection === target;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', String(active));
            });
            sections.forEach((section) => section.classList.toggle('active', section.id === target));
        };
        sectionButtons.forEach((button) => button.addEventListener('click', () => activateSection(button.dataset.aiSection)));
        document.querySelectorAll('[data-open-section]').forEach((button) => {
            button.addEventListener('click', () => activateSection(button.dataset.openSection));
        });

        const settingTabs = document.querySelectorAll('.setting-tab');
        const settingPanels = document.querySelectorAll('.setting-panel');
        const settingDescription = document.getElementById('setting-tab-description');
        const settingDescriptions = {
            model: 'Cấu hình nhà cung cấp AI, API Key, model và tham số. Để trống Endpoint API nếu dùng địa chỉ mặc định của nhà cung cấp.',
            fanpage: 'Cấu hình Meta App, Fanpage và sự kiện webhook. URL callback cần được xác nhận bằng Verify Token trong Meta trước khi vận hành.',
            rules: 'Chọn các điều kiện cần bàn giao hội thoại cho kỹ thuật viên và tắt phản hồi AI tự động khi kỹ thuật viên tiếp quản.'
        };
        const activateSetting = (target) => {
            settingTabs.forEach((tab) => tab.classList.toggle('active', tab.dataset.settingTab === target));
            settingPanels.forEach((panel) => panel.classList.toggle('active', panel.dataset.settingPanel === target));
            settingDescription.textContent = settingDescriptions[target] || '';
            document.getElementById('provider-key-status').hidden = target !== 'model';
        };
        settingTabs.forEach((tab) => tab.addEventListener('click', () => activateSetting(tab.dataset.settingTab)));
        document.querySelectorAll('[data-open-settings]').forEach((button) => {
            button.addEventListener('click', () => {
                activateSection('settings');
                activateSetting(button.dataset.openSettings);
            });
        });

        const threadList = document.querySelector('.thread-list');
        const inboxLayout = document.querySelector('.inbox-layout');
        const threadListToggle = document.getElementById('thread-list-toggle');
        const inboxBackButton = document.querySelector('[data-inbox-back]');
        const threadSearch = document.getElementById('thread-search');
        const threadCount = document.getElementById('thread-count');
        const messageHistory = document.getElementById('message-history');
        const detailHeading = document.getElementById('detail-heading');
        const detailMeta = document.getElementById('detail-meta');
        const detailAvatar = document.getElementById('detail-avatar');
        const detailBadge = document.getElementById('detail-badge');
        const replyComposer = document.getElementById('reply-composer');
        const replyInput = document.getElementById('reply-message');
        const typingIndicator = document.createElement('div');
        typingIndicator.className = 'message-typing-presence';
        typingIndicator.setAttribute('role', 'status');
        typingIndicator.setAttribute('aria-live', 'polite');
        typingIndicator.setAttribute('aria-hidden', 'true');
        typingIndicator.classList.add('is-hidden');
        let staffTypingConversationId = '';
        let staffTypingStopTimer = 0;
        let staffTypingHeartbeatTimer = 0;
        let lastTypingError = '';
        let customerTypingHideTimer = 0;
        let customerTypingConversationId = '';
        const handoffButton = document.querySelector('[data-thread-action="handoff"]');
        const resumeButton = document.querySelector('[data-thread-action="resume"]');
        const syncInboxViewport = () => {
            const viewportHeight = window.visualViewport?.height || window.innerHeight;
            inboxLayout.style.setProperty('--ai-inbox-viewport-height', Math.round(viewportHeight) + 'px');
        };
        syncInboxViewport();
        window.addEventListener('resize', syncInboxViewport, { passive: true });
        window.visualViewport?.addEventListener('resize', syncInboxViewport, { passive: true });
        window.visualViewport?.addEventListener('scroll', syncInboxViewport, { passive: true });
        inboxBackButton.addEventListener('click', () => {
            inboxLayout.classList.remove('thread-open');
            Array.from(threadList.querySelectorAll('.thread-item'))
                .find((item) => item.dataset.threadId === activeThreadId)
                ?.querySelector('.thread-select')
                ?.focus();
        });
        threadListToggle.addEventListener('click', () => {
            const isExpanded = threadListToggle.getAttribute('aria-expanded') === 'true';
            const nextExpanded = !isExpanded;
            inboxLayout.classList.toggle('list-collapsed', !nextExpanded);
            threadListToggle.setAttribute('aria-expanded', String(nextExpanded));
            const label = nextExpanded ? 'Hộp thư' : 'Mở danh sách';
            threadListToggle.querySelector('span').textContent = label;
            threadListToggle.title = nextExpanded ? 'Thu gọn danh sách hội thoại' : 'Mở danh sách hội thoại';
            threadListToggle.setAttribute('aria-label', threadListToggle.title);
        });
        const badgeStyles = {
            'AI đang xử lý': 'label-ai',
            'AI tạm dừng': 'label-ai',
            'Kỹ thuật viên tiếp quản': 'label-human',
            'Chờ phản hồi': 'label-waiting',
            'Lỗi cần xử lý': 'label-waiting'
        };
        const threads = new Map();
        const renderedMessageSignatures = new Map();
        threadList.querySelectorAll('.thread-item').forEach((element) => {
            const id = element.dataset.threadId;
            threads.set(id, {
                id,
                display_id: element.dataset.displayId || id,
                customer: element.querySelector('.thread-topline strong')?.textContent || '',
                channel: element.dataset.channel,
                customer_type: element.dataset.customerType || 'guest',
                customer_type_label: element.querySelector('.customer-type-badge')?.textContent || 'Khách vãng lai',
                status: element.querySelector('.thread-topline .label-badge')?.textContent || '',
                status_class: element.querySelector('.thread-topline .label-badge')?.classList[1] || 'label-ai',
                preview: element.querySelector('p')?.textContent || '',
                time: element.querySelector('small')?.textContent || '',
                unread: false,
                messages: []
            });
        });
        const storedActiveThread = readStored('ai-customer-service-active-thread', '');
        let activeThreadId = threads.has(storedActiveThread)
            ? storedActiveThread
            : threadList.querySelector('.thread-item.active')?.dataset.threadId || '';
        let activeFilter = 'all';
        const monitorSettings = <?= json_encode($monitorSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let monitorMeta = <?= json_encode($monitorMeta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let monitorProvider = <?= json_encode($monitorProvider, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let runtimeState = monitorSettings.runtime_state;

        function addMessageElement(message) {
            const card = document.createElement('div');
            card.className = 'message-card ' + (message.kind === 'customer' ? 'customer-message' : message.kind === 'ai' ? 'ai-response' : 'staff-message');
            const meta = document.createElement('div');
            meta.className = 'message-meta';
            const sender = document.createElement('strong');
            sender.textContent = message.kind === 'customer'
                ? (message.sender_label || threads.get(activeThreadId)?.customer_type_label || 'Khách vãng lai')
                : message.kind === 'ai'
                ? 'Nhân viên'
                : 'Kỹ thuật viên';
            const time = document.createElement('span');
            time.textContent = message.time;
            meta.append(sender, time);
            const text = document.createElement('p');
            text.textContent = message.text;
            card.append(meta, text);
            if (typeof message.image_url === 'string' && message.image_url !== '') {
                const image = document.createElement('img');
                image.src = appBasePath + message.image_url;
                image.alt = 'Ảnh khách hàng đính kèm';
                image.loading = 'lazy';
                image.className = 'message-attachment-image';
                card.appendChild(image);
            }
            messageHistory.appendChild(card);
        }

        function createThreadElement(item) {
            const element = document.createElement('div');
            element.className = 'thread-item';
            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'thread-select';
            const topLine = document.createElement('div');
            topLine.className = 'thread-topline';
            const nameTime = document.createElement('span');
            nameTime.className = 'thread-name-time';
            const name = document.createElement('strong');
            const time = document.createElement('small');
            nameTime.append(name, time);
            const badge = document.createElement('span');
            badge.className = 'label-badge';
            topLine.append(nameTime, badge);
            const meta = document.createElement('div');
            meta.className = 'thread-meta';
            const customerType = document.createElement('span');
            customerType.className = 'customer-type-badge';
            const subline = document.createElement('div');
            subline.className = 'thread-subline';
            subline.append(meta, customerType);
            const preview = document.createElement('p');
            const previewRow = document.createElement('div');
            previewRow.className = 'thread-preview-row';
            previewRow.append(preview);
            selectButton.append(topLine, previewRow, subline);
            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'thread-delete-btn';
            deleteButton.textContent = '×';
            deleteButton.setAttribute('aria-label', 'Xóa toàn bộ hội thoại');
            element.append(selectButton, deleteButton);
            updateThreadElement(element, item);
            return element;
        }

        function updateThreadElement(element, item) {
            element.dataset.threadId = item.id;
            element.dataset.displayId = item.display_id;
            element.dataset.channel = item.channel;
            element.dataset.customerType = item.customer_type || 'guest';
            element.dataset.unread = 'false';
            element.dataset.status = item.status;
            const name = element.querySelector('.thread-topline strong');
            const badge = element.querySelector('.thread-topline .label-badge');
            const meta = element.querySelector('.thread-meta');
            const customerType = element.querySelector('.customer-type-badge');
            const preview = element.querySelector('p');
            const time = element.querySelector('.thread-topline small');
            name.textContent = item.customer;
            badge.textContent = item.status;
            badge.className = 'label-badge ' + (item.status_class || badgeStyles[item.status] || 'label-ai');
            meta.textContent = (item.channel === 'website' ? 'Website' : 'Facebook') + ' · ' + item.display_id;
            customerType.textContent = item.customer_type_label || 'Khách vãng lai';
            preview.textContent = item.preview;
            time.textContent = item.time;
        }

        function renderInbox(items, total) {
            const activeMessages = threads.get(activeThreadId)?.messages || [];
            threads.clear();
            const elements = items.map((item) => {
                const merged = Object.assign({}, item, {
                    messages: item.id === activeThreadId ? activeMessages : []
                });
                threads.set(merged.id, merged);
                const element = createThreadElement(merged);
                element.classList.toggle('active', merged.id === activeThreadId);
                return element;
            });
            threadList.replaceChildren(...elements);
            threadCount.textContent = items.length < total ? items.length + ' / ' + total + ' hội thoại' : total + ' hội thoại';
        }

        function renderActiveThread() {
            const item = threads.get(activeThreadId);
            if (!item) {
                detailHeading.textContent = 'Chưa có hội thoại';
                detailMeta.textContent = 'Chưa có hội thoại';
                detailAvatar.textContent = '—';
                detailBadge.textContent = 'Chưa có hội thoại';
                detailBadge.className = 'label-badge label-waiting';
                replyComposer.hidden = true;
                replyInput.disabled = true;
                handoffButton.hidden = true;
                resumeButton.hidden = true;
                hideCustomerTypingIndicator();
                messageHistory.replaceChildren();
                messageHistory.appendChild(typingIndicator);
                renderedMessageSignatures.delete(activeThreadId);
                return;
            }
            threadList.querySelectorAll('.thread-item').forEach((element) => {
                element.classList.toggle('active', element.dataset.threadId === activeThreadId);
            });
            detailHeading.textContent = item.customer;
            detailMeta.textContent = (item.channel === 'website' ? 'Website' : 'Facebook')
                + ' · ' + item.display_id + ' · ' + (item.customer_type_label || 'Khách vãng lai');
            detailAvatar.textContent = item.customer.match(/\d+$/)?.[0] || item.customer.slice(0, 2).toLocaleUpperCase('vi');
            detailBadge.textContent = item.status;
            detailBadge.className = 'label-badge ' + (item.status_class || badgeStyles[item.status] || 'label-ai');
            const isStaffOwned = item.status === 'Kỹ thuật viên tiếp quản';
            replyComposer.hidden = !isStaffOwned;
            replyInput.disabled = !isStaffOwned;
            handoffButton.hidden = isStaffOwned;
            resumeButton.hidden = !isStaffOwned;
            if (!isStaffOwned) stopStaffTyping();
            if (item.channel !== 'website' || customerTypingConversationId !== item.id) {
                hideCustomerTypingIndicator();
            }

            const previousScrollTop = messageHistory.scrollTop;
            const previousScrollHeight = messageHistory.scrollHeight;
            const previousSignature = renderedMessageSignatures.get(activeThreadId) || '';
            const currentSignature = (item.messages || []).map((message) => `${message.id ?? `${message.kind}-${message.time}-${message.text}`}:${message.kind}:${message.text || ''}`).join('|');
            const shouldAutoScroll = previousSignature !== currentSignature && (
                previousSignature === ''
                || messageHistory.scrollHeight - messageHistory.scrollTop - messageHistory.clientHeight < 24
            );

            if (previousSignature !== currentSignature) {
                messageHistory.replaceChildren();
                (item.messages || []).forEach(addMessageElement);
                renderedMessageSignatures.set(activeThreadId, currentSignature);
            }
            if (typingIndicator.parentNode !== messageHistory) messageHistory.appendChild(typingIndicator);

            if (!(item.messages || []).length) {
                messageHistory.scrollTop = 0;
                return;
            }

            if (shouldAutoScroll) {
                messageHistory.scrollTop = messageHistory.scrollHeight;
            } else {
                const preserveOffset = previousScrollHeight - previousScrollTop;
                messageHistory.scrollTop = Math.max(0, messageHistory.scrollHeight - preserveOffset);
            }
        }

        let listRequestId = 0;
        let detailRequestId = 0;
        let lastSyncError = '';
        async function sendStaffTyping(conversationId, typing) {
            try {
                await sendAdminPost('/admin/ai-customer-service/typing', {
                    conversation_id: conversationId,
                    typing
                });
                lastTypingError = '';
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Không thể đồng bộ trạng thái đang soạn tin.';
                if (message !== lastTypingError) {
                    notify(message);
                    lastTypingError = message;
                }
            }
        }

        function hideCustomerTypingIndicator() {
            if (customerTypingHideTimer) {
                window.clearTimeout(customerTypingHideTimer);
                customerTypingHideTimer = 0;
            }
            typingIndicator.classList.remove('is-hiding');
            typingIndicator.classList.add('is-hidden');
            typingIndicator.setAttribute('aria-hidden', 'true');
            typingIndicator.textContent = '';
        }

        function stopStaffTyping() {
            if (staffTypingStopTimer) {
                window.clearTimeout(staffTypingStopTimer);
                staffTypingStopTimer = 0;
            }
            if (staffTypingHeartbeatTimer) {
                window.clearInterval(staffTypingHeartbeatTimer);
                staffTypingHeartbeatTimer = 0;
            }
            const previousConversationId = staffTypingConversationId;
            staffTypingConversationId = '';
            if (previousConversationId) void sendStaffTyping(previousConversationId, false);
        }

        async function refreshCustomerTyping() {
            const item = threads.get(activeThreadId);
            if (!item || item.channel !== 'website') {
                customerTypingConversationId = '';
                hideCustomerTypingIndicator();
                return;
            }
            if (customerTypingConversationId !== item.id) {
                customerTypingConversationId = item.id;
                hideCustomerTypingIndicator();
            }
            try {
                const query = new URLSearchParams({ id: item.id });
                const response = await fetch(appBasePath + '/admin/ai-customer-service/typing?' + query.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không thể đồng bộ trạng thái đang soạn tin.');
                }
                if (activeThreadId !== item.id) return;
                if (result.customer_typing === true) {
                    if (customerTypingHideTimer) {
                        window.clearTimeout(customerTypingHideTimer);
                        customerTypingHideTimer = 0;
                    }
                    typingIndicator.classList.remove('is-hiding');
                    typingIndicator.classList.remove('is-hidden');
                    typingIndicator.setAttribute('aria-hidden', 'false');
                    typingIndicator.textContent = 'Khách đang soạn tin...';
                    if (!typingIndicator.isConnected) messageHistory.appendChild(typingIndicator);
                } else if (!typingIndicator.classList.contains('is-hidden') && !customerTypingHideTimer) {
                    typingIndicator.classList.add('is-hiding');
                    customerTypingHideTimer = window.setTimeout(() => {
                        customerTypingHideTimer = 0;
                        hideCustomerTypingIndicator();
                    }, 1100);
                }
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Không thể đồng bộ trạng thái đang soạn tin.';
                if (message !== lastTypingError) {
                    notify(message);
                    lastTypingError = message;
                }
            }
        }

        async function refreshInbox(quiet = false) {
            const requestId = ++listRequestId;
            const query = new URLSearchParams();
            if (activeFilter === 'website' || activeFilter === 'facebook') {
                query.set('channel', activeFilter);
            } else if (activeFilter !== 'all') {
                const stateByLabel = {
                    'AI đang xử lý': 'ai_active',
                    'Kỹ thuật viên tiếp quản': 'staff_active',
                    'Chờ phản hồi': 'waiting'
                };
                query.set('state', stateByLabel[activeFilter] || '');
            }
            const search = threadSearch.value.trim();
            if (search) query.set('q', search);
            const queryString = query.toString();
            try {
                const response = await fetch(appBasePath + '/admin/ai-customer-service/conversations' + (queryString ? '?' + queryString : ''), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true || !Array.isArray(result.conversations) || !Number.isInteger(result.total)) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không tải được danh sách hội thoại.');
                }
                if (requestId !== listRequestId) return;
                lastSyncError = '';
                const previousActiveThreadId = activeThreadId;
                renderInbox(result.conversations, result.total);
                if (!threads.has(activeThreadId)) {
                    activeThreadId = result.conversations[0]?.id || '';
                }
                if (activeThreadId) {
                    writeStored('ai-customer-service-active-thread', activeThreadId);
                    renderActiveThread();
                } else {
                    renderActiveThread();
                }
                if (activeThreadId && activeThreadId !== previousActiveThreadId) {
                    await loadConversation(activeThreadId, true);
                }
            } catch (error) {
                if (requestId !== listRequestId) return;
                const message = error instanceof Error ? error.message : 'Không tải được danh sách hội thoại.';
                if (!quiet || message !== lastSyncError) notify(message);
                lastSyncError = message;
            }
        }

        async function loadConversation(id, quiet = false) {
            const requestId = ++detailRequestId;
            if (!id) return;
            const query = new URLSearchParams({ id });
            try {
                const response = await fetch(appBasePath + '/admin/ai-customer-service/conversation?' + query.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true || !result.conversation || result.conversation.id !== id || !Array.isArray(result.conversation.messages)) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không tải được nội dung hội thoại.');
                }
                if (requestId !== detailRequestId || activeThreadId !== id) return;
                threads.set(id, result.conversation);
                renderActiveThread();
                lastSyncError = '';
            } catch (error) {
                if (requestId !== detailRequestId || activeThreadId !== id) return;
                const message = error instanceof Error ? error.message : 'Không tải được nội dung hội thoại.';
                if (!quiet || message !== lastSyncError) notify(message);
                lastSyncError = message;
            }
        }

        threadList.addEventListener('click', async (event) => {
            const deleteButton = event.target.closest('.thread-delete-btn');
            const itemElement = event.target.closest('.thread-item');
            if (!itemElement || !threads.has(itemElement.dataset.threadId)) return;
            const item = threads.get(itemElement.dataset.threadId);
            if (deleteButton) {
                if (!window.confirm('Bạn có chắc muốn xóa toàn bộ hội thoại với ' + item.customer + '? Tất cả tin nhắn trong đoạn chat này sẽ bị xóa vĩnh viễn.')) return;
                deleteButton.disabled = true;
                try {
                    await sendAdminPost('/admin/ai-customer-service/conversation/delete', {
                        conversation_id: item.id
                    });
                    if (activeThreadId === item.id) {
                        writeStored('ai-customer-service-active-thread', '');
                        inboxLayout.classList.remove('thread-open');
                    }
                    notify('Đã xóa toàn bộ hội thoại.');
                    await refreshInbox();
                    if (activeThreadId) await loadConversation(activeThreadId);
                } catch (error) {
                    notify(error instanceof Error ? error.message : 'Không thể xóa toàn bộ hội thoại.');
                    deleteButton.disabled = false;
                }
                return;
            }
            if (!event.target.closest('.thread-select')) return;
            stopStaffTyping();
            activeThreadId = itemElement.dataset.threadId;
            if (window.matchMedia('(max-width: 600px)').matches) {
                inboxLayout.classList.add('thread-open');
            }
            writeStored('ai-customer-service-active-thread', activeThreadId);
            renderActiveThread();
            await loadConversation(activeThreadId);
        });
        let searchDebounce;
        threadSearch.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => refreshInbox(), 250);
        });
        document.querySelectorAll('[data-thread-filter]').forEach((chip) => {
            chip.addEventListener('click', () => {
                activeFilter = chip.dataset.threadFilter;
                document.querySelectorAll('[data-thread-filter]').forEach((button) => button.classList.toggle('active', button === chip));
                refreshInbox();
            });
        });

        document.querySelectorAll('[data-thread-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const item = threads.get(activeThreadId);
                if (!item) return;
                const action = button.dataset.threadAction;
                if (action === 'send') {
                    if (item.status !== 'Kỹ thuật viên tiếp quản') {
                        notify('Chỉ có thể phản hồi khi kỹ thuật viên đang tiếp quản hội thoại.');
                        return;
                    }
                    const text = replyInput.value.trim();
                    if (!text) {
                        notify('Nhập nội dung trước khi gửi phản hồi.');
                        replyInput.focus();
                        return;
                    }
                    button.disabled = true;
                    try {
                        stopStaffTyping();
                        await sendAdminPost('/admin/ai-customer-service/conversation/reply', {
                            conversation_id: activeThreadId,
                            message: text
                        });
                        replyInput.value = '';
                        replyInput.style.height = 'auto';
                        notify('Đã lưu phản hồi kỹ thuật viên.');
                        await refreshInbox();
                        await loadConversation(activeThreadId);
                    } catch (error) {
                        notify(error instanceof Error ? error.message : 'Không thể lưu phản hồi.');
                        await loadConversation(activeThreadId, true);
                        await refreshInbox(true);
                    } finally {
                        button.disabled = false;
                    }
                    return;
                }
                if (!['handoff', 'resume'].includes(action)) return;
                button.disabled = true;
                try {
                    const result = await sendAdminPost('/admin/ai-customer-service/conversation/ownership', {
                        conversation_id: activeThreadId,
                        action
                    });
                    stopStaffTyping();
                    notify(result.message);
                    await refreshInbox();
                    await loadConversation(activeThreadId);
                } catch (error) {
                    notify(error instanceof Error ? error.message : 'Không thể cập nhật trạng thái hội thoại.');
                    await loadConversation(activeThreadId, true);
                    await refreshInbox(true);
                } finally {
                    button.disabled = false;
                }
            });
        });

        function updateReplyComposerHeight() {
            replyInput.style.height = 'auto';
            replyInput.style.height = Math.min(replyInput.scrollHeight, 160) + 'px';
        }
        replyInput.addEventListener('input', updateReplyComposerHeight);
        replyInput.addEventListener('input', () => {
            const item = threads.get(activeThreadId);
            if (!item || item.channel !== 'website' || item.status !== 'Kỹ thuật viên tiếp quản' || !replyInput.value.trim()) {
                stopStaffTyping();
                return;
            }
            if (staffTypingConversationId !== item.id) {
                stopStaffTyping();
                staffTypingConversationId = item.id;
                void sendStaffTyping(item.id, true);
                staffTypingHeartbeatTimer = window.setInterval(() => {
                    if (staffTypingConversationId === item.id) void sendStaffTyping(item.id, true);
                }, 1200);
            }
            if (staffTypingStopTimer) window.clearTimeout(staffTypingStopTimer);
            staffTypingStopTimer = window.setTimeout(stopStaffTyping, 1600);
        });
        replyInput.addEventListener('blur', stopStaffTyping);
        replyInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                document.querySelector('[data-thread-action="send"]').click();
            }
        });
        updateReplyComposerHeight();
        refreshInbox().then(() => activeThreadId && loadConversation(activeThreadId, true));
        const inboxRefreshTimer = window.setInterval(async () => {
            await refreshInbox(true);
            if (activeThreadId) await loadConversation(activeThreadId, true);
            await refreshMonitorStatus(true);
        }, 15000);
        const typingRefreshTimer = window.setInterval(refreshCustomerTyping, 1200);
        const cleanupInboxRefresh = () => {
            window.clearInterval(inboxRefreshTimer);
            window.clearInterval(typingRefreshTimer);
            if (customerTypingHideTimer) window.clearTimeout(customerTypingHideTimer);
            stopStaffTyping();
            window.removeEventListener('resize', syncInboxViewport);
            window.visualViewport?.removeEventListener('resize', syncInboxViewport);
            window.visualViewport?.removeEventListener('scroll', syncInboxViewport);
        };
        window.aiCustomerServiceCleanup = cleanupInboxRefresh;
        document.addEventListener('vc:partial-leave', cleanupInboxRefresh, { once: true });

        const runtimeStateLabel = document.querySelector('[data-runtime-state-label]');
        const runtimeStateIndicator = document.querySelector('[data-runtime-state-indicator]');
        const runtimeStatusRow = document.querySelector('[data-runtime-status-row]');
        const handoffRuleLabels = {
            customer_requests_human: 'Khách hàng yêu cầu gặp kỹ thuật viên',
            ai_no_answer: 'AI không tìm thấy câu trả lời phù hợp',
            customer_needs_more_help: 'Khách hàng cần hỗ trợ thêm'
        };
        let lastMonitorError = '';
        function updateRuntime(state) {
            runtimeState = state;
            monitorSettings.runtime_state = state;
            const aiToggle = document.querySelector('[data-monitor-toggle="ai"]');
            aiToggle.checked = state === 'active';
            aiToggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', aiToggle.checked);
            const labels = {
                active: 'Đang hoạt động',
                paused: 'AI tạm dừng'
            };
            runtimeStateLabel.textContent = labels[state];
            runtimeStateIndicator.className = 'dot ' + (state === 'active' ? 'oxygen' : 'neutral');
            runtimeStatusRow.classList.toggle('muted', state === 'paused');
        }
        function renderMonitorRules() {
            const selectedRules = Array.isArray(monitorSettings.handoff_triggers)
                ? monitorSettings.handoff_triggers.filter((rule) => rule in handoffRuleLabels)
                : [];
            const unsupportedRules = selectedRules.filter((rule) => rule !== 'customer_requests_human');
            document.querySelector('[data-monitor-rule="handoff"]').textContent = selectedRules.length
                ? 'Đã lưu: ' + selectedRules.map((rule) => handoffRuleLabels[rule]).join('; ')
                    + (unsupportedRules.length
                        ? '. Lưu ý: một số điều kiện chưa được nối vào luồng xử lý tự động.'
                        : '.')
                : 'Chưa bật điều kiện bàn giao nào. Thiết lập trong Cài đặt AI → Quy tắc.';
            document.querySelector('[data-monitor-rule="ownership"]').textContent = monitorSettings.disable_on_handoff
                ? 'Đang bật: AI không tự trả lời khi kỹ thuật viên tiếp quản hội thoại.'
                : 'Chưa bật khóa phản hồi AI khi kỹ thuật viên tiếp quản.'
            const webhookDetail = monitorMeta.last_webhook_received_at
                ? ' Đã nhận webhook gần nhất: ' + monitorMeta.last_webhook_received_at + '.'
                : ' Chưa ghi nhận sự kiện webhook hợp lệ.';
            const metaDetail = monitorMeta.configured
                ? (monitorMeta.connection_verified
                    ? 'Page ID và Page Access Token đã được Meta xác thực.'
                    : 'Đã lưu đủ thông tin; hãy kiểm tra kết nối Page với Meta.')
                : 'Thiếu: ' + (Array.isArray(monitorMeta.missing_fields) && monitorMeta.missing_fields.length
                    ? monitorMeta.missing_fields.join(', ')
                    : 'thông tin cấu hình Meta') + '.';
            const selectedWebhookEvents = Array.isArray(monitorMeta.webhook_events)
                ? monitorMeta.webhook_events.join(', ')
                : 'messages';
            document.querySelector('[data-monitor-rule="meta"]').textContent = metaDetail
                + ' Danh sách cần bật trong Meta: ' + selectedWebhookEvents + '.'
                + webhookDetail;
            document.querySelector('[data-monitor-provider]').textContent = monitorProvider.configured
                ? (monitorProvider.connection_verified
                    ? 'Đã kiểm tra kết nối ' + (monitorProvider.name || 'provider')
                    : 'Đã cấu hình ' + (monitorProvider.name || 'provider') + (monitorProvider.model_selected ? ' và model · chưa kiểm tra kết nối' : ''))
                : 'Chưa đủ API Key hoặc model cụ thể';
            const overviewProviderStatus = document.querySelector('[data-overview-provider-status]');
            const overviewProviderDot = document.querySelector('[data-overview-provider-dot]');
            if (overviewProviderStatus && overviewProviderDot) {
                overviewProviderStatus.textContent = monitorProvider.connection_verified
                    ? 'Provider AI đã kết nối thành công'
                    : monitorProvider.configured
                        ? 'Provider AI đã đủ cấu hình · chưa kiểm tra kết nối'
                        : 'Provider AI chưa đủ cấu hình để phản hồi';
                overviewProviderDot.className = 'task-dot ' + (monitorProvider.connection_verified
                    ? 'success'
                    : monitorProvider.configured ? 'warning' : 'danger');
            }
            document.querySelector('[data-monitor-meta]').textContent = monitorMeta.connection_verified
                ? 'Kết nối Page với Meta thành công'
                : monitorMeta.configured
                    ? 'Đủ cấu hình · chưa kiểm tra kết nối Meta'
                    : 'Thiếu: ' + (Array.isArray(monitorMeta.missing_fields) && monitorMeta.missing_fields.length
                        ? monitorMeta.missing_fields.join(', ')
                        : 'thông tin cấu hình');
            const overviewMetaStatus = document.querySelector('[data-overview-meta-status]');
            const overviewMetaDot = document.querySelector('[data-overview-meta-dot]');
            const overviewMetaDetail = document.querySelector('[data-overview-meta-detail]');
            if (overviewMetaStatus && overviewMetaDot && overviewMetaDetail) {
                overviewMetaStatus.textContent = monitorMeta.connection_verified
                    ? 'Kết nối Page với Meta thành công'
                    : monitorMeta.configured
                        ? 'Fanpage đủ cấu hình · chưa kiểm tra kết nối Meta'
                        : 'Fanpage chưa đủ thông tin cấu hình';
                overviewMetaDot.className = 'task-dot ' + (monitorMeta.connection_verified
                    ? 'success'
                    : monitorMeta.configured ? 'warning' : 'danger');
                overviewMetaDetail.textContent = monitorMeta.last_webhook_received_at
                    ? 'Đã nhận sự kiện webhook: ' + monitorMeta.last_webhook_received_at
                    : monitorMeta.configured
                        ? 'Kiểm tra Page ID và Page Access Token trong Cài đặt.'
                        : 'Thiếu: ' + (Array.isArray(monitorMeta.missing_fields) && monitorMeta.missing_fields.length
                            ? monitorMeta.missing_fields.join(', ')
                            : 'thông tin cấu hình Meta');
            }
        }
        function renderMonitorCounts(stats) {
            for (const [key, value] of Object.entries(stats)) {
                const element = document.querySelector('[data-monitor-count="' + key + '"]');
                const count = Number(value);
                if (element && Number.isFinite(count) && count >= 0) {
                    element.textContent = new Intl.NumberFormat('vi-VN').format(count);
                }
                const row = document.querySelector('[data-monitor-status="' + key + '"]');
                const dot = document.querySelector('[data-monitor-dot="' + key + '"]');
                if (!row || !dot || !Number.isFinite(count) || count < 0) continue;
                const activeClass = key === 'errors' ? 'danger' : 'warning';
                row.classList.toggle('muted', count === 0);
                row.classList.toggle('warning', count > 0 && activeClass === 'warning');
                row.classList.toggle('danger', count > 0 && activeClass === 'danger');
                dot.className = 'dot ' + (count > 0 ? activeClass : 'neutral');
            }
        }
        async function refreshMonitorStatus(quiet = false) {
            try {
                const response = await fetch(appBasePath + '/admin/ai-customer-service/monitor-status', {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true
                    || !result.settings || !result.stats || !result.provider || !result.meta) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không tải được dữ liệu giám sát.');
                }
                Object.assign(monitorSettings, result.settings);
                monitorMeta = result.meta;
                monitorProvider = result.provider;
                renderMonitorCounts(result.stats);
                updateRuntime(result.settings.runtime_state);
                const webChatToggle = document.querySelector('[data-monitor-toggle="web-chat"]');
                webChatToggle.checked = result.settings.web_chat_enabled;
                webChatToggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', webChatToggle.checked);
                const retentionToggle = document.querySelector('[data-monitor-toggle="retention"]');
                retentionToggle.checked = result.settings.retention_enabled;
                retentionToggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', retentionToggle.checked);
                document.querySelector('[data-retention-note]').textContent = retentionToggle.checked
                    ? 'Tự động xóa đã bật: hội thoại không có tin nhắn mới trong hơn 7 ngày sẽ bị xóa vĩnh viễn khi cron máy chủ chạy.'
                    : 'Tự động xóa đang tắt. Bật công tắc để máy chủ xóa vĩnh viễn hội thoại không có tin nhắn mới trong hơn 7 ngày.';
                renderMonitorRules();
                lastMonitorError = '';
            } catch (error) {
                if (!quiet || (document.getElementById('monitor')?.classList.contains('active') && lastMonitorError === '')) {
                    notify(error instanceof Error ? error.message : 'Không tải được dữ liệu giám sát.');
                }
                lastMonitorError = error instanceof Error ? error.message : 'monitor-error';
            }
        }
        document.querySelectorAll('[data-monitor-toggle]').forEach((toggle) => {
            const type = toggle.dataset.monitorToggle;
            toggle.checked = type === 'ai'
                ? runtimeState === 'active'
                : type === 'web-chat'
                    ? monitorSettings.web_chat_enabled
                    : type === 'retention' && monitorSettings.retention_enabled;
            toggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', toggle.checked);
            toggle.addEventListener('change', async () => {
                if (type === 'retention') {
                    if (toggle.checked && !window.confirm('Bật tự động xóa vĩnh viễn các hội thoại không có tin nhắn mới trong hơn 7 ngày? Thao tác này không thể hoàn tác.')) {
                        toggle.checked = false;
                        toggle.closest('.monitor-toggle-row').classList.remove('is-enabled');
                        return;
                    }
                }
                const nextRuntimeState = type === 'ai'
                    ? (toggle.checked ? 'active' : 'paused')
                    : runtimeState;
                const nextWebChatEnabled = type === 'web-chat'
                    ? toggle.checked
                    : monitorSettings.web_chat_enabled;
                const nextRetentionEnabled = type === 'retention'
                    ? toggle.checked
                    : monitorSettings.retention_enabled;
                toggle.disabled = true;
                if (type === 'ai') {
                    toggle.checked = runtimeState === 'active';
                }
                try {
                    const result = await sendAdminPost('/admin/ai-customer-service/monitor-settings', {
                        runtime_state: nextRuntimeState,
                        web_chat_enabled: nextWebChatEnabled,
                        retention_enabled: nextRetentionEnabled
                    });
                    Object.assign(monitorSettings, result.settings);
                    updateRuntime(result.settings.runtime_state);
                    const webChatToggle = document.querySelector('[data-monitor-toggle="web-chat"]');
                    webChatToggle.checked = result.settings.web_chat_enabled;
                    webChatToggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', webChatToggle.checked);
                    const retentionToggle = document.querySelector('[data-monitor-toggle="retention"]');
                    retentionToggle.checked = result.settings.retention_enabled;
                    retentionToggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', retentionToggle.checked);
                    document.querySelector('[data-retention-note]').textContent = retentionToggle.checked
                        ? 'Tự động xóa đã bật: hội thoại không có tin nhắn mới trong hơn 7 ngày sẽ bị xóa vĩnh viễn khi cron máy chủ chạy.'
                        : 'Tự động xóa đang tắt. Bật công tắc để máy chủ xóa vĩnh viễn hội thoại không có tin nhắn mới trong hơn 7 ngày.';
                    renderMonitorRules();
                    notify(type === 'retention'
                        ? (result.settings.retention_enabled
                            ? 'Đã bật tự động xóa hội thoại quá 7 ngày. Tác vụ chạy theo lịch cron máy chủ.'
                            : 'Đã tắt tự động xóa hội thoại.')
                        : result.message);
                } catch (error) {
                    toggle.checked = type === 'ai'
                        ? runtimeState === 'active'
                        : type === 'web-chat'
                            ? monitorSettings.web_chat_enabled
                            : monitorSettings.retention_enabled;
                    toggle.closest('.monitor-toggle-row').classList.toggle('is-enabled', toggle.checked);
                    notify(error instanceof Error ? error.message : 'Không thể lưu trạng thái vận hành.');
                } finally {
                    toggle.disabled = false;
                }
            });
            renderMonitorRules();
            refreshMonitorStatus();
        });

        const knowledgeGrid = document.querySelector('.knowledge-grid');
        const knowledgeSearch = document.getElementById('knowledge-search');
        const knowledgeStatusFilter = document.getElementById('knowledge-status-filter');
        const knowledgeCount = document.getElementById('knowledge-count');
        const knowledgeDialog = document.getElementById('knowledge-dialog');
        const knowledgeForm = document.getElementById('knowledge-form');
        const knowledgeTitle = document.getElementById('knowledge-dialog-title');
        const knowledgeSave = document.getElementById('knowledge-save-button');
        const knowledgeFetchUrl = document.getElementById('knowledge-fetch-url');
        const knowledgeSourceUrl = document.getElementById('knowledge-source-url');
        const knowledgeFields = knowledgeForm.elements;
        const topicLabels = { faq: 'FAQ', pricing: 'Bảng giá', policy: 'Chính sách', guide: 'Hướng dẫn' };
        let knowledgeItems = <?= json_encode($knowledgeBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        let editingKnowledgeId = null;
        let knowledgeReadOnly = false;

        function renderKnowledge() {
            knowledgeGrid.replaceChildren();
            if (knowledgeItems.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'knowledge-empty';
                empty.textContent = 'Chưa có tài liệu. Hãy thêm nội dung để xây dựng thư viện kiến thức.';
                knowledgeGrid.appendChild(empty);
                knowledgeCount.textContent = '0 tài liệu';
                return;
            }
            const query = knowledgeSearch.value.trim().toLocaleLowerCase('vi');
            const selectedStatus = knowledgeStatusFilter.value;
            let visibleCount = 0;
            knowledgeItems.forEach((item) => {
                const card = document.createElement('article');
                card.className = 'knowledge-item';
                card.dataset.knowledgeId = item.id;
                const matchesQuery = !query || (item.title + ' ' + item.content).toLocaleLowerCase('vi').includes(query);
                const matchesStatus = selectedStatus === 'all' || item.status === selectedStatus;
                card.hidden = !matchesQuery || !matchesStatus;
                if (!card.hidden) visibleCount += 1;
                const top = document.createElement('div');
                top.className = 'knowledge-top';
                const title = document.createElement('strong');
                title.textContent = item.title;
                const status = document.createElement('span');
                status.className = 'label-badge ' + (item.status === 'Đã xuất bản' ? 'label-ai' : item.status === 'Chờ duyệt' ? 'label-waiting' : 'label-policy');
                status.textContent = item.status;
                top.append(title, status);
                const tags = document.createElement('div');
                tags.className = 'tags-row';
                [topicLabels[item.tag] || 'FAQ', 'VPN', 'Hỗ trợ'].forEach((tag) => {
                    const badge = document.createElement('span');
                    badge.className = 'mini-tag';
                    badge.textContent = tag;
                    tags.appendChild(badge);
                });
                const preview = document.createElement('p');
                preview.className = 'knowledge-preview';
                preview.textContent = item.content;
                const actions = document.createElement('div');
                actions.className = 'knowledge-actions';
                [['view', 'Xem'], ['edit', 'Sửa'], ['status', 'Đổi trạng thái'], ['delete', 'Xóa']].forEach(([action, label]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'text-btn small' + (action === 'delete' ? ' danger-text' : '');
                    button.dataset.knowledgeAction = action;
                    button.textContent = label;
                    actions.appendChild(button);
                });
                card.append(top, tags, preview, actions);
                knowledgeGrid.appendChild(card);
            });
            if (visibleCount === 0) {
                const empty = document.createElement('div');
                empty.className = 'knowledge-empty';
                empty.textContent = 'Không tìm thấy tài liệu phù hợp với bộ lọc hiện tại.';
                knowledgeGrid.appendChild(empty);
            }
            knowledgeCount.textContent = visibleCount + ' / ' + knowledgeItems.length + ' tài liệu';
        }
        function showKnowledgeDialog(item, readOnly) {
            editingKnowledgeId = item?.id || null;
            knowledgeReadOnly = readOnly;
            knowledgeTitle.textContent = readOnly ? 'Xem tài liệu' : item ? 'Chỉnh sửa tài liệu' : 'Thêm tài liệu';
            knowledgeFields.title.value = item?.title || '';
            knowledgeFields.tag.value = item?.tag || 'faq';
            knowledgeFields.status.value = item?.status || 'Nháp';
            knowledgeFields.content.value = item?.content || '';
            knowledgeSourceUrl.value = '';
            Array.from(knowledgeForm.querySelectorAll('input, select, textarea')).forEach((field) => { field.disabled = readOnly; });
            knowledgeFetchUrl.disabled = readOnly;
            knowledgeSave.hidden = readOnly;
            knowledgeSave.textContent = item ? 'Lưu thay đổi' : 'Thêm tài liệu';
            knowledgeDialog.showModal();
        }
        document.querySelector('[data-knowledge-action="add"]').addEventListener('click', () => showKnowledgeDialog(null, false));
        knowledgeFetchUrl.addEventListener('click', async () => {
            const url = knowledgeSourceUrl.value.trim();
            if (!url) {
                notify('Nhập đường dẫn HTTPS của trang hướng dẫn cần đọc.');
                return;
            }
            knowledgeFetchUrl.disabled = true;
            try {
                const result = await sendAdminPost('/admin/ai-customer-service/knowledge/fetch-url', { url });
                knowledgeFields.title.value = result.document.title;
                knowledgeFields.content.value = result.document.content;
                if (!knowledgeFields.status.value) knowledgeFields.status.value = 'Nháp';
                notify(result.message);
            } catch (error) {
                notify(error instanceof Error ? error.message : 'Không thể đọc nội dung trang web.');
            } finally {
                knowledgeFetchUrl.disabled = false;
            }
        });
        knowledgeSearch.addEventListener('input', renderKnowledge);
        knowledgeStatusFilter.addEventListener('change', renderKnowledge);
        knowledgeGrid.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-knowledge-action]');
            if (!button) return;
            const card = button.closest('[data-knowledge-id]');
            const item = knowledgeItems.find((entry) => entry.id === card.dataset.knowledgeId);
            if (!item) return;
            const action = button.dataset.knowledgeAction;
            if (action === 'view' || action === 'edit') {
                showKnowledgeDialog(item, action === 'view');
            } else if (action === 'status') {
                const nextStatus = item.status === 'Nháp' ? 'Chờ duyệt' : item.status === 'Chờ duyệt' ? 'Đã xuất bản' : 'Nháp';
                try {
                    const result = await sendAdminPost('/admin/ai-customer-service/knowledge/save', { ...item, status: nextStatus });
                    knowledgeItems = knowledgeItems.map((entry) => entry.id === item.id ? result.item : entry);
                    renderKnowledge();
                    notify(result.message);
                } catch (error) {
                    notify(error instanceof Error ? error.message : 'Không thể cập nhật trạng thái tài liệu.');
                }
            } else if (action === 'delete' && window.confirm('Lưu trữ tài liệu "' + item.title + '"?')) {
                try {
                    const result = await sendAdminPost('/admin/ai-customer-service/knowledge/delete', { id: item.id });
                    knowledgeItems = knowledgeItems.filter((entry) => entry.id !== item.id);
                    renderKnowledge();
                    notify(result.message);
                } catch (error) {
                    notify(error instanceof Error ? error.message : 'Không thể lưu trữ tài liệu.');
                }
            }
        });
        knowledgeForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (knowledgeReadOnly) return;
            const title = knowledgeFields.title.value.trim();
            const content = knowledgeFields.content.value.trim();
            if (!title || !content) {
                notify('Vui lòng nhập tiêu đề và nội dung tài liệu.');
                return;
            }
            const item = {
                id: editingKnowledgeId,
                title,
                tag: knowledgeFields.tag.value,
                status: knowledgeFields.status.value,
                content
            };
            knowledgeSave.disabled = true;
            sendAdminPost('/admin/ai-customer-service/knowledge/save', item).then((result) => {
                const existingIndex = knowledgeItems.findIndex((entry) => entry.id === result.item.id);
                if (existingIndex >= 0) knowledgeItems[existingIndex] = result.item;
                else knowledgeItems.unshift(result.item);
                renderKnowledge();
                knowledgeDialog.close();
                notify(result.message);
            }).catch((error) => {
                notify(error instanceof Error ? error.message : 'Không thể lưu tài liệu kiến thức.');
            }).finally(() => {
                knowledgeSave.disabled = false;
            });
        });
        knowledgeDialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => knowledgeDialog.close()));
        knowledgeDialog.addEventListener('click', (event) => {
            if (event.target === knowledgeDialog) knowledgeDialog.close();
        });
        renderKnowledge();

        const settingsKey = 'ai-customer-service-settings';
        const csrfToken = <?= json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const providerSettings = <?= json_encode([
            'provider' => $aiSettings['provider'] ?? '',
            'model' => $aiSettings['model'] ?? 'auto',
            'endpoint' => $aiSettings['endpoint'] ?? '',
            'temperature' => $aiSettings['temperature'] ?? '0.7',
            'max_tokens' => $aiSettings['max_tokens'] ?? '2048',
            'api_key_configured' => $aiSettings['api_key_configured'] ?? false,
            'meta_app_secret_configured' => $aiSettings['meta_app_secret_configured'] ?? false,
            'verify_token_configured' => $aiSettings['verify_token_configured'] ?? false,
            'page_access_token_configured' => $aiSettings['page_access_token_configured'] ?? false,
            'webhook_events' => $aiSettings['webhook_events'] ?? ['messages'],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const settings = document.querySelectorAll('.setting-panel .field-input, .setting-panel input[type="checkbox"]');
        const storedSettingsValue = readStored(settingsKey, {});
        const storedSettings = storedSettingsValue && typeof storedSettingsValue === 'object' && !Array.isArray(storedSettingsValue)
            ? storedSettingsValue
            : {};
        ['api_key', 'meta_app_secret', 'verify_token', 'page_access_token'].forEach((key) => {
            if (key in storedSettings) {
                delete storedSettings[key];
                writeStored(settingsKey, storedSettings);
            }
        });
        ['provider', 'model', 'endpoint', 'temperature', 'max_tokens', 'meta_app_id', 'meta_page_id'].forEach((key) => {
            if (key in storedSettings) {
                delete storedSettings[key];
                writeStored(settingsKey, storedSettings);
            }
        });
        const activityLog = document.getElementById('settings-activity');
        const activityLogStatus = document.getElementById('settings-activity-status');
        const refreshAuditButton = document.querySelector('[data-refresh-audit]');
        const auditPageSizeInput = document.getElementById('settings-activity-page-size');
        const auditPageRange = document.getElementById('settings-activity-range');
        const auditPageLabel = document.getElementById('settings-activity-page');
        const auditPreviousButton = document.querySelector('[data-audit-page-prev]');
        const auditNextButton = document.querySelector('[data-audit-page-next]');
        let auditEntries = [];
        let auditLoading = false;
        let auditPage = 1;
        let auditPerPage = 20;
        let auditTotal = 0;
        let auditPages = 1;
        function updateAuditPagination() {
            const firstEntry = auditTotal === 0 ? 0 : (auditPage - 1) * auditPerPage + 1;
            const lastEntry = Math.min(auditPage * auditPerPage, auditTotal);
            auditPageRange.textContent = auditTotal === 0
                ? '0 sự kiện'
                : `Hiển thị ${firstEntry}–${lastEntry} trên ${auditTotal} sự kiện`;
            auditPageLabel.textContent = `Trang ${auditPage} / ${auditPages}`;
            auditPreviousButton.disabled = auditLoading || auditPage <= 1;
            auditNextButton.disabled = auditLoading || auditPage >= auditPages;
            auditPageSizeInput.disabled = auditLoading;
        }
        function renderAudit() {
            activityLog.replaceChildren();
            if (auditEntries.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'muted';
                empty.textContent = auditLoading
                    ? 'Đang tải nhật ký từ máy chủ...'
                    : 'Chưa có sự kiện vận hành được ghi nhận.';
                activityLog.appendChild(empty);
                return;
            }
            auditEntries.forEach((entry) => {
                const row = document.createElement('div');
                row.className = 'activity-log-item';
                const time = document.createElement('span');
                time.textContent = entry.time;
                const action = document.createElement('strong');
                action.textContent = entry.action;
                const result = document.createElement('span');
                result.textContent = entry.result;
                row.append(time, action, result);
                activityLog.appendChild(row);
            });
        }
        async function loadAudit(announce = false, requestedPage = auditPage) {
            if (auditLoading) return;
            auditLoading = true;
            refreshAuditButton.disabled = true;
            refreshAuditButton.setAttribute('aria-busy', 'true');
            activityLogStatus.hidden = false;
            activityLogStatus.textContent = 'Đang tải nhật ký cấu hình từ máy chủ...';
            updateAuditPagination();
            renderAudit();
            try {
                const query = new URLSearchParams({
                    page: String(requestedPage),
                    per_page: String(auditPerPage)
                });
                const response = await fetch(appBasePath + '/admin/ai-customer-service/audit?' + query.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true || !Array.isArray(result.entries)
                    || !result.pagination || !Number.isInteger(result.pagination.page)
                    || !Number.isInteger(result.pagination.per_page) || !Number.isInteger(result.pagination.total)
                    || !Number.isInteger(result.pagination.pages)) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không tải được nhật ký cấu hình.');
                }
                auditEntries = result.entries.filter((entry) => entry
                    && typeof entry.action === 'string'
                    && typeof entry.result === 'string'
                    && typeof entry.time === 'string').slice(0, result.pagination.per_page);
                auditPage = result.pagination.page;
                auditPerPage = result.pagination.per_page;
                auditTotal = result.pagination.total;
                auditPages = result.pagination.pages;
                auditPageSizeInput.value = String(auditPerPage);
                activityLogStatus.textContent = `Đang lưu tối đa 60 sự kiện mới nhất trên máy chủ.`;
                if (announce) {
                    notify('Đã làm mới nhật ký cấu hình.');
                }
            } catch (error) {
                const message = error instanceof Error ? error.message : 'Không tải được nhật ký cấu hình.';
                activityLogStatus.textContent = message;
                if (announce) notify(message);
            } finally {
                auditLoading = false;
                refreshAuditButton.disabled = false;
                refreshAuditButton.removeAttribute('aria-busy');
                updateAuditPagination();
                renderAudit();
            }
        }
        function recordAudit() {
            loadAudit();
        }
        refreshAuditButton.addEventListener('click', () => loadAudit(true));
        auditPageSizeInput.addEventListener('change', () => {
            const value = Number(auditPageSizeInput.value);
            if (!Number.isInteger(value) || value < 1 || value > 60) {
                auditPageSizeInput.value = String(auditPerPage);
                notify('Số sự kiện mỗi trang phải từ 1 đến 60.');
                return;
            }
            auditPerPage = value;
            auditPage = 1;
            loadAudit();
        });
        auditPreviousButton.addEventListener('click', () => {
            if (auditPage > 1) loadAudit(false, auditPage - 1);
        });
        auditNextButton.addEventListener('click', () => {
            if (auditPage < auditPages) loadAudit(false, auditPage + 1);
        });
        settings.forEach((field) => {
            if (field.hasAttribute('data-preview-secret')) return;
            if (['provider', 'model', 'endpoint', 'temperature', 'max_tokens'].includes(field.name)) return;
            if (field.name === 'webhook_events[]') {
                field.checked = Array.isArray(providerSettings.webhook_events)
                    && providerSettings.webhook_events.includes(field.value);
                return;
            }
            if (field.name === 'handoff_triggers[]') {
                field.checked = Array.isArray(monitorSettings.handoff_triggers) && monitorSettings.handoff_triggers.includes(field.value);
                return;
            }
            if (field.name === 'disable_on_handoff') {
                field.checked = true;
                return;
            }
            if (field.name === 'webhook_url') return;
            if (!(field.name in storedSettings)) return;
            if (field.type === 'checkbox') field.checked = Boolean(storedSettings[field.name]);
            else if (field.name === 'model' && !Array.from(field.options).some((option) => option.value === storedSettings[field.name])) field.value = 'auto';
            else field.value = storedSettings[field.name];
        });
        const webhookUrlInput = document.getElementById('webhook-callback-url');
        webhookUrlInput.value = window.location.origin + appBasePath + '/webhook/facebook';
        const providerField = document.querySelector('[data-setting-panel="model"] [name="provider"]');
        const modelField = document.querySelector('[data-setting-panel="model"] [name="model"]');
        const endpointField = document.querySelector('[data-setting-panel="model"] [name="endpoint"]');
        const temperatureField = document.querySelector('[data-setting-panel="model"] [name="temperature"]');
        const maxTokensField = document.querySelector('[data-setting-panel="model"] [name="max_tokens"]');
        const apiKeyField = document.querySelector('[data-setting-panel="model"] [name="api_key"]');
        const apiKeyStatus = document.getElementById('provider-key-status');
        providerField.value = providerSettings.provider || '';
        endpointField.value = providerSettings.endpoint || '';
        temperatureField.value = providerSettings.temperature || '0.7';
        maxTokensField.value = providerSettings.max_tokens || '2048';

        let modelRequestId = 0;
        let savedProvider = providerSettings.api_key_configured ? providerSettings.provider : '';
        function updateApiKeyStatus() {
            const isSaved = providerSettings.api_key_configured && providerField.value === savedProvider;
            apiKeyStatus.textContent = isSaved ? 'Đã có key' : 'Chưa có key';
            apiKeyStatus.classList.toggle('is-saved', isSaved);
            apiKeyStatus.hidden = !document.querySelector('[data-setting-panel="model"]').classList.contains('active');
        }
        updateApiKeyStatus();
        async function requestProviderModels(provider, selectedModel = 'auto') {
            const requestId = ++modelRequestId;
            modelField.replaceChildren(new Option('Lưu API Key để tải danh sách model', 'auto'));
            if (!provider || provider !== savedProvider) return;
            modelField.options[0].textContent = 'Đang tải model được cấp quyền…';
            const query = new URLSearchParams({ provider, endpoint: endpointField.value.trim() });
            try {
                const response = await fetch(appBasePath + '/admin/ai-customer-service/provider-models?' + query.toString(), {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || result.ok !== true || !Array.isArray(result.models)) {
                    throw new Error(typeof result.message === 'string' ? result.message : 'Không tải được danh sách model.');
                }
                if (requestId !== modelRequestId) return;
                result.models.forEach((model) => {
                    if (!model || typeof model.id !== 'string' || typeof model.name !== 'string') return;
                    const option = new Option(model.name, model.id);
                    modelField.add(option);
                });
                if (selectedModel !== 'auto' && Array.from(modelField.options).some((option) => option.value === selectedModel)) {
                    modelField.value = selectedModel;
                } else if (modelField.options.length > 1) {
                    modelField.selectedIndex = 1;
                    const permittedModel = modelField.value;
                    if (providerSettings.api_key_configured && selectedModel !== permittedModel) {
                        const panel = document.querySelector('[data-setting-panel="model"]');
                        try {
                            const saved = await sendAdminPost(
                                '/admin/ai-customer-service/provider-settings',
                                { ...currentProviderSettings(panel), model: permittedModel, api_key: '' }
                            );
                            Object.assign(providerSettings, saved.settings);
                            notify('Đã chọn model đầu tiên được provider cấp quyền.');
                            recordAudit();
                        } catch (error) {
                            modelField.value = '';
                            notify(error instanceof Error ? error.message : 'Không thể lưu model được cấp quyền.');
                        }
                    }
                }
            } catch (error) {
                if (requestId !== modelRequestId) return;
                notify(error instanceof Error ? error.message : 'Không tải được danh sách model từ provider.');
            }
        }

        providerField.addEventListener('change', () => {
            endpointField.value = '';
            modelField.value = 'auto';
            updateApiKeyStatus();
            requestProviderModels(providerField.value);
        });
        if (providerSettings.provider && savedProvider === providerSettings.provider) {
            requestProviderModels(providerSettings.provider, providerSettings.model || 'auto');
        }

        async function sendAdminPost(path, payload) {
            const response = await fetch(appBasePath + path, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ ...payload, csrf_token: csrfToken })
            });
            let result;
            try {
                result = await response.json();
            } catch (error) {
                throw new Error('Máy chủ trả về phản hồi không hợp lệ.');
            }
            if (!response.ok || result.ok !== true) {
                throw new Error(typeof result.message === 'string' ? result.message : 'Yêu cầu không thành công.');
            }
            return result;
        }

        document.querySelectorAll('[data-toggle-secret]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = button.closest('.secret-field').querySelector('input');
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-label', (visible ? 'Ẩn ' : 'Hiện ') + input.closest('.field-block').querySelector('label').textContent.trim());
                button.title = button.getAttribute('aria-label');
                button.querySelector('[data-eye-open]').hidden = visible;
                button.querySelector('[data-eye-closed]').hidden = !visible;
            });
        });
        document.querySelectorAll('[data-generate-token]').forEach((button) => {
            button.addEventListener('click', () => {
                const tokenInput = document.querySelector('[name="verify_token"]');
                if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') {
                    notify('Trình duyệt này không hỗ trợ tạo token an toàn.');
                    return;
                }
                try {
                    const bytes = window.crypto.getRandomValues(new Uint8Array(24));
                    tokenInput.value = 'vpn2s_' + Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
                    tokenInput.dispatchEvent(new Event('input', { bubbles: true }));
                    notify('Đã tạo Verify Token mẫu. Hãy sao chép token để nhập vào Meta.');
                } catch (error) {
                    console.error('Không thể tạo Verify Token an toàn.', error);
                    notify('Không tạo được token. Vui lòng thử lại trong trình duyệt hỗ trợ bảo mật.');
                }
            });
        });
        function fallbackCopyText(value) {
            const copyInput = document.createElement('textarea');
            copyInput.value = value;
            copyInput.setAttribute('readonly', '');
            copyInput.style.position = 'fixed';
            copyInput.style.opacity = '0';
            document.body.appendChild(copyInput);
            copyInput.select();
            let copied = false;
            try {
                copied = document.execCommand('copy');
            } finally {
                copyInput.remove();
            }
            return copied;
        }
        document.querySelectorAll('[data-copy-field]').forEach((button) => {
            button.addEventListener('click', async () => {
                const input = document.querySelector('[name="' + button.dataset.copyField + '"]');
                const value = input?.value.trim();
                if (!value) {
                    notify('Chưa có nội dung để sao chép.');
                    return;
                }
                try {
                    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                        await navigator.clipboard.writeText(value);
                    } else if (!fallbackCopyText(value)) {
                        throw new Error('Clipboard API and document copy command are unavailable.');
                    }
                    notify('Đã sao chép ' + (button.dataset.copyField === 'verify_token' ? 'Verify Token.' : 'Webhook Callback URL.'));
                } catch (error) {
                    try {
                        if (fallbackCopyText(value)) {
                            notify('Đã sao chép ' + (button.dataset.copyField === 'verify_token' ? 'Verify Token.' : 'Webhook Callback URL.'));
                            return;
                        }
                    } catch (fallbackError) {
                        console.error('Không thể sao chép trường cấu hình.', fallbackError);
                        notify('Không thể sao chép. Hãy chọn và sao chép nội dung thủ công.');
                        return;
                    }
                    console.error('Không thể sao chép trường cấu hình.', error);
                    notify('Không thể sao chép. Hãy chọn và sao chép nội dung thủ công.');
                }
            });
        });
        const settingsGroupLabels = {
            model: 'cấu hình mô hình',
            fanpage: 'cấu hình Fanpage',
            rules: 'quy tắc'
        };
        function currentProviderSettings(panel) {
            return {
                provider: panel.querySelector('[name="provider"]').value,
                model: panel.querySelector('[name="model"]').value,
                api_key: apiKeyField.value.trim(),
                endpoint: panel.querySelector('[name="endpoint"]').value.trim(),
                temperature: panel.querySelector('[name="temperature"]').value,
                max_tokens: panel.querySelector('[name="max_tokens"]').value
            };
        }
        document.querySelectorAll('[data-settings-action="save"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const panel = button.closest('.setting-panel');
                const group = button.dataset.settingsGroup;
                if (group === 'model') {
                    if (!providerField.value) {
                        notify('Chọn nhà cung cấp trước khi lưu API Key.');
                        return;
                    }
                    if ((!providerSettings.api_key_configured || providerField.value !== savedProvider) && !apiKeyField.value.trim()) {
                        notify('Nhập API Key rồi lưu để tải danh sách model được cấp quyền.');
                        return;
                    }
                    button.disabled = true;
                    try {
                        const response = await sendAdminPost('/admin/ai-customer-service/provider-settings', currentProviderSettings(panel));
                        Object.assign(providerSettings, response.settings);
                        savedProvider = providerSettings.provider;
                        updateApiKeyStatus();
                        apiKeyField.value = '';
                        notify(response.message);
                        recordAudit('Lưu ' + settingsGroupLabels[group], 'Cấu hình được lưu ở server; bí mật không được trả về trình duyệt.');
                        await requestProviderModels(providerField.value, providerSettings.model || 'auto');
                        await refreshMonitorStatus(true);
                    } catch (error) {
                        notify(error instanceof Error ? error.message : 'Không thể lưu cấu hình provider.');
                        recordAudit('Lưu ' + settingsGroupLabels[group], 'Thất bại; không ghi lại dữ liệu bí mật.');
                    } finally {
                        apiKeyField.value = '';
                        button.disabled = false;
                    }
                    return;
                }
                if (group === 'fanpage') {
                    button.disabled = true;
                    const fanpage = panel;
                    try {
                        const response = await sendAdminPost('/admin/ai-customer-service/meta-settings', {
                            meta_app_id: fanpage.querySelector('[name="meta_app_id"]').value.trim(),
                            meta_app_secret: fanpage.querySelector('[name="meta_app_secret"]').value.trim(),
                            meta_page_id: fanpage.querySelector('[name="meta_page_id"]').value.trim(),
                            verify_token: fanpage.querySelector('[name="verify_token"]').value.trim(),
                            page_access_token: fanpage.querySelector('[name="page_access_token"]').value.trim(),
                            webhook_events: Array.from(fanpage.querySelectorAll('[name="webhook_events[]"]:checked'))
                                .map((field) => field.value)
                        });
                        Object.assign(providerSettings, response.settings);
                        fanpage.querySelectorAll('[name="webhook_events[]"]').forEach((field) => {
                            field.checked = providerSettings.webhook_events.includes(field.value);
                        });
                        notify(response.message);
                        recordAudit('Lưu ' + settingsGroupLabels[group], 'Cấu hình được lưu ở server; bí mật không được trả về trình duyệt.');
                        await refreshMonitorStatus(true);
                    } catch (error) {
                        notify(error instanceof Error ? error.message : 'Không thể lưu cấu hình Meta.');
                        recordAudit('Lưu ' + settingsGroupLabels[group], 'Thất bại; không ghi lại dữ liệu bí mật.');
                    } finally {
                        panel.querySelectorAll('[data-preview-secret]').forEach((field) => { field.value = ''; });
                        button.disabled = false;
                    }
                    return;
                }
                if (group === 'rules') {
                    button.disabled = true;
                    try {
                        const response = await sendAdminPost('/admin/ai-customer-service/handoff-rules', {
                            handoff_triggers: Array.from(panel.querySelectorAll('[name="handoff_triggers[]"]:checked'))
                                .map((field) => field.value),
                            disable_on_handoff: panel.querySelector('[name="disable_on_handoff"]').checked
                        });
                        Object.assign(monitorSettings, response.settings);
                        renderMonitorRules();
                        notify(response.message);
                        recordAudit();
                    } catch (error) {
                        notify(error instanceof Error ? error.message : 'Không thể lưu quy tắc bàn giao.');
                    } finally {
                        button.disabled = false;
                    }
                    return;
                }

                const values = Object.assign({}, storedSettings);
                if (group === 'fanpage') values.webhook_events = [];
                if (group === 'rules') values.handoff_triggers = [];
                panel.querySelectorAll('.field-input, input[type="checkbox"]').forEach((field) => {
                    if (field.hasAttribute('data-preview-secret') || field.name === 'webhook_url') return;
                    if (field.name === 'webhook_events[]') {
                        if (field.checked) values.webhook_events.push(field.value);
                        return;
                    }
                    if (field.name === 'handoff_triggers[]') {
                        if (field.checked) values.handoff_triggers.push(field.value);
                        return;
                    }
                    values[field.name] = field.type === 'checkbox' ? field.checked : field.value.trim();
                });
                if (writeStored(settingsKey, values)) {
                    Object.assign(storedSettings, values);
                    panel.querySelectorAll('[data-preview-secret]').forEach((field) => { field.value = ''; });
                    notify('Đã lưu ' + settingsGroupLabels[group] + ' không chứa trường bí mật trong preview.');
                    recordAudit('Lưu ' + settingsGroupLabels[group], 'Chỉ lưu các trường không bí mật thuộc tab này.');
                }
            });
        });
        document.querySelectorAll('[data-settings-action="test"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const panel = button.closest('.setting-panel');
                const group = button.dataset.settingsGroup;
                let valid = false;
                let result = 'Không hợp lệ; không gọi dịch vụ.';
                let message = '';
                if (group === 'model') {
                    const modelSelect = panel.querySelector('[name="model"]');
                    if (!modelSelect.value || modelSelect.value === 'auto') {
                        message = 'Chọn một model cụ thể trước khi kiểm tra kết nối.';
                        recordAudit('Kiểm tra ' + settingsGroupLabels[group], 'Chưa chọn model.');
                        notify(message);
                        return;
                    }
                    button.disabled = true;
                    try {
                        const response = await sendAdminPost('/admin/ai-customer-service/provider-test', currentProviderSettings(panel));
                        notify(response.message);
                        recordAudit('Kiểm tra ' + settingsGroupLabels[group], 'Provider đã phản hồi; nội dung phản hồi không được ghi vào nhật ký.');
                        await refreshMonitorStatus(true);
                    } catch (error) {
                        const errorMessage = error instanceof Error ? error.message : 'Không thể kiểm tra kết nối provider.';
                        notify(errorMessage);
                        recordAudit('Kiểm tra ' + settingsGroupLabels[group], errorMessage);
                    } finally {
                        apiKeyField.value = '';
                        button.disabled = false;
                    }
                    return;
                } else if (group === 'fanpage') {
                    button.disabled = true;
                    try {
                        const response = await sendAdminPost('/admin/ai-customer-service/meta-test', {});
                        notify(response.message);
                        recordAudit('Kiểm tra ' + settingsGroupLabels[group], 'Meta đã xác thực Page ID và Page Access Token; giá trị bí mật không được ghi vào nhật ký.');
                    } catch (error) {
                        const errorMessage = error instanceof Error ? error.message : 'Không thể kiểm tra kết nối Meta.';
                        notify(errorMessage);
                        recordAudit('Kiểm tra ' + settingsGroupLabels[group], errorMessage);
                    } finally {
                        await refreshMonitorStatus(true);
                        button.disabled = false;
                    }
                    return;
                } else if (group === 'rules') {
                    const selectedTriggers = panel.querySelectorAll('[name="handoff_triggers[]"]:checked').length;
                    valid = selectedTriggers > 0 && panel.querySelector('[name="disable_on_handoff"]').checked;
                    message = valid
                        ? 'Quy tắc hợp lệ. Hãy lưu để áp dụng cho chat Website.'
                        : 'Chọn ít nhất một điều kiện chuyển hội thoại cho kỹ thuật viên.';
                    result = valid ? 'Kiểm tra quy tắc bàn giao cục bộ thành công.' : 'Chưa chọn điều kiện bàn giao.';
                }
                notify(message);
                recordAudit('Kiểm tra ' + settingsGroupLabels[group], result);
            });
        });

        updateRuntime(runtimeState, false);
        renderActiveThread();
        loadAudit();
    })();
</script>

<?php
$content = ob_get_clean();
require BASE_PATH . '/resources/views/layouts/admin.php';
?>
