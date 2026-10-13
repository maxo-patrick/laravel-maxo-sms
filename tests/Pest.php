<?php

use MaxoTel\Sms\Tests\TestCase;

function migrateSmsLogs(): void
{
    $migration = require dirname(__DIR__).'/database/migrations/create_sms_logs_table.php.stub';
    $migration->up();
}

uses(TestCase::class)->in(__DIR__);
