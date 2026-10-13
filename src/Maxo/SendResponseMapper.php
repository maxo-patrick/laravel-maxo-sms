<?php

namespace MaxoTel\Sms\Maxo;

use MaxoTel\Sms\Data\SmsMessage;
use MaxoTel\Sms\Data\SmsResult;
use MaxoTel\Sms\Enums\SmsErrorType;
use MaxoTel\Sms\Enums\SmsStatus;
use MaxoTel\Sms\Support\ResponseSanitizer;

/**
 * @internal
 */
final class SendResponseMapper
{
    public function __construct(
        private ErrorMapper $errors,
        private ResponseSanitizer $sanitizer,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function map(SmsMessage $message, int $httpStatus, array $payload): SmsResult
    {
        $sanitized = $this->sanitizer->sanitize($payload);
        $accepted = $httpStatus === 200 && (int) ($payload['status'] ?? 0) === 1;

        if ($accepted) {
            return SmsResult::accepted(
                message: $message,
                smsId: (int) $payload['smsid'],
                parts: isset($payload['parts']) ? (int) $payload['parts'] : null,
                price: isset($payload['price']) ? (float) $payload['price'] : null,
                providerResponse: $sanitized,
            );
        }

        $mapped = $this->errors->fromPayload($payload, $httpStatus);

        return SmsResult::failure(
            message: $message,
            errorType: $mapped['type'],
            status: $mapped['status'],
            errorMessage: $mapped['reason'] ?? $mapped['error'],
            providerError: $mapped['error'],
            providerReason: $mapped['reason'],
            providerResponse: $sanitized,
        );
    }

    public function invalidRecipient(SmsMessage $message): SmsResult
    {
        return SmsResult::failure(
            message: $message,
            errorType: SmsErrorType::InvalidRecipient,
            status: SmsStatus::Failed,
            errorMessage: 'Recipient is not a valid Australian mobile number.',
        );
    }
}
