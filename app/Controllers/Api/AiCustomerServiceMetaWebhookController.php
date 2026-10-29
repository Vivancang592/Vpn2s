<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\AiCustomerServiceMetaService;
use App\Services\AiCustomerServiceAuditService;
use App\Services\AiCustomerServiceWebsiteChatService;
use DomainException;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

final class AiCustomerServiceMetaWebhookController
{
    private AiCustomerServiceMetaService $meta;

    public function __construct()
    {
        $this->meta = new AiCustomerServiceMetaService();
    }

    public function verify(): void
    {
        try {
            $challenge = $this->meta->verifyWebhookHandshake(
                is_string($_GET['hub_mode'] ?? null) ? $_GET['hub_mode'] : null,
                is_string($_GET['hub_verify_token'] ?? null) ? $_GET['hub_verify_token'] : null,
                is_string($_GET['hub_challenge'] ?? null) ? $_GET['hub_challenge'] : null
            );
            $this->respond($challenge, 200);
        } catch (DomainException) {
            $this->respond('Forbidden', 403);
        } catch (RuntimeException) {
            $this->respond('Webhook is not configured', 503);
        }
    }

    public function receive(): void
    {
        $rawBody = file_get_contents('php://input', false, null, 0, 1048577);
        if (!is_string($rawBody) || $rawBody === '' || strlen($rawBody) > 1048576) {
            $this->respond('Invalid payload', 413);
        }
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if (!is_string($signature)) {
            $this->respond('Invalid signature', 403);
        }

        try {
            $result = $this->meta->receiveWebhook($rawBody, $signature);
        } catch (DomainException) {
            $this->respond('Invalid signature', 403);
        } catch (InvalidArgumentException) {
            $this->respond('Invalid payload', 400);
        } catch (RuntimeException) {
            $this->respond('Unable to process event', 503);
        }

        $this->respondAndContinue('EVENT_RECEIVED', 200);
        set_time_limit(0);
        $chat = new AiCustomerServiceWebsiteChatService();
        $audit = new AiCustomerServiceAuditService();
        foreach ($result['conversation_ids'] ?? [] as $conversationId) {
            if (is_int($conversationId) && $conversationId > 0) {
                try {
                    $replyStatus = $chat->respondToUnansweredCustomerMessage($conversationId);
                    if ($replyStatus === 'replied') {
                        $audit->record(null, 'facebook_auto_reply_sent');
                    } elseif (!in_array($replyStatus, ['already_answered', 'no_pending'], true)) {
                        $audit->record(null, 'facebook_auto_reply_skipped');
                    }
                } catch (PDOException | RuntimeException) {
                    $audit->record(null, 'facebook_auto_reply_failed');
                }
            }
        }
    }

    private function respondAndContinue(string $body, int $status): void
    {
        ignore_user_abort(true);
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $body;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }

    private function respond(string $body, int $status): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $body;
        exit;
    }
}
