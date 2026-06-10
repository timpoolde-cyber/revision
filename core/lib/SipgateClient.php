<?php
declare(strict_types=1);

class SipgateClient {
    private string $token;
    private string $tokenId;
    private string $smsId;

    public function __construct() {
        // Zieht die globalen Keys aus der zentralen .env
        $this->token = getenv('SIPGATE_API_TOKEN') ?: '';
        $this->tokenId = getenv('SIPGATE_API_TOKEN_ID') ?: '';
        $this->smsId = getenv('SIPGATE_SMS_ID') ?: 's0'; // Standard Web-SMS Extension
    }

    public function sendSMS(string $recipient, string $message): array {
        if (empty($this->token) || empty($this->tokenId)) {
            return ['ok' => false, 'error' => 'Sipgate API Credentials nicht konfiguriert.'];
        }

        $url = 'https://api.sipgate.com/v2/sessions/sms';
        $payload = json_encode([
            'smsId' => $this->smsId,
            'recipient' => $recipient,
            'message' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($this->tokenId . ':' . $this->token)
            ],
            CURLOPT_POSTFIELDS => $payload
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curlError];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'response' => json_decode($response, true)];
        }

        return ['ok' => false, 'error' => "HTTP-Status $httpCode: $response"];
    }
}
