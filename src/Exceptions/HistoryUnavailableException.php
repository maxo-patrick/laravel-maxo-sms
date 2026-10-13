<?php

namespace MaxoTel\Sms\Exceptions;

class HistoryUnavailableException extends SmsException
{
    public function __construct(string $message = 'SMS history is unavailable because persistence is disabled.')
    {
        parent::__construct($message);
    }
}
