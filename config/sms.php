<?php

use MaxoTel\Sms\Models\SmsLog;

return [

    // Name of a connection below.
    'default' => env('SMS_CONNECTION', 'maxo'),

    // Global fallback sender — see 11.4.
    'from' => env('SMS_FROM'),

    // When true, messages are logged instead of sent. See 11.5.
    'pretend' => env('SMS_PRETEND', false),

    /*
     | Named connections, in the style of config/mail.php. Several connections may
     | exist against the same API — a sandbox key and a production key, or separate
     | keys per business unit.
     */
    'connections' => [

        'maxo' => [
            'key' => env('MAXO_SMS_KEY'),
            'base_uri' => env('MAXO_SMS_BASE_URI', 'https://myapi.maxo.com.au/'),
            'from' => env('MAXO_SMS_FROM'),
            'smsinbox' => env('MAXO_SMS_INBOX'),
            'timeout' => env('MAXO_SMS_TIMEOUT', 10),
            'connect_timeout' => env('MAXO_SMS_CONNECT_TIMEOUT', 10),
        ],

        'maxo-sandbox' => [
            'key' => env('MAXO_SMS_SANDBOX_KEY'),
            'base_uri' => env('MAXO_SMS_BASE_URI', 'https://myapi.maxo.com.au/'),
            'from' => env('MAXO_SMS_SANDBOX_FROM'),
            'smsinbox' => env('MAXO_SMS_SANDBOX_INBOX'),
            'timeout' => env('MAXO_SMS_SANDBOX_TIMEOUT', 10),
            'connect_timeout' => env('MAXO_SMS_SANDBOX_CONNECT_TIMEOUT', 10),
        ],
    ],

    'queue' => [
        'connection' => env('SMS_QUEUE_CONNECTION'),
        'queue' => env('SMS_QUEUE', 'default'),
        'tries' => 1,
    ],

    'logging' => [
        'enabled' => env('SMS_LOG_MESSAGES', false),
        'store_body' => env('SMS_STORE_BODY', false),
        'store_provider_response' => env('SMS_STORE_PROVIDER_RESPONSE', false),
        'table' => 'sms_logs',
        'connection' => env('SMS_LOG_CONNECTION'),
        'model' => SmsLog::class,
        'retain_for_days' => env('SMS_RETAIN_DAYS', 90),
    ],

    'log_channel' => [
        'enabled' => env('SMS_LOG_CHANNEL_ENABLED', false),
        'channel' => env('SMS_LOG_CHANNEL'),
        'level' => 'info',
    ],

    'redact' => ['key', 'authorization', 'password', 'secret', 'token'],
];
