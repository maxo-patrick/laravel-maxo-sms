<?php

use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use MaxoTel\Sms\Exceptions\SmsSendException;
use MaxoTel\Sms\Facades\Sms;
use MaxoTel\Sms\PendingSms;

function smsNotifiable(): object
{
    return new class
    {
        use Notifiable;

        public string $phone_number = '0412345678';
    };
}

function smsNotification(): Notification
{
    return new class extends Notification
    {
        /**
         * @return list<string>
         */
        public function via(object $notifiable): array
        {
            return ['sms'];
        }

        public function toSms(object $notifiable): PendingSms
        {
            return Sms::message('Your order has shipped');
        }
    };
}

it('sends a laravel notification through the sms channel', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 1,
            'smsid' => 5,
            'parts' => 1,
            'price' => 0.1,
        ], 200),
    ]);

    NotificationFacade::send(smsNotifiable(), smsNotification());

    Http::assertSentCount(1);
});

it('throws when a notification send fails', function () {
    Http::fake([
        'https://myapi.maxo.com.au/sms/send' => Http::response([
            'status' => 0,
            'error' => 'VALIDATION',
            'reason' => 'Sending to International numbers is not supported',
        ], 200),
    ]);

    NotificationFacade::send(smsNotifiable(), smsNotification());
})->throws(SmsSendException::class);
