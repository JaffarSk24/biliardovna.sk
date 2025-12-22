<?php

namespace App\Controllers;

use App\Database\Database;
use App\Services\BookingService;

class TelegramController extends Controller
{
    public function handleWebhook()
    {
        $logFile = __DIR__ . '/../../logs/telegram.log';
        
        // Ensure log directory exists
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }

        file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . file_get_contents('php://input') . "\n\n", FILE_APPEND);
        
        $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $secret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? null;
        $allowedChats = array_map('trim', explode(',', $_ENV['TELEGRAM_ALLOWED_CHATS'] ?? ''));
        
        if (!$token) {
            http_response_code(500);
            exit;
        }
        
        if ($secret && ($_GET['secret'] ?? '') !== $secret) {
            http_response_code(403);
            exit;
        }
        
        $update = json_decode(file_get_contents('php://input'), true);
        
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $token, $allowedChats);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message'], $token, $allowedChats, $logFile);
        }
        
        echo json_encode(['ok' => true]);
    }

    private function handleCallbackQuery($callbackQuery, $token, $allowedChats)
    {
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];
        $chatId = $callbackQuery['message']['chat']['id'];
        
        // Check access
        if (!in_array((string)$chatId, $allowedChats, true)) {
            http_response_code(403);
            exit;
        }
        
        if (strpos($data, 'confirm_') === 0) {
            $this->handleBookingAction($data, 'confirm_', 'confirmed', '✅ Potvrdené!', $token, $chatId, $messageId, $callbackQuery['message']['text'], $callbackQuery['id']);
        } elseif (strpos($data, 'cancel_') === 0) {
            $this->handleBookingAction($data, 'cancel_', 'cancelled', '❌ Zrušené!', $token, $chatId, $messageId, $callbackQuery['message']['text'], $callbackQuery['id']);
        }
    }

    private function handleBookingAction($data, $prefix, $status, $newStatusText, $token, $chatId, $messageId, $originalText, $callbackQueryId)
    {
        $bookingId = (int)str_replace($prefix, '', $data);
        
        $bookingService = new BookingService();
        $bookingService->updateStatus($bookingId, $status);
        
        $lines = explode("\n", $originalText);
        $lines[0] = $newStatusText;
        $newText = implode("\n", $lines);
        
        $editData = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $newText,
            'parse_mode' => 'HTML'
        ];

        if ($status === 'confirmed') {
            $editData['reply_markup'] = json_encode([
                'inline_keyboard' => [[
                    ['text' => '❌ Zrušiť', 'callback_data' => 'cancel_' . $bookingId]
                ]]
            ]);
        }
        
        $this->sendMessage('editMessageText', $token, $editData);
        
        $answerData = [
            'callback_query_id' => $callbackQueryId,
            'text' => $status === 'confirmed' ? 'Rezervácia potvrdená ✅' : 'Rezervácia zrušená ❌'
        ];
        
        $this->sendMessage('answerCallbackQuery', $token, $answerData);
    }

    private function handleMessage($message, $token, $allowedChats, $logFile)
    {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';
        
        // Check access
        if (!in_array((string)$chatId, $allowedChats, true)) {
            http_response_code(403);
            exit;
        }
        
        if ($text === '?') {
            $this->sendMessage('sendMessage', $token, [
                'chat_id' => $chatId,
                'text' => 'Zadajte číslo rezervácie:',
                'reply_markup' => json_encode(['force_reply' => true])
            ]);
        } elseif ($text === '%') {
             $this->sendMessage('sendMessage', $token, [
                'chat_id' => $chatId,
                'text' => 'Zadajte promo kód:',
                'reply_markup' => json_encode(['force_reply' => true])
            ]);
        } elseif (isset($message['reply_to_message']) && $message['reply_to_message']['text'] === 'Zadajte promo kód:') {
            $this->handleCouponCheck($text, $chatId, $token, $logFile);
        } elseif (isset($message['reply_to_message']) && $message['reply_to_message']['text'] === 'Zadajte číslo rezervácie:') {
            $this->handleBookingLookup($text, $chatId, $token, $logFile);
        }
    }

    private function handleCouponCheck($couponCode, $chatId, $token, $logFile)
    {
        $couponCode = trim($couponCode);
        try {
            $conn = Database::getInstance();
            $stmt = $conn->prepare("SELECT id, type, discount_percent, used FROM coupons WHERE code = ?");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$coupon) {
                $responseText = "❌ Promo kód <code>{$couponCode}</code> nebol nájdený.";
            } elseif ($coupon['used'] >= 1) {
                $responseText = "⚠️ Promo kód <code>{$couponCode}</code> už bol použitý.";
            } else {
                $stmt = $conn->prepare("UPDATE coupons SET used = used + 1 WHERE id = ?");
                $stmt->execute([$coupon['id']]);
                
                $typeLabel = $coupon['type'] === 'promo' ? '🎁 Promo' : '⭐ Za recenziu';
                $responseText = "✅ Promo kód <code>{$couponCode}</code> bol úspešne uplatnený!\n\n{$typeLabel}\nZľava: {$coupon['discount_percent']}%";
            }
            
            $this->sendMessage('sendMessage', $token, [
                'chat_id' => $chatId,
                'text' => $responseText,
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            file_put_contents($logFile, "Coupon Error: " . $e->getMessage() . "\n", FILE_APPEND);
            $this->sendError($chatId, $token, $e->getMessage());
        }
    }

    private function handleBookingLookup($bookingIdText, $chatId, $token, $logFile)
    {
        $bookingId = (int)trim($bookingIdText);
        
        try {
            $conn = Database::getInstance();
            $stmt = $conn->prepare("SELECT b.* FROM bookings b WHERE b.id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($booking) {
                // Get service slug
                $serviceStmt = $conn->prepare("SELECT slug FROM services WHERE id = ?");
                $serviceStmt->execute([$booking['service_id']]);
                $service = $serviceStmt->fetch(\PDO::FETCH_ASSOC);
                $booking['service_name'] = ucfirst($service['slug'] ?? 'Unknown');
                
                // Calculate original price if coupon was used
                $originalPrice = null;
                $discountPercent = null;
                if (!empty($booking['coupon_redeemed'])) {
                    $couponStmt = $conn->prepare("SELECT discount_percent FROM coupons WHERE code = ?");
                    $couponStmt->execute([$booking['coupon_redeemed']]);
                    $couponData = $couponStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($couponData) {
                        $discountPercent = (int)$couponData['discount_percent'];
                        $originalPrice = $booking['price'] / (1 - $discountPercent / 100);
                    }
                }
                
                $statusIcons = [
                    'pending' => '🔔',
                    'confirmed' => '✅',
                    'cancelled' => '❌',
                    'completed' => '🎉'
                ];
                
                $statusTitles = [
                    'pending' => 'Nová rezervácia!',
                    'confirmed' => 'Potvrdené!',
                    'cancelled' => 'Zrušené!',
                    'completed' => 'Dokončené!'
                ];
                
                $status = $booking['status'];
                $icon = $statusIcons[$status] ?? '📋';
                $title = $statusTitles[$status] ?? 'Rezervácia';
                
                $flags = ['sk' => '🇸🇰', 'ru' => '🇷🇺', 'en' => '🇬🇧', 'uk' => '🇺🇦', 'de' => '🇩🇪'];
                $flag = $flags[$booking['language']] ?? '🌐';
                
                $date = date('d.m.Y', strtotime($booking['booking_date']));
                $startTime = substr($booking['start_time'], 0, 5);
                $endTime = substr($booking['end_time'], 0, 5);
                
                $messageText = "{$icon} <b>{$title}</b>\n\n";
                $messageText .= "📅 Dátum: {$date}\n";
                $messageText .= "🕐 Čas: {$startTime} - {$endTime}\n\n";
                $messageText .= "🎯 Služba: {$booking['service_name']}\n";
                $messageText .= "🎱 Stôl č.: {$booking['resource_id']}\n\n";
                $messageText .= "{$flag} Jazyk: {$booking['language']}\n";
                $messageText .= "👤 Meno: {$booking['customer_name']}\n";
                $messageText .= "📞 Telefón: {$booking['customer_phone']}\n\n";
                
                if (!empty($booking['notes'])) {
                    $messageText .= "📝 Poznámka: {$booking['notes']}\n\n";
                }
                
                if ($originalPrice && $discountPercent) {
                    $messageText .= "💰 Spolu: <s>" . number_format($originalPrice, 2) . " €</s> → <b>{$booking['price']} €</b>\n";
                    $messageText .= "🎟️ Promo kód: {$booking['coupon_redeemed']} (-{$discountPercent}%)\n";
                } else {
                    $messageText .= "💰 Spolu: {$booking['price']} €\n";
                }
                
                $messageText .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
                
                $replyMarkup = null;
                if ($status === 'pending') {
                    $replyMarkup = json_encode([
                        'inline_keyboard' => [[
                            ['text' => '✅ Potvrdiť', 'callback_data' => 'confirm_' . $bookingId],
                            ['text' => '❌ Zrušiť', 'callback_data' => 'cancel_' . $bookingId]
                        ]]
                    ]);
                } elseif ($status === 'confirmed') {
                    $replyMarkup = json_encode([
                        'inline_keyboard' => [[
                            ['text' => '❌ Zrušiť', 'callback_data' => 'cancel_' . $bookingId]
                        ]]
                    ]);
                }
                
                $data = [
                    'chat_id' => $chatId,
                    'text' => $messageText,
                    'parse_mode' => 'HTML'
                ];
                
                if ($replyMarkup) {
                    $data['reply_markup'] = $replyMarkup;
                }
                
                $this->sendMessage('sendMessage', $token, $data);
            } else {
                $this->sendError($chatId, $token, "Rezervácia č.: {$bookingId} nebola nájdená");
            }
        } catch (\Exception $e) {
            file_put_contents($logFile, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    private function sendMessage($method, $token, $data)
    {
        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
    
    private function sendError($chatId, $token, $message)
    {
        $this->sendMessage('sendMessage', $token, [
            'chat_id' => $chatId,
            'text' => "❌ " . $message
        ]);
    }
}
