<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\NotificationService;
use App\Services\BookingService;
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
    ORDER BY created_at ASC
";

$stmt = $db->prepare($sql);
$stmt->execute([$threshold]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($bookings)) {
    echo date('Y-m-d H:i:s') . " - No pending bookings to auto-confirm.\n";
    exit(0);
}

// We need BookingService
$bookingService = new BookingService();
$count = 0;

foreach ($bookings as $booking) {
    echo date('Y-m-d H:i:s') . " - Processing booking #{$booking['id']}...\n";

    // Attempt Confirmation (checks for conflicts)
    // Pass isAuto = true
    $result = $bookingService->attemptConfirmation($booking['id'], true);

    if ($result['success']) {
        echo " -> Confirmed.\n";

        // Mark as auto-confirmed in DB (attemptConfirmation sets status to confirmed, but doesn't set is_auto_confirmed flag)
        // We should update the flag
        $updateSql = "UPDATE bookings SET is_auto_confirmed = 1 WHERE id = ?";
        $updateStmt = $db->prepare($updateSql);
        $updateStmt->execute([$booking['id']]);

        // Cleanup Old Telegram Message (Remove buttons / Update text)
        updateTelegramMessage($db, $booking, 'confirmed');
        $count++;
    } else {
        echo " -> Failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        if (!empty($result['conflict'])) {
            echo " -> Conflict detected! Booking cancelled.\n";
            // Cleanup Old Telegram Message (Update text to say cancelled)
            updateTelegramMessage($db, $booking, 'conflict');
        }
    }
}

echo date('Y-m-d H:i:s') . " - Processed bookings. Auto-confirmed count: {$count}.\n";

/**
 * Helper to update OLD Upgrade/Confirm Telegram message to remove buttons
 */
function updateTelegramMessage($db, $booking, $status)
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

        // We just want to remove buttons and maybe add a tag line.
        // We cannot easily "edit" the text to match the new one as we don't have the full original text easily available 
        // without reconstructing it or fetching it (which Telegram API doesn't easily let us do for bots without keeping state).
        // BUT we know the structure.

        // Simpler approach: Just remove buttons.
        $replyMarkup = json_encode(['inline_keyboard' => []]);

        $url = "https://api.telegram.org/bot{$token}/editMessageReplyMarkup";
        $postData = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup
        ];

        // If we want to update text to reflect status (optional but nice)
        // We'd need to assume the original text is "New Booking..."
        // editMessageText requires text.
        // Let's just remove buttons to prevent double-action.

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}
