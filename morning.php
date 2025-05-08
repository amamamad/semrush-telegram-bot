<?php
$token = '8068050524:AAGPLbW72S3uBhoiPYYmHoKjAyuoqrTC_k0';
$usersFile = __DIR__ . '/users.txt';

if (!file_exists($usersFile)) exit;

$users = file($usersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach (array_unique($users) as $chatId) {
    file_get_contents("https://api.telegram.org/bot$token/sendMessage?chat_id=$chatId&text=Let's+check+something");
}
?>