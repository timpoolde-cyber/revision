<?php
/**
 * REVISION 100 — GSC OAuth2 Callback
 * Exchanges auth code for tokens and stores them.
 * Register this URL in Google Cloud Console as authorized redirect URI.
 */

require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/gsc_api.php';

requireAuthPage();

$db = new PDO('sqlite:' . __DIR__ . '/data/rockets.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
gsc_db_init($db);

// OAuth error from Google
if (isset($_GET['error'])) {
    header('Location: settings.php?gsc_err=' . urlencode($_GET['error']));
    exit;
}

$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: settings.php?gsc_err=no_code');
    exit;
}

$r = gsc_http(
    'https://oauth2.googleapis.com/token',
    'POST',
    http_build_query([
        'code'          => $code,
        'client_id'     => gsc_get($db, 'gsc_client_id'),
        'client_secret' => gsc_get($db, 'gsc_client_secret'),
        'redirect_uri'  => gsc_get($db, 'gsc_redirect_uri'),
        'grant_type'    => 'authorization_code',
    ]),
    ['Content-Type: application/x-www-form-urlencoded']
);

$d = json_decode($r['body'], true);

if (empty($d['access_token'])) {
    header('Location: settings.php?gsc_err=token_fail');
    exit;
}

gsc_set($db, 'gsc_access_token',  $d['access_token']);
gsc_set($db, 'gsc_refresh_token', $d['refresh_token'] ?? '');
gsc_set($db, 'gsc_token_expires', (string)(time() + (int)($d['expires_in'] ?? 3600)));
gsc_set($db, 'gsc_connected_at',  date('Y-m-d H:i:s'));

header('Location: settings.php?gsc_ok=1');
exit;
