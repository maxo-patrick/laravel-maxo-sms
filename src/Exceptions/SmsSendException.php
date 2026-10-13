<?php

namespace MaxoTel\Sms\Exceptions;

use MaxoTel\Sms\Data\SmsResult;

class SmsSendException extends SmsException
{
    public function __construct(public readonly SmsResult $result)
    {
        parent::__construct($result->errorMessage ?? 'SMS send failed.');
    }
}
