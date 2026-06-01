<?php

// 1. Umgebungsvariablen laden (.env)
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

// 2. Sicherheits- und Session-Validierung
require_once __DIR__ . '/session_handler.php';
check_auth();

// 3. Globale Hilfsfunktionen bereitstellen
require_once __DIR__ . '/functions.php';

// 4. Google Maps Key für das Modal-Autocomplete bereitstellen
// Prüft erst getenv(), dann das globale $_ENV-Array als Fallback
$googleMapsKey = getenv('GOOGLE_MAPS_KEY') ?: ($_ENV['GOOGLE_MAPS_KEY'] ?? '');

// Unvarnierter System-Check: Falls der Key leer ist, versuchen wir die .env manuell zu parsen
if (empty($googleMapsKey) && file_exists(__DIR__ . '/.env')) {
    $localEnv = parse_ini_file(__DIR__ . '/.env');
    $googleMapsKey = $localEnv['GOOGLE_MAPS_KEY'] ?? '';
}

// 5. Das isolierte Design-Template laden
require_once __DIR__ . '/views/crm.tpl.php';