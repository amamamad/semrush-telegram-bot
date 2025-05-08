<?php
$telegramToken = '8068050524:AAGPLbW72S3uBhoiPYYmHoKjAyuoqrTC_k0';
$semrushKey = 'f4352e8435cbf334e5d51d88050cf35c';

// Simplified dispatcher placeholder
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['message']['text'])) exit;

$chatId = $input['message']['chat']['id'];
$messageText = trim($input['message']['text']);

// Basic response for validation
sendTelegramMessage($telegramToken, $chatId, "Bot is live! You sent: $messageText");

// Basic send message function
function sendTelegramMessage($token, $chatId, $text) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($params)
    ]);
    curl_exec($ch);
    curl_close($ch);
}
?>