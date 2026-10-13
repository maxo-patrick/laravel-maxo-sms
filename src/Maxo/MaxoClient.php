<?php

namespace MaxoTel\Sms\Maxo;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use MaxoTel\Sms\Contracts\SmsInboxes;
use MaxoTel\Sms\Data\InboxMessageQuery;
use MaxoTel\Sms\Data\SmsInbox;
use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Exceptions\ConfigurationException;
use MaxoTel\Sms\Exceptions\SmsInboxException;
use MaxoTel\Sms\Support\AustralianMobile;
use Throwable;

/**
 * @internal
 */
final class MaxoClient implements SmsInboxes
{
    public function __construct(
        private HttpFactory $http,
        private SendResponseMapper $sendMapper,
        private ErrorMapper $errorMapper,
        private InboxMessageMapper $inboxMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $connection
     */
    public function send(SmsMessage $message, array $connection): SmsResult
    {
        $this->assertCredentials($connection, $message->connection);

        $url = $this->url($connection, $message->smsInbox === null
            ? 'sms/send'
            : 'sms/'.$message->smsInbox.'/send');

        $payload = [
            'key' => $connection['key'],
            'origin' => $message->sender,
            'destination' => AustralianMobile::toLocal04($message->recipient),
            'message' => $message->body,
        ];

        try {
            $response = $this->post($connection, $url, $payload);

            return $this->sendMapper->map($message, $response->status(), $response->json() ?? []);
        } catch (Throwable $exception) {
            $mapped = $this->errorMapper->fromException($exception);

            return SmsResult::failure(
                message: $message,
                errorType: $mapped['type'],
                status: $mapped['status'],
                errorMessage: $mapped['reason'] ?? $mapped['error'],
                providerError: $mapped['error'],
                providerReason: $mapped['reason'],
            );
        }
    }

    public function all(?string $connection = null): Collection
    {
        $name = $connection ?? (string) config('sms.default');
        $config = $this->connectionConfig($name);
        $this->assertCredentials($config, $name);

        $response = $this->inbox($config, $this->url($config, 'sms/list'), [
            'key' => $config['key'],
        ]);

        /** @var list<array<string, mixed>> $inboxes */
        $inboxes = $response->json('smsinboxes') ?? [];

        return Collection::make($inboxes)
            ->map(fn (array $inbox): SmsInbox => $this->inboxMapper->mapInbox($inbox))
            ->values();
    }

    public function messages(int $smsInbox, InboxMessageQuery $query, ?string $connection = null): Collection
    {
        $name = $connection ?? (string) config('sms.default');
        $config = $this->connectionConfig($name);
        $this->assertCredentials($config, $name);

        $payload = $this->messagePayload($config, $query);

        $response = $this->inbox($config, $this->url($config, 'sms/'.$smsInbox.'/messages'), $payload);
        /** @var list<array<string, mixed>> $messages */
        $messages = $response->json('messages') ?? [];

        return Collection::make($messages)
            ->map(fn (array $message) => $this->inboxMapper->mapMessage($message))
            ->values();
    }

    public function messagesCsv(int $smsInbox, InboxMessageQuery $query, bool $headings = true, ?string $connection = null): string
    {
        $name = $connection ?? (string) config('sms.default');
        $config = $this->connectionConfig($name);
        $this->assertCredentials($config, $name);

        $payload = $this->messagePayload($config, $query);
        $payload['outputFormat'] = 'csv';

        if ($headings) {
            $payload['showHeadings'] = 1;
        }

        $response = $this->inbox($config, $this->url($config, 'sms/'.$smsInbox.'/messages'), $payload, false);

        return $response->body();
    }

    /**
     * @return array<string, mixed>
     */
    public function connectionConfig(string $name): array
    {
        $config = config('sms.connections.'.$name);

        if (! is_array($config)) {
            throw new ConfigurationException("SMS connection [{$name}] is not defined.");
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, mixed>  $payload
     */
    private function post(array $connection, string $url, array $payload, bool $acceptJson = true): Response
    {
        $pending = $this->http
            ->asForm()
            ->timeout((int) ($connection['timeout'] ?? 10))
            ->connectTimeout((int) ($connection['connect_timeout'] ?? 5));

        if ($acceptJson) {
            $pending = $pending->acceptJson();
        }

        return $pending->post($url, $payload);
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, mixed>  $payload
     */
    private function inbox(array $connection, string $url, array $payload, bool $acceptJson = true): Response
    {
        try {
            $response = $this->post($connection, $url, $payload, $acceptJson);
        } catch (Throwable $exception) {
            $mapped = $this->errorMapper->fromException($exception);

            throw new SmsInboxException(
                $mapped['reason'] ?? $mapped['error'] ?? 'The SMS inbox request failed.',
                $mapped['type'],
                $exception instanceof RequestException ? $exception->response->status() : 0,
                $mapped['error'],
                $mapped['reason'],
                $exception,
            );
        }

        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : [];
        $jsonError = str_contains(strtolower((string) $response->header('Content-Type')), 'json')
            && (int) ($body['status'] ?? 0) !== 1;
        $accepted = $acceptJson
            ? $response->status() === 200 && (int) ($body['status'] ?? 0) === 1
            : $response->status() === 200 && ! $jsonError;

        if (! $accepted) {
            $mapped = $this->errorMapper->fromPayload($body, $response->status());

            throw new SmsInboxException(
                $mapped['reason'] ?? $mapped['error'] ?? 'The SMS inbox request failed.',
                $mapped['type'],
                $response->status(),
                $mapped['error'],
                $mapped['reason'],
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return array<string, mixed>
     */
    private function messagePayload(array $connection, InboxMessageQuery $query): array
    {
        $payload = [
            'key' => $connection['key'],
            'startTime' => $query->startTimestamp(),
            'endTime' => $query->endTimestamp(),
        ];

        if ($query->contact !== null) {
            $e164 = AustralianMobile::toE164($query->contact);
            $payload['contact'] = $e164 !== null ? AustralianMobile::toLocal04($e164) : $query->contact;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function url(array $connection, string $path): string
    {
        $base = rtrim((string) ($connection['base_uri'] ?? ''), '/');

        if ($base === '') {
            throw new ConfigurationException('SMS connection is missing [base_uri].');
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function assertCredentials(array $connection, string $name): void
    {
        if (! filled($connection['key'] ?? null)) {
            throw new ConfigurationException("SMS connection [{$name}] is missing [key].");
        }

        if (! filled($connection['base_uri'] ?? null)) {
            throw new ConfigurationException("SMS connection [{$name}] is missing [base_uri].");
        }
    }
}
