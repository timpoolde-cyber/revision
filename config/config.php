<?php
/**
 * REVISION100™ — SYSTEM-KONFIGURATION (ISOLIERT)
 * WARNUNG: Diese Datei enthält sensitive Credentials.
 * NICHT in Versionskontrolle committen (.gitignore)!
 */
return [
    'smtp' => [
        'host' => getenv('SMTP_HOST'),
        'user' => getenv('SMTP_USER'),
        'pass' => getenv('SMTP_PASS'),
        'port' => getenv('SMTP_PORT') ?: 587,
        'auth' => true,
        'secure' => 'tls'  // PHPMailer::ENCRYPTION_STARTTLS
    ],
    'database' => [
        'path' => __DIR__ . '/../data/rockets.db'
    ]
];
