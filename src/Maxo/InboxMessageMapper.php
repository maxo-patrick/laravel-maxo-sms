<?php

namespace MaxoTel\Sms\Maxo;

use Carbon\CarbonImmutable;
use MaxoTel\Sms\Data\InboxMessage;
use MaxoTel\Sms\Data\SmsInbox;
use MaxoTel\Sms\Enums\MessageDirection;
use MaxoTel\Sms\Support\AustralianMobile;

/**
 * @internal
 */
final class InboxMessageMapper
{
    /**
     * @param  array<string, mixed>  $inbox
     */
    public function mapInbox(array $inbox): SmsInbox
    {
        return new SmsInbox(
            id: (int) ($inbox['smsinbox'] ?? 0),
            name: (string) ($inbox['name'] ?? ''),
            senderId: (string) ($inbox['senderid'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function mapMessage(array $message): InboxMessage
    {
        $direction = ($message['direction'] ?? 'Send') === 'Receive'
            ? MessageDirection::Receive
            : MessageDirection::Send;

        return new InboxMessage(
            smsId: (int) ($message['smsid'] ?? 0),
            smsInbox: (int) ($message['smsinbox'] ?? 0),
            sentAt: CarbonImmutable::createFromTimestamp((int) ($message['unixtime'] ?? time())),
            contact: AustralianMobile::toE164((string) ($message['contact'] ?? '')) ?? (string) ($message['contact'] ?? ''),
            direction: $direction,
            origin: $this->normalizeEndpoint((string) ($message['origin'] ?? '')),
            destination: AustralianMobile::toE164((string) ($message['destination'] ?? '')) ?? (string) ($message['destination'] ?? ''),
            status: (string) ($message['status'] ?? ''),
            read: (int) ($message['read'] ?? 0) === 1,
            cost: (float) ($message['cost'] ?? 0),
            body: (string) ($message['message'] ?? ''),
        );
    }

    private function normalizeEndpoint(string $value): string
    {
        return AustralianMobile::toE164($value) ?? $value;
    }
}
