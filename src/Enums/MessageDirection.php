<?php

namespace MaxoTel\Sms\Enums;

enum MessageDirection: string
{
    case Send = 'Send';
    case Receive = 'Receive';
}
