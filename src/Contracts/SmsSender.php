<?php

namespace MaxoTel\Sms\Contracts;

use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\PendingSms;

interface SmsSender
{
    /**
     * @param  string|array<int, string>  $recipients
     */
    public function to(string|array $recipients): PendingSms;

    public function message(string $body): PendingSms;

    public function connection(?string $name = null): PendingSms;

    public function send(SmsMessage $message): SmsResult;
}
