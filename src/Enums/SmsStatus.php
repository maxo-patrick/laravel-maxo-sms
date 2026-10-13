<?php

namespace MaxoTel\Sms\Enums;

enum SmsStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Rejected = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [self::Sent, self::Failed, self::Rejected], true);
    }

    public function isSuccessful(): bool
    {
        return $this === self::Sent;
    }
}
