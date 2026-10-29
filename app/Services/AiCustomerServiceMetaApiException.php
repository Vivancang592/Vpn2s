<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AiCustomerServiceMetaApiException extends RuntimeException
{
    public function __construct(
        int $httpStatus,
        public readonly ?int $graphErrorCode,
        public readonly ?int $graphErrorSubcode,
        public readonly ?string $graphErrorSummary,
        public readonly ?string $fbtraceId
    ) {
        parent::__construct('Meta từ chối yêu cầu gửi tin.', $httpStatus);
    }
}
