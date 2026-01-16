<?php

/**
 * Telegram Webhook Setup Script
 * Upload this to your server and visit https://biliardovna.sk/set_webhook.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
$secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '';
$domain = $_ENV['APP_URL'] ?? 'https://biliardovna.sk';

if (empty($token)) {
    die("Error: TELEGRAM_BOT_TOKEN is missing in .env");
}

$webhookUrl = rtrim($domain, '/') . '/webhook/telegram' . ($secret ? '?secret=' . $secret : '');

echo "<h1>Telegram Webhook Setup</h1>";
echo "<p><strong>Bot Token:</strong> " . substr($token, 0, 5) . "..." . substr($token, -5) . "</p>";
echo "<p><strong>Target URL:</strong> $webhookUrl</p>";

// 1. Get current webhook info
$ch = curl_init("https://api.telegram.org/bot{$token}/getWebhookInfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$info = json_decode(curl_exec($ch), true);
curl_close($ch);

echo "<h3>Current Status:</h3>";
echo "<pre>" . json_encode($info, JSON_PRETTY_PRINT) . "</pre>";

// 2. Set new webhook
if (isset($_POST['set'])) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/setWebhook");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, ['url' => $webhookUrl]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    echo "<h3>Set Webhook Result:</h3>";
    echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";

    if (($result['ok'] ?? false)) {
        echo "<p style='color:green'><strong>Webhook successfully set!</strong></p>";
    } else {
        echo "<p style='color:red'><strong>Error setting webhook.</strong></p>";
    }
}

?>

<form method="post">
    <button type="submit" name="set" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
        Set Webhook Now
    </button>
</form>