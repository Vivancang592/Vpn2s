<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiKnowledge;
use App\Models\BaseModel;
use PDOException;
use RuntimeException;

final class AiCustomerServiceKnowledgeService
{
    private const CATEGORY_LABELS = [
        'faq' => 'FAQ',
        'pricing' => 'Bảng giá',
        'policy' => 'Chính sách',
        'guide' => 'Hướng dẫn',
    ];

    private const STATUS_LABELS = [
        'draft' => 'Nháp',
        'pending' => 'Chờ duyệt',
        'published' => 'Đã xuất bản',
    ];

    private AiKnowledge $knowledge;

    public function __construct()
    {
        $this->knowledge = new AiKnowledge();
    }

    public function list(): array
    {
        return array_map(
            fn(array $row): array => $this->present($row),
            $this->knowledge->listActive()
        );
    }

    public function pendingCount(): int
    {
        return $this->knowledge->countPending();
    }

    public function fetchFromUrl(string $url): array
    {
        $url = trim($url);
        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('Đường dẫn trang web không được vượt quá 2048 byte.');
        }
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || !isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)
            || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Chỉ hỗ trợ đường dẫn HTTPS công khai, không kèm thông tin đăng nhập.');
        }

        $host = strtolower((string) $parts['host']);
        if ($host === '' || str_ends_with($host, '.') || preg_match('/^[a-z0-9.-]+$/', $host) !== 1) {
            throw new \InvalidArgumentException('Tên miền không hợp lệ.');
        }
        $addresses = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? [$host]
            : (gethostbynamel($host) ?: []);
        if (!filter_var($host, FILTER_VALIDATE_IP) && function_exists('dns_get_record')) {
            $ipv6Records = @dns_get_record($host, DNS_AAAA);
            foreach (is_array($ipv6Records) ? $ipv6Records : [] as $record) {
                if (is_string($record['ipv6'] ?? null)) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }
        if ($addresses === []) {
            throw new RuntimeException('Không thể phân giải địa chỉ IP công khai của trang web.');
        }
        foreach ($addresses as $address) {
            $ipFlags = str_contains($address, ':') ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
            if (filter_var($address, FILTER_VALIDATE_IP, $ipFlags | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new \InvalidArgumentException('Trang web phải phân giải tới địa chỉ IP công khai.');
            }
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Máy chủ chưa bật cURL; không thể tải tài liệu từ trang web.');
        }
        $address = $addresses[0];
        $resolvedAddress = str_contains($address, ':') ? '[' . $address . ']' : $address;
        $body = '';
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Không thể khởi tạo kết nối tới trang web.');
        }
        try {
            $configured = curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_PROXY => '',
                CURLOPT_RESOLVE => [$host . ':443:' . $resolvedAddress],
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain;q=0.9'],
                CURLOPT_USERAGENT => 'VPN2S-KnowledgeImporter/1.0',
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (!$configured) {
                throw new RuntimeException('Không thể cấu hình kết nối tới trang web.');
            }
            $success = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
        } finally {
            curl_close($handle);
        }
        if ($success === false) {
            throw new RuntimeException('Không thể tải trang web trong thời hạn cho phép hoặc nội dung vượt quá 2 MB.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Trang web trả về lỗi HTTP ' . $status . '.');
        }
        if (!str_starts_with($contentType, 'text/html')
            && !str_starts_with($contentType, 'application/xhtml+xml')
            && !str_starts_with($contentType, 'text/plain')) {
            throw new RuntimeException('Địa chỉ này không trả về nội dung trang HTML hoặc văn bản.');
        }

        [$title, $content] = $this->extractPageText($body);
        if ($content === '') {
            throw new RuntimeException('Không trích xuất được nội dung văn bản từ trang web.');
        }
        return [
            'title' => $title !== '' ? $this->truncate($title, 120) : $host,
            'content' => 'Nguồn: ' . $url . "\n\n" . $this->truncate($content, 9000),
        ];
    }

    public function save(array $input, int $adminId): array
    {
        $id = $input['id'] ?? null;
        if ($id !== null && (!is_int($id) && !is_string($id))) {
            throw new \InvalidArgumentException('Mã tài liệu không hợp lệ.');
        }
        $id = $id === null ? 0 : filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new \InvalidArgumentException('Mã tài liệu không hợp lệ.');
        }

        $title = $input['title'] ?? null;
        $content = $input['content'] ?? null;
        $category = $input['tag'] ?? null;
        $statusLabel = $input['status'] ?? null;
        if (!is_string($title) || !is_string($content) || !is_string($category) || !is_string($statusLabel)) {
            throw new \InvalidArgumentException('Thông tin tài liệu không hợp lệ.');
        }

        $title = trim($title);
        $content = trim($content);
        $category = trim($category);
        $status = array_search($statusLabel, self::STATUS_LABELS, true);
        if ($title === '' || strlen($title) > 255 || $content === '' || strlen($content) > 12000
            || str_contains($title, "\0") || str_contains($content, "\0")) {
            throw new \InvalidArgumentException('Tiêu đề phải có tối đa 255 byte và nội dung tối đa 12000 byte.');
        }
        if (!isset(self::CATEGORY_LABELS[$category]) || !is_string($status)) {
            throw new \InvalidArgumentException('Danh mục hoặc trạng thái tài liệu không hợp lệ.');
        }
        if ($adminId < 1) {
            throw new \InvalidArgumentException('Phiên quản trị không hợp lệ.');
        }

        $existing = $id > 0 ? $this->knowledge->findById($id) : null;
        if ($id > 0 && $existing === null) {
            throw new \OutOfBoundsException('Không tìm thấy tài liệu.');
        }

        $slug = $this->slug($title);
        if ($existing !== null && $existing['title'] === $title) {
            $slug = (string) $existing['slug'];
        }
        $data = [
            'title' => $title,
            'slug' => $slug,
            'category' => $category,
            'content' => $content,
            'status' => $status,
            'reviewed_by' => $status === 'published' ? $adminId : null,
            'published_at' => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ];

        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            if ($existing === null) {
                $data['created_by'] = $adminId;
                $id = $this->knowledge->insertKnowledge($data);
            } elseif (!$this->knowledge->update($id, $data)) {
                throw new \OutOfBoundsException('Tài liệu vừa thay đổi hoặc không còn tồn tại.');
            }
            (new AiCustomerServiceAuditService())->record($adminId, 'knowledge_saved');
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
            throw new RuntimeException('Không thể lưu tài liệu kiến thức.', 0, $exception);
        }

        $row = $this->knowledge->findById($id);
        if ($row === null) {
            throw new RuntimeException('Đã lưu tài liệu nhưng không thể tải lại dữ liệu.');
        }
        return $this->present($row);
    }

    public function archive(int $id, int $adminId): void
    {
        if ($id < 1 || $adminId < 1) {
            throw new \InvalidArgumentException('Mã tài liệu không hợp lệ.');
        }
        $pdo = BaseModel::getPdo();
        try {
            $pdo->beginTransaction();
            if (!$this->knowledge->archive($id)) {
                throw new \OutOfBoundsException('Không tìm thấy tài liệu hoặc tài liệu đã được lưu trữ.');
            }
            (new AiCustomerServiceAuditService())->record($adminId, 'knowledge_archived');
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
            throw new RuntimeException('Không thể lưu trữ tài liệu kiến thức.', 0, $exception);
        }
    }

    private function present(array $row): array
    {
        $status = (string) $row['status'];
        if (!isset(self::STATUS_LABELS[$status])) {
            throw new RuntimeException('Tài liệu có trạng thái không được hỗ trợ.');
        }
        $category = (string) ($row['category'] ?? '');

        return [
            'id' => (string) $row['id'],
            'title' => (string) $row['title'],
            'tag' => isset(self::CATEGORY_LABELS[$category]) ? $category : 'faq',
            'status' => self::STATUS_LABELS[$status],
            'content' => (string) $row['content'],
        ];
    }

    private function slug(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', is_string($ascii) ? $ascii : $title), '-'));
        $slug = substr($slug !== '' ? $slug : 'knowledge', 0, 230);
        return $slug . '-' . bin2hex(random_bytes(8));
    }

    private function extractPageText(string $html): array
    {
        if (!class_exists(\DOMDocument::class)) {
            $html = (string) preg_replace('/<(script|style|noscript)\b[^>]*>.*?<\/\1>/is', ' ', $html);
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return ['', trim((string) preg_replace('/\s+/u', ' ', $text))];
        }

        $document = new \DOMDocument();
        $previousErrorMode = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
        if (!$loaded) {
            throw new RuntimeException('Không thể đọc nội dung HTML của trang web.');
        }

        $title = '';
        $titleNodes = $document->getElementsByTagName('title');
        if ($titleNodes->length > 0) {
            $title = trim((string) $titleNodes->item(0)?->textContent);
        }
        foreach (['script', 'style', 'noscript', 'svg', 'nav', 'footer', 'form', 'header'] as $tag) {
            $nodes = $document->getElementsByTagName($tag);
            for ($index = $nodes->length - 1; $index >= 0; $index--) {
                $node = $nodes->item($index);
                $node?->parentNode?->removeChild($node);
            }
        }
        $text = html_entity_decode((string) $document->documentElement?->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\p{Z}\s]+/u', ' ', $text));
        return [$title, $text];
    }

    private function truncate(string $value, int $maxBytes): string
    {
        if (function_exists('mb_strcut')) {
            return mb_strcut($value, 0, $maxBytes, 'UTF-8');
        }
        $truncated = substr($value, 0, $maxBytes);
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $truncated);
        return is_string($clean) ? $clean : '';
    }
}
