<?php
$telegramToken = '8068050524:AAGPLbW72S3uBhoiPYYmHoKjAyuoqrTC_k0';
$semrushKey = 'f4352e8435cbf334e5d51d88050cf35c';

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['message']['text'])) exit;

$chatId = $input['message']['chat']['id'];
$messageText = trim($input['message']['text']);

// Command parsing
if ($messageText === '/start' || $messageText === '/menu') {
    sendTelegramMessage($telegramToken, $chatId, "Welcome to the SEMrush Bot 🔍\n\nAvailable commands:\n/check domain.com [region]\n/export domain.com [region]\n/suggest keyword [region]\n/related keyword [region]\n/volume keyword [region]\n/exact keyword [region]\n/phrase keyword [region]");
}

elseif (preg_match('/^\/check\s+([\S]+)(?:\s+(\w{2}))?/i', $messageText, $m)) {
    $domain = $m[1]; $region = $m[2] ?? 'us';
    $url = "https://api.semrush.com/?type=domain_organic&key=$semrushKey&display_limit=50&export_columns=Ph,Po&domain=$domain&database=$region";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No keyword data found for $domain [$region].");
    } else {
        $text = "📊 Top Organic Keywords for: $domain [$region]\n\n";
        foreach (array_slice($lines, 1, 10) as $line) {
            [$kw, $pos] = str_getcsv($line, ';'); $text .= "- `$kw` (position: $pos)\n";
        }
        sendTelegramMessage($telegramToken, $chatId, $text);
    }
}

elseif (preg_match('/^\/export\s+([\S]+)(?:\s+(\w{2}))?/i', $messageText, $m)) {
    $domain = $m[1]; $region = $m[2] ?? 'us';
    $url = "https://api.semrush.com/?type=domain_organic&key=$semrushKey&display_limit=100&export_columns=Ph,Po&domain=$domain&database=$region";
    $csv = @file_get_contents($url);
    if (!$csv) {
        sendTelegramMessage($telegramToken, $chatId, "❌ Failed to export data for $domain [$region].");
    } else {
        $path = __DIR__ . "/export_{$domain}_{$region}.txt";
        file_put_contents($path, $csv);
        sendTelegramDocument($telegramToken, $chatId, $path, "✅ Keyword export for $domain [$region]");
        unlink($path);
    }
}

elseif (preg_match('/^\/(suggest|related|exact|phrase)\s+(.+)/i', $messageText, $m)) {
    $cmd = strtolower($m[1]); $input = explode(' ', $m[2]);
    $regionList = ['us','uk','ru','de','fr','br','ca','au','es','it'];
    $region = in_array(end($input), $regionList) ? array_pop($input) : 'us';
    $phrase = urlencode(implode(' ', $input));
    $type = [
        'suggest' => 'phrase_related',
        'related' => 'related_keywords',
        'exact'   => 'phrase_exact',
        'phrase'  => 'phrase_phrase'
    ][$cmd];
    $url = "https://api.semrush.com/?type=$type&key=$semrushKey&export_columns=Ph,Nq,Cp,Co&phrase=$phrase&database=$region&display_limit=10";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No $cmd keywords found for '$phrase' [$region].");
    } else {
        $title = ucfirst($cmd);
        $text = "🔎 *$title keywords for:* `$phrase` [$region]\n\n";
        foreach (array_slice($lines, 1, 10) as $line) {
            [$kw, $vol, $cpc, $comp] = str_getcsv($line, ';');
            $text .= "- `$kw` — 📈 Vol: $vol | 💰 CPC: \$$cpc | ⚔️ Comp: $comp\n";
        }
        sendTelegramMessage($telegramToken, $chatId, $text);
    }
}

elseif (preg_match('/^\/volume\s+(.+)/i', $messageText, $m)) {
    $input = explode(' ', $m[1]);
    $regionList = ['us','uk','ru','de','fr','br','ca','au','es','it'];
    $region = in_array(end($input), $regionList) ? array_pop($input) : 'us';
    $phrase = urlencode(implode(' ', $input));
    $url = "https://api.semrush.com/?type=phrase_this&key=$semrushKey&export_columns=Ph,Nq,Cp,Co&phrase=$phrase&database=$region";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No volume data for '$phrase' [$region].");
    } else {
        [$kw, $vol, $cpc, $comp] = str_getcsv($lines[1], ';');
        $text = "📊 *Keyword:* `$kw`\n📈 Volume: $vol\n💰 CPC: \$$cpc\n⚔️ Competition: $comp";
        sendTelegramMessage($telegramToken, $chatId, $text);
    }
}

function sendTelegramMessage($token, $chatId, $text) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => http_build_query($params)]); curl_exec($ch); curl_close($ch);
}

function sendTelegramDocument($token, $chatId, $filepath, $caption) {
    $url = "https://api.telegram.org/bot$token/sendDocument";
    $postFields = ['chat_id' => $chatId, 'caption' => $caption, 'document' => new CURLFile(realpath($filepath))];
    $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $postFields]); curl_exec($ch); curl_close($ch);
}
?>
