<?php
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);

// Load .env
if (file_exists(__DIR__ . '/.env')) {
    $env = parse_ini_file(__DIR__ . '/.env');
    if ($env !== false) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
        }
    }
}

$testUrl = $_GET['url'] ?? 'https://example.com';
$apiKey = getenv('GOOGLE_PSI_API_KEY');

if (!$apiKey) {
    echo "ERROR: GOOGLE_PSI_API_KEY not set\n";
    exit;
}

echo "Test PSI API call\n";
echo "=================\n\n";
echo "Target URL: $testUrl\n";
echo "API Key (first 10 chars): " . substr($apiKey, 0, 10) . "...\n\n";

$baseApiUrl = 'https://pagespeedonline.googleapis.com/pagespeedonline/v5/runPagespeed';
$categories = ['performance', 'accessibility', 'best-practices', 'seo'];
$categoryParts = array();
foreach ($categories as $cat) {
    $categoryParts[] = "category=" . urlencode($cat);
}
$categoryParams = implode('&', $categoryParts);
$strategy = 'mobile';

$apiUrl = "{$baseApiUrl}?url=" . urlencode($testUrl) . "&key={$apiKey}&{$categoryParams}&strategy={$strategy}";

echo "Full API URL:\n";
echo $apiUrl . "\n\n";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 60,
        'ignore_errors' => true
    ]
]);

$response = @file_get_contents($apiUrl, false, $context);

echo "Response Status:\n";
if ($response === false) {
    echo "Failed to fetch (no data returned)\n";
} else {
    echo "Success - " . strlen($response) . " bytes\n\n";
    echo "Response (first 1500 chars):\n";
    echo substr($response, 0, 1500) . "\n";
}
?>
