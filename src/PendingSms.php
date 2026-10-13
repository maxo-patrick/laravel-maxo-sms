<?php

namespace MaxoTel\Sms;

use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Str;
use MaxoTel\Sms\Contracts\SmsGateway;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Data\SmsResultCollection;
use MaxoTel\Sms\Support\AustralianMobile;
use MaxoTel\Sms\Support\MessageEncoding;

class PendingSms
{
    /**
     * @var array<int, string>
     */
    private array $recipients = [];

    private ?string $body = null;

    private ?string $sender = null;

    private ?int $smsInbox = null;

    private ?string $reference = null;

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    private ?string $idempotencyKey = null;

    private ?string $connection = null;

    public function __construct(private SmsGateway $gateway) {}

    /**
     * @param  string|array<int, string>  $recipients
     */
    public function to(string|array $recipients): static
    {
        $this->recipients = array_values(is_array($recipients) ? $recipients : [$recipients]);

        return $this;
    }

    public function from(?string $sender): static
    {
        $this->sender = $sender;

        return $this;
    }

    public function message(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function body(string $body): static
    {
        return $this->message($body);
    }

    public function inbox(?int $smsInbox): static
    {
        $this->smsInbox = $smsInbox;

        return $this;
    }

    public function reference(?string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function idempotencyKey(string $key): static
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function connection(string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    public function segments(): int
    {
        return MessageEncoding::segments($this->body ?? '');
    }

    public function send(): SmsResult|SmsResultCollection
    {
        $messages = $this->messages();

        if (count($messages) === 1) {
            return $this->gateway->send($messages[0]);
        }

        return SmsResultCollection::make(
            array_map(fn (SmsMessage $message): SmsResult => $this->gateway->send($message), $messages)
        );
    }

    public function queue(DateTimeInterface|int|null $delay = null): PendingDispatch|Batch
    {
        return $this->gateway->queueMessages($this->messages(), $delay);
    }

    /**
     * @return array<int, SmsMessage>
     */
    private function messages(): array
    {
        $connection = $this->connection ?? $this->gateway->currentConnection();
        $sender = $this->resolveSender($connection);
        $inbox = $this->resolveInbox($connection);

        return array_map(function (string $recipient) use ($connection, $sender, $inbox): SmsMessage {
            return new SmsMessage(
                uuid: (string) Str::uuid(),
                recipient: AustralianMobile::toE164($recipient) ?? $recipient,
                body: $this->body ?? '',
                sender: $sender,
                smsInbox: $inbox,
                reference: $this->reference,
                metadata: $this->metadata,
                idempotencyKey: $this->idempotencyKey,
                connection: $connection,
            );
        }, $this->recipients);
    }

    private function resolveSender(string $connection): string
    {
        $from = $this->sender
            ?? config("sms.connections.{$connection}.from")
            ?? config('sms.from');

        if (! filled($from)) {
            throw new Exceptions\ConfigurationException(
                "SMS connection [{$connection}] is missing a sender. Set ->from() or sms.from."
            );
        }

        return (string) $from;
    }

    private function resolveInbox(string $connection): ?int
    {
        if ($this->smsInbox !== null) {
            return $this->smsInbox;
        }

        $inbox = config("sms.connections.{$connection}.smsinbox");

        return $inbox !== null && $inbox !== '' ? (int) $inbox : null;
    }
}
