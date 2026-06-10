<?php
// /Users/timpoolair/revision100/functions.php

function sendTelegramMessage($message) {
    $botToken = getenv('TELEGRAM_BOT_TOKEN');
    $chatId = getenv('TELEGRAM_CHAT_ID');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . $botToken . "/sendMessage");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id' => $chatId,
        'text' => $message
    ]);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); 

    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

require_once __DIR__ . '/r400_status.php';