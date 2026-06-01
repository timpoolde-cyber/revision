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
        'pass' => 'v,W69-A;E_8m',
        'port' => 587,
        'auth' => true,
        'secure' => 'tls'  // PHPMailer::ENCRYPTION_STARTTLS
    ],
    'database' => [
        'path' => __DIR__ . '/../data/rockets.db'
    ]
];
