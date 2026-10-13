<?php

namespace MaxoTel\Sms\Events;

use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;

class SmsFailed
{
    public function __construct(
        public SmsMessage $message,
        public SmsResult $result,
    ) {}
}
