<?php
$botToken = "8449205901:AAEV8vQxaIr4HZvI9qu_Ge8iB7X8p6jeXwM";
$chatId = "6498470414";
$message = "Automatischer Alarm: Telegram-Anbindung fuer Revision100 aktiv.";

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

echo "Skript-Ausfuehrung beendet.\n";
?>
