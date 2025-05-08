<?php
$telegramToken = '8068050524:AAGPLbW72S3uBhoiPYYmHoKjAyuoqrTC_k0';
$semrushKey = 'f4352e8435cbf334e5d51d88050cf35c';
$usersFile = __DIR__ . '/users.txt';

$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['message']['chat']['id'])) {
    $chatId = $input['message']['chat']['id'];
    file_put_contents($usersFile, $chatId . PHP_EOL, FILE_APPEND | LOCK_EX);
}

if (!isset($input['message']['text'])) exit;

$chatId = $input['message']['chat']['id'];
$messageText = trim($input['message']['text']);

if (in_array($messageText, ['/start', '/menu'])) {
    $text = "Welcome to the SEMrush Bot 🔍\n\nAvailable commands:\n/check domain.com\n/export domain.com\n/suggest keyword vol>500 kd<70\n/related keyword\n/exact keyword\n/phrase keyword\n/volume keyword";
    sendTelegramMessage($telegramToken, $chatId, $text);
}

elseif (preg_match('/^\/check\s+(\S+)/i', $messageText, $m)) {
    $domain = $m[1];
    $url = "https://api.semrush.com/?type=domain_organic&key=$semrushKey&display_limit=50&export_columns=Ph,Po&domain=$domain&database=us";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No data found for $domain.");
    } else {
        $text = "📊 Top Organic Keywords for: $domain [us]\n\n";
        foreach (array_slice($lines, 1, 5) as $line) {
            [$kw, $pos] = str_getcsv($line, ';'); $text .= "- `$kw` (position: $pos)\n";
        }
        sendTelegramMessage($telegramToken, $chatId, $text);
    }
}

elseif (preg_match('/^\/export\s+(\S+)/i', $messageText, $m)) {
    $domain = $m[1];
    $url = "https://api.semrush.com/?type=domain_organic&key=$semrushKey&display_limit=100&export_columns=Ph,Po&domain=$domain&database=us";
    $csv = @file_get_contents($url);
    if (!$csv) {
        sendTelegramMessage($telegramToken, $chatId, "❌ Failed to export data for $domain.");
    } else {
        $path = __DIR__ . "/export_{$domain}_us.txt";
        file_put_contents($path, $csv);
        sendTelegramDocument($telegramToken, $chatId, $path, "✅ Exported keyword list for $domain [us]");
        unlink($path);
    }
}

elseif (preg_match('/^\/(suggest|related|exact|phrase)\s+(.+)/i', $messageText, $m)) {
    $cmd = strtolower($m[1]);
    $input = explode(' ', $m[2]);
    $phraseParts = array_slice($input, 0, 6); // Max 6 words
    $filters = ['vol' => null, 'kd' => null];
    foreach ($input as $i) {
        if (preg_match('/vol>(\d+)/i', $i, $vm)) $filters['vol'] = (int)$vm[1];
        if (preg_match('/kd<(\d+)/i', $i, $km)) $filters['kd'] = (int)$km[1];
    }
    $phrase = urlencode(implode(' ', $phraseParts));
    $typeMap = [
        'suggest' => 'phrase_related',
        'related' => 'related_keywords',
        'exact'   => 'phrase_exact',
        'phrase'  => 'phrase_phrase'
    ];
    $url = "https://api.semrush.com/?type={$typeMap[$cmd]}&key=$semrushKey&export_columns=Ph,Nq,Cp,Co&phrase=$phrase&database=us&display_limit=200";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No $cmd keywords found for '$phrase'.");
    } else {
        $text = "🔍 *" . ucfirst($cmd) . " keywords for:* `$phrase`\nFiltered:";
        if ($filters['vol']) $text .= " vol>{$filters['vol']}";
        if ($filters['kd']) $text .= " kd<{$filters['kd']}";
        $text .= "\n\n";

        $path = __DIR__ . "/{$cmd}_" . str_replace('%20', '_', $phrase) . "_us.txt";
        $fh = fopen($path, 'w');
        $total = 0;
        foreach (array_slice($lines, 1) as $line) {
            [$kw, $vol, $cpc, $kd] = str_getcsv($line, ';');
            if (($filters['vol'] && $vol < $filters['vol']) || ($filters['kd'] && $kd > $filters['kd'])) continue;
            $lineText = "$kw — Vol: $vol | CPC: \$$cpc | KD: $kd\n";
            if ($total < 5) $text .= "- `$kw` — Vol: $vol | KD: $kd\n";
            fwrite($fh, $lineText); $total++;
        }
        fclose($fh);
        $text .= "\n📥 Full list attached ($total results)";
        sendTelegramMessage($telegramToken, $chatId, $text);
        sendTelegramDocument($telegramToken, $chatId, $path, "Full $cmd report for '$phrase'");
        unlink($path);
    }
}

elseif (preg_match('/^\/volume\s+(.+)/i', $messageText, $m)) {
    $phrase = urlencode(trim($m[1]));
    $url = "https://api.semrush.com/?type=phrase_this&key=$semrushKey&export_columns=Ph,Nq,Cp,Co&phrase=$phrase&database=us";
    $csv = @file_get_contents($url);
    $lines = explode("\n", trim($csv));
    if (count($lines) < 2) {
        sendTelegramMessage($telegramToken, $chatId, "❌ No data for '$phrase'.");
    } else {
        [$kw, $vol, $cpc, $comp] = str_getcsv($lines[1], ';');
        $text = "📊 *Keyword:* `$kw`\n📈 Volume: $vol\n💰 CPC: \$$cpc\n⚔️ Difficulty: $comp";
        sendTelegramMessage($telegramToken, $chatId, $text);
    }
}

function sendTelegramMessage($token, $chatId, $text) {
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => http_build_query($params)]);
    curl_exec($ch); curl_close($ch);
}

function sendTelegramDocument($token, $chatId, $filepath, $caption) {
    $url = "https://api.telegram.org/bot$token/sendDocument";
    $postFields = ['chat_id' => $chatId, 'caption' => $caption, 'document' => new CURLFile(realpath($filepath))];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $postFields]);
    curl_exec($ch); curl_close($ch);
}
?>
