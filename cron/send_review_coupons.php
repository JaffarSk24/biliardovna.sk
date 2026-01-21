<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Models\Coupon;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Fallback to direct .env file reading
if (empty($_ENV['DB_PASSWORD'])) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"');
        }
    }
}

// Database connection
$db = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Find bookings where review was clicked and coupon not sent yet
$sql = "
    SELECT * FROM bookings 
    WHERE review_clicked_at IS NOT NULL 
    AND coupon_code IS NULL
    AND customer_email IS NOT NULL
    AND customer_email != ''
";

$stmt = $db->query($sql);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = 0;

foreach ($bookings as $booking) {
    // Find first unused promo coupon
    $couponStmt = $db->prepare("
        SELECT code, id 
        FROM coupons 
        WHERE type = 'promo' 
        AND used = 0 
        AND customer_email IS NULL
        ORDER BY id ASC 
        LIMIT 1
    ");
    $couponStmt->execute();
    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo date('Y-m-d H:i:s') . " - No available promo coupons left!\n";
        break;
    }
    
    // Assign coupon to customer
    $assignStmt = $db->prepare("
        UPDATE coupons 
        SET customer_email = ?, booking_id = ?
        WHERE id = ?
    ");
    $assignStmt->execute([$booking['customer_email'], $booking['id'], $coupon['id']]);
    
    // Update booking
    $updateStmt = $db->prepare("UPDATE bookings SET coupon_code = ? WHERE id = ?");
    $updateStmt->execute([$coupon['code'], $booking['id']]);
    
    // Send email with coupon
    $sent = sendCouponEmail($booking, $coupon['code']);
    
    if ($sent) {
        $count++;
        echo date('Y-m-d H:i:s') . " - Sent coupon {$coupon['code']} to {$booking['customer_email']}\n";
    } else {
        echo date('Y-m-d H:i:s') . " - Failed to send coupon to {$booking['customer_email']}\n";
    }
    
    // 10 секунд между отправками
    usleep(10000000);
}

echo date('Y-m-d H:i:s') . " - Total sent: {$count} coupon(s)\n";

function sendCouponEmail($booking, $couponCode): bool
{
    $domain = $_ENV['MAILGUN_DOMAIN'] ?? null;
    $apiKey = $_ENV['MAILGUN_API_KEY'] ?? null;
    
    if (!$domain || !$apiKey) {
        return false;
    }
    
    $language = $booking['language'] ?? 'sk';
    $langFile = __DIR__ . "/../translations/{$language}.php";
    $translations = file_exists($langFile) ? require $langFile : require __DIR__ . '/../translations/sk.php';
    
    $subject = $translations['email_subject_coupon'] ?? 'Váš zľavový kupón - Biliardovna.sk';
    $greeting = $translations['email_greeting'] ?? 'Dobrý deň';
    $thanks = $translations['email_thanks'] ?? 'Ďakujeme';
    $team = $translations['email_team'] ?? 'Tím Biliardovna.sk';
    
    $siteUrl = $_ENV['APP_URL'] . ($language !== 'sk' ? '/' . $language : '');
    
    // Get coupon details from DB
    $db = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASSWORD']
    );
    $stmt = $db->prepare("SELECT discount_percent, valid_until FROM coupons WHERE code = ?");
    $stmt->execute([$couponCode]);
    $couponData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $expiryFormatted = $couponData['valid_until'] ? date('d.m.Y', strtotime($couponData['valid_until'])) : 'N/A';
    $discount = $couponData['discount_percent'] ?? 10;
    
    $html = "
    <html>
    <head>
        <meta charset='UTF-8'>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2c3e50;'>{$translations['email_coupon_title']}</h2>
            <p>{$greeting} {$booking['customer_name']},</p>
            <p>{$translations['email_coupon_text']}</p>
            
            <div style='background: #f8f9fa; padding: 30px; border-radius: 8px; margin: 30px 0; border: 2px dashed #4caf50; text-align: center;'>
                <p style='font-size: 16px; color: #666; margin-bottom: 10px;'>{$translations['email_coupon_code_label']}</p>
                <p style='font-size: 32px; font-weight: bold; color: #2c3e50; letter-spacing: 2px; margin: 15px 0;'>
                    {$couponCode}
                </p>
                <p style='font-size: 14px; color: #999;'>Zľava: {$discount}%</p>
                <p style='font-size: 14px; color: #999;'>{$translations['email_coupon_valid']}: {$expiryFormatted}</p>
            </div>
            
            <p>{$translations['email_coupon_how_to_use']}</p>
            
            <p style='margin-top: 30px;'>{$thanks},<br><strong><a href='{$siteUrl}' style='color: #2c3e50; text-decoration: none;'>{$team}</a></strong></p>
        </div>
    </body>
    </html>
    ";
    
    $url = "https://api.eu.mailgun.net/v3/{$domain}/messages";
    
    $data = [
        'from' => 'Biliardovňa <' . $_ENV['MAILGUN_FROM_EMAIL'] . '>',
        'to' => $booking['customer_email'],
        'subject' => $subject,
        'html' => $html
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result !== false;
}