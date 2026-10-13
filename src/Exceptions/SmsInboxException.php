<?php

namespace MaxoTel\Sms\Exceptions;

use MaxoTel\Sms\Enums\SmsErrorType;
use Throwable;

class SmsInboxException extends SmsException
{
    public function __construct(
        string $message,
        public readonly SmsErrorType $errorType,
        public readonly int $httpStatus,
        public readonly ?string $providerError = null,
        public readonly ?string $providerReason = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
