<?php

namespace MaxoTel\Sms\Events;

use MaxoTel\Sms\Data\SmsMessage;

class SmsSending
{
    public function __construct(
        public SmsMessage $message,
        public string $connection,
    ) {}
}
