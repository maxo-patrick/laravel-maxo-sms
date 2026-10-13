<?php

namespace MaxoTel\Sms\Enums;

enum SmsErrorType: string
{
    case AuthenticationError = 'authentication_error';
    case InvalidRecipient = 'invalid_recipient';
    case InvalidSender = 'invalid_sender';
    case MessageRejected = 'message_rejected';
    case ConfigurationError = 'configuration_error';
    case NetworkError = 'network_error';
    case ProviderUnavailable = 'provider_unavailable';
    case IndeterminateError = 'indeterminate_error';
    case UnknownError = 'unknown_error';
}
