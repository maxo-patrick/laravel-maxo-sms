<?php

namespace MaxoTel\Sms\Facades;

use Illuminate\Support\Facades\Facade;
use MaxoTel\Sms\Contracts\SmsGateway;
use MaxoTel\Sms\Contracts\SmsSender;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\PendingSms;
use MaxoTel\Sms\SmsManager;
use MaxoTel\Sms\Support\SmsFake;

/**
 * @method static PendingSms to(string|array<int, string> $recipients)
 * @method static PendingSms message(string $body)
 * @method static PendingSms connection(?string $name = null)
 * @method static \MaxoTel\Sms\Data\SmsResult send(SmsMessage $message)
 *
 * @see SmsManager
 */
class Sms extends Facade
{
    public static function fake(): SmsFake
    {
        $fake = new SmsFake(static::$app['events']);
        static::$app->instance(SmsSender::class, $fake);
        static::$app->instance(SmsGateway::class, $fake);
        static::swap($fake);

        return $fake;
    }

    public static function shouldFail(SmsErrorType $type): SmsFake
    {
        return self::currentFake()->shouldFail($type);
    }

    public static function shouldFailFor(string $recipient, SmsErrorType $type): SmsFake
    {
        return self::currentFake()->shouldFailFor($recipient, $type);
    }

    public static function respondWith(callable $callback): SmsFake
    {
        return self::currentFake()->respondWith($callback);
    }

    public static function assertSent(callable|string|null $callback = null): void
    {
        self::currentFake()->assertSent($callback);
    }

    public static function assertSentTo(string $recipient, ?callable $callback = null): void
    {
        self::currentFake()->assertSentTo($recipient, $callback);
    }

    public static function assertNotSentTo(string $recipient): void
    {
        self::currentFake()->assertNotSentTo($recipient);
    }

    public static function assertNothingSent(): void
    {
        self::currentFake()->assertNothingSent();
    }

    public static function assertSentCount(int $count): void
    {
        self::currentFake()->assertSentCount($count);
    }

    public static function assertQueued(?callable $callback = null): void
    {
        self::currentFake()->assertQueued($callback);
    }

    public static function assertNothingQueued(): void
    {
        self::currentFake()->assertNothingQueued();
    }

    protected static function getFacadeAccessor(): string
    {
        return SmsSender::class;
    }

    private static function currentFake(): SmsFake
    {
        $instance = static::getFacadeRoot();

        if (! $instance instanceof SmsFake) {
            return static::fake();
        }

        return $instance;
    }
}
