<?php

namespace MaxoTel\Sms\Events;

use MaxoTel\Sms\Data\SmsMessage;

class SmsQueued
{
    public function __construct(
        public SmsMessage $message,
        public string $connection,
    ) {}
}
