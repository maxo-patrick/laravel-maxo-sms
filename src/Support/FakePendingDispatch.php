<?php

namespace MaxoTel\Sms\Support;

use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * @internal
 */
final class FakePendingDispatch extends PendingDispatch
{
    public function __destruct()
    {
        // The fake records the queue intent without dispatching a real job.
    }
}
