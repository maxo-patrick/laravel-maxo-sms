<?php

namespace MaxoTel\Sms\Notifications;

use MaxoTel\Sms\Contracts\SmsSender;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Data\SmsResultCollection;
use MaxoTel\Sms\PendingSms;

/**
 * @internal
 */
class SmsChannel
{
    public function __construct(private SmsSender $sms) {}

    public function send(object $notifiable, object $notification): ?SmsResult
    {
        if (! method_exists($notification, 'toSms')) {
            return null;
        }

        $message = $notification->toSms($notifiable);

        $pending = $message instanceof PendingSms
            ? $message
            : $this->sms->message((string) $message);

        $recipient = $this->recipient($notifiable);

        if ($recipient !== null) {
            $pending->to($recipient);
        }

        $result = $pending->send();

        if ($result instanceof SmsResultCollection) {
            $result->failed()->each(fn (SmsResult $item) => $item->throw());

            return $result->first();
        }

        return $result->throw();
    }

    private function recipient(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $route = $notifiable->routeNotificationForSms();

            return is_string($route) ? $route : null;
        }

        if (isset($notifiable->phone_number) && is_string($notifiable->phone_number)) {
            return $notifiable->phone_number;
        }

        return null;
    }
}
