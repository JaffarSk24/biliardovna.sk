<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\NotificationService;
use App\Database\Database;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Fallback to direct .env file reading (fix for hosting)
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

// Init Database
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
    exit(1);
}

// 5 minutes threshold
$threshold = date('Y-m-d H:i:s', strtotime('-5 minutes'));

// Find pending bookings older than 5 minutes
$sql = "
    SELECT * FROM bookings 
    WHERE status = 'pending' 
    AND created_at <= ? 
    AND (is_auto_confirmed IS NULL OR is_auto_confirmed = 0)
";

$stmt = $db->prepare($sql);
$stmt->execute([$threshold]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($bookings)) {
    echo date('Y-m-d H:i:s') . " - No pending bookings to auto-confirm.\n";
    exit(0);
}

$notificationService = new NotificationService();
$count = 0;

foreach ($bookings as $booking) {
    echo date('Y-m-d H:i:s') . " - Auto-confirming booking #{$booking['id']}...\n";

    // 1. Update Booking Status
    $updateSql = "UPDATE bookings SET status = 'confirmed', is_auto_confirmed = 1, updated_at = NOW() WHERE id = ?";
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([$booking['id']]);

    // 2. Send Confirmation Email
    // Re-fetch booking to get fresh data if needed, or just modify array
    $booking['status'] = 'confirmed';
    $notificationService->sendEmailNotification($booking, 'confirmed');

    // 3. Update Telegram Message (Simulate Admin Click)
    updateTelegramMessage($db, $booking);

    $count++;
}

echo date('Y-m-d H:i:s') . " - Auto-confirmed {$count} booking(s).\n";

/**
 * Helper to update Telegram message
 */
function updateTelegramMessage($db, $booking)
{
    $bookingId = $booking['id'];

    // Find all messages associated with this booking
    $stmt = $db->prepare("SELECT chat_id, message_id FROM telegram_messages WHERE booking_id = ?");
    $stmt->execute([$bookingId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$messages) return;

    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
    if (!$token) return;

    foreach ($messages as $msg) {
        $chatId = $msg['chat_id'];
        $messageId = $msg['message_id'];

        // Construct new message text (Confirmed state)
        // We can replicate NotificationService formatting or make a generic one.
        // For consistency, let's look at how NotificationService formats 'confirmed'.
        // It's private method, so we can't call it easily without refactoring.
        // Hack: Use a simplified "Auto-Confirmed" text or instantiate Service properly?
        // Service::formatTelegramMessage is private. 
        // We'll manually construct a simpler update message or copy logic.
        // Better: Make formatTelegramMessage public in NotificationService? 
        // Or just append "✅ Confirmed (Auto)" to the existing text? 
        // editMessageText requires full new text.

        // Use NotificationService to get consistent formatting with custom 'auto_confirmed' title
        $notificationService = new \App\Services\NotificationService();
        $newText = $notificationService->formatTelegramMessage($booking, 'auto_confirmed');

        // Add footer text about auto-confirmation reason
        $newText .= "\n\n<i>Zákazník mal 5 minút na čakanie a systém ju schválil autom.</i>";

        $url = "https://api.telegram.org/bot{$token}/editMessageText";
        $postData = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $newText,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => []]) // Remove buttons
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
