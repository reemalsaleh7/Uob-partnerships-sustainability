<?php

declare(strict_types=1);

$local = [];

$localPath = __DIR__ . '/email.local.php';

if (is_file($localPath)) {

    $loaded = require $localPath;

    if (!is_array($loaded)) {

        throw new RuntimeException(
            'config/email.local.php must return an array.'
        );
    }

    $local = $loaded;
}

$value = static function (
    string $environmentName,
    string $localName,
    $default = ''
) use ($local) {

    $environmentValue = getenv(
        $environmentName
    );

    if (
        $environmentValue !== false
        && $environmentValue !== ''
    ) {
        return $environmentValue;
    }

    return $local[$localName]
        ?? $default;
};

return [

    'host' => $value(
        'UOB_SMTP_HOST',
        'host',
        'smtp.gmail.com'
    ),

    'port' => (int) $value(
        'UOB_SMTP_PORT',
        'port',
        587
    ),

    'username' => $value(
        'UOB_SMTP_USERNAME',
        'username',
        ''
    ),

    'password' => $value(
        'UOB_SMTP_PASSWORD',
        'password',
        ''
    ),

    'encryption' => $value(
        'UOB_SMTP_ENCRYPTION',
        'encryption',
        'tls'
    ),

    'from_email' => $value(
        'UOB_MAIL_FROM',
        'from_email',
        ''
    ),

    'from_name' => $value(
        'UOB_MAIL_FROM_NAME',
        'from_name',
        'UOB Partnerships & Sustainability'
    ),

    'enabled' => filter_var(
        $value(
            'UOB_MAIL_ENABLED',
            'enabled',
            false
        ),
        FILTER_VALIDATE_BOOLEAN
    ),
];