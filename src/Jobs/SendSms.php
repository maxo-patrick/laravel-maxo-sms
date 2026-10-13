<?php

namespace MaxoTel\Sms\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use MaxoTel\Sms\Contracts\SmsSender;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Exceptions\SmsSendException;

/**
 * @internal
 */
class SendSms implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(
        public SmsMessage $message,
        public string $connectionName,
    ) {
        $this->tries = (int) config('sms.queue.tries', 1);

        $queueConnection = config('sms.queue.connection');
        if (is_string($queueConnection) && $queueConnection !== '') {
            $this->onConnection($queueConnection);
        }

        $queue = config('sms.queue.queue');
        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(SmsSender $sms): void
    {
        $result = $sms->send($this->message);

        if (! $result->successful) {
            $this->fail(new SmsSendException($result));
        }
    }
}
