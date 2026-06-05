<?php
/**
 * REVISION100™ — SYSTEM-KONFIGURATION (ISOLIERT)
 * WARNUNG: Diese Datei enthält sensitive Credentials.
 * NICHT in Versionskontrolle committen (.gitignore)!
 */
return [
    'smtp' => [
        'host' => 'send.one.com',
        'user' => 'system@revision100.de',
        'pass' => 'qajac7y4tecu',
        'port' => 587,
        'auth' => true,
        'secure' => 'tls'  // PHPMailer::ENCRYPTION_STARTTLS
    ],
    'database' => [
        'path' => __DIR__ . '/../data/rockets.db'
    ]
];
