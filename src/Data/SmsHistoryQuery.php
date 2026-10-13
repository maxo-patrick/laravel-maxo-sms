<?php

namespace MaxoTel\Sms\Data;

use Carbon\CarbonImmutable;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;

final class SmsHistoryQuery
{
    public function __construct(
        public ?string $recipient = null,
        public ?string $connection = null,
        public ?int $smsInbox = null,
        public ?SmsStatus $status = null,
        public ?SmsErrorType $errorType = null,
        public ?string $reference = null,
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
    ) {}
}
