<?php

namespace MaxoTel\Sms\Maxo;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use Throwable;

/**
 * @internal
 */
final class ErrorMapper
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{type: SmsErrorType, status: SmsStatus, error: ?string, reason: ?string}
     */
    public function fromPayload(array $payload, int $httpStatus): array
    {
        $error = isset($payload['error']) ? (string) $payload['error'] : null;
        $reason = isset($payload['reason']) ? (string) $payload['reason'] : null;

        $type = match ($httpStatus) {
            401, 403 => SmsErrorType::AuthenticationError,
            404 => SmsErrorType::ConfigurationError,
            400 => SmsErrorType::MessageRejected,
            503 => SmsErrorType::ProviderUnavailable,
            500, 502, 504 => SmsErrorType::IndeterminateError,
            default => $this->fromErrorString($error, $reason),
        };

        if ($httpStatus === 200 || $httpStatus === 400) {
            $type = $this->fromErrorString($error, $reason);
        }

        $status = $type === SmsErrorType::MessageRejected || $type === SmsErrorType::InvalidRecipient || $type === SmsErrorType::InvalidSender
            ? SmsStatus::Rejected
            : SmsStatus::Failed;

        return [
            'type' => $type,
            'status' => $status,
            'error' => $error,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{type: SmsErrorType, status: SmsStatus, error: ?string, reason: ?string}
     */
    public function fromException(Throwable $exception): array
    {
        if ($exception instanceof RequestException) {
            $payload = $exception->response->json();

            return $this->fromPayload(is_array($payload) ? $payload : [], $exception->response->status());
        }

        if ($exception instanceof ConnectionException) {
            return [
                'type' => $this->classifyConnection($exception),
                'status' => SmsStatus::Failed,
                'error' => null,
                'reason' => $this->safeMessage($exception),
            ];
        }

        return [
            'type' => SmsErrorType::UnknownError,
            'status' => SmsStatus::Failed,
            'error' => null,
            'reason' => $this->safeMessage($exception),
        ];
    }

    private function fromErrorString(?string $error, ?string $reason): SmsErrorType
    {
        $haystack = strtolower(trim(($error ?? '').' '.($reason ?? '')));

        if ($error !== null && strcasecmp($error, 'VALIDATION') === 0) {
            if ($this->mentionsDestination($haystack)) {
                return SmsErrorType::InvalidRecipient;
            }

            if ($this->mentionsOrigin($haystack)) {
                return SmsErrorType::InvalidSender;
            }

            return SmsErrorType::MessageRejected;
        }

        if ($this->mentionsDestination($haystack)) {
            return SmsErrorType::InvalidRecipient;
        }

        if ($this->mentionsOrigin($haystack)) {
            return SmsErrorType::InvalidSender;
        }

        return $error === null && $reason === null
            ? SmsErrorType::UnknownError
            : SmsErrorType::MessageRejected;
    }

    private function mentionsDestination(string $haystack): bool
    {
        return str_contains($haystack, 'destination')
            || str_contains($haystack, 'international')
            || str_contains($haystack, 'mobile number')
            || str_contains($haystack, 'to number');
    }

    private function mentionsOrigin(string $haystack): bool
    {
        return str_contains($haystack, 'origin')
            || str_contains($haystack, 'sender')
            || str_contains($haystack, 'from');
    }

    private function classifyConnection(ConnectionException $exception): SmsErrorType
    {
        $message = strtolower($exception->getMessage());

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout') || str_contains($message, 'curl error 28')) {
            if (str_contains($message, 'connection timed out') || str_contains($message, 'connect timed out') || str_contains($message, 'failed to connect')) {
                return SmsErrorType::NetworkError;
            }

            return SmsErrorType::IndeterminateError;
        }

        if (
            str_contains($message, 'could not resolve')
            || str_contains($message, 'name or service not known')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'couldn\'t connect')
            || str_contains($message, 'failed to connect')
            || str_contains($message, 'ssl')
            || str_contains($message, 'tls')
        ) {
            return SmsErrorType::NetworkError;
        }

        return SmsErrorType::NetworkError;
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $message = (string) preg_replace('/key=[^&\s]+/i', 'key=[REDACTED]', $message);

        return $message;
    }
}
