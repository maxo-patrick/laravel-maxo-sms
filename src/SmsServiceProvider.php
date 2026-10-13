<?php

namespace MaxoTel\Sms;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\ServiceProvider;
use MaxoTel\Sms\Contracts\SmsGateway;
use MaxoTel\Sms\Contracts\SmsHistory;
use MaxoTel\Sms\Contracts\SmsInboxes;
use MaxoTel\Sms\Contracts\SmsSender;
use MaxoTel\Sms\History\DatabaseSmsHistory;
use MaxoTel\Sms\Logging\SmsLogger;
use MaxoTel\Sms\Maxo\ErrorMapper;
use MaxoTel\Sms\Maxo\InboxMessageMapper;
use MaxoTel\Sms\Maxo\MaxoClient;
use MaxoTel\Sms\Maxo\SendResponseMapper;
use MaxoTel\Sms\Notifications\SmsChannel;
use MaxoTel\Sms\Support\ResponseSanitizer;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sms.php', 'sms');

        $this->app->singleton(ResponseSanitizer::class, function (): ResponseSanitizer {
            $keys = array_values(array_filter(
                (array) config('sms.redact', []),
                fn (mixed $key): bool => is_string($key)
            ));

            return new ResponseSanitizer($keys);
        });

        $this->app->singleton(ErrorMapper::class);
        $this->app->singleton(InboxMessageMapper::class);
        $this->app->singleton(SendResponseMapper::class);
        $this->app->singleton(SmsLogger::class);
        $this->app->singleton(MaxoClient::class);
        $this->app->singleton(SmsManager::class);
        $this->app->singleton(SmsSender::class, fn ($app) => $app->make(SmsManager::class));
        $this->app->singleton(SmsGateway::class, fn ($app) => $app->make(SmsManager::class));

        $this->app->singleton(SmsHistory::class, DatabaseSmsHistory::class);
        $this->app->singleton(SmsInboxes::class, MaxoClient::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sms.php' => config_path('sms.php'),
            ], 'sms-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_sms_logs_table.php.stub' => $this->migrationPath(),
            ], 'sms-migrations');
        }

        $this->app->make(ChannelManager::class)->extend('sms', function ($app): SmsChannel {
            return new SmsChannel($app->make(SmsSender::class));
        });
    }

    private function migrationPath(): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_create_sms_logs_table.php");
    }
}
