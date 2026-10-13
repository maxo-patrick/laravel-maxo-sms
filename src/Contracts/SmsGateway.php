<?php

namespace MaxoTel\Sms\Contracts;

use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Bus\PendingDispatch;
use MaxoTel\Sms\Data\SmsMessage;

/**
 * @internal
 */
interface SmsGateway extends SmsSender
{
    /**
     * @param  array<int, SmsMessage>  $messages
     */
    public function queueMessages(array $messages, DateTimeInterface|int|null $delay = null): PendingDispatch|Batch;

    public function currentConnection(): string;
}
