<?php

namespace MaxoTel\Sms\Tests;

use MaxoTel\Sms\SmsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SmsServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('sms.default', 'maxo');
        $app['config']->set('sms.from', '0411111111');
        $app['config']->set('sms.connections.maxo.key', 'test-key');
        $app['config']->set('sms.connections.maxo.from', '0411111111');
        $app['config']->set('sms.connections.maxo.base_uri', 'https://myapi.maxo.com.au/');
        $app['config']->set('sms.logging.enabled', false);
        $app['config']->set('sms.pretend', false);
    }
}
