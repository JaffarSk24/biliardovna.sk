<?php

namespace App\Services;

class NotificationService
{
    private array $translations = [];
    
    /**
     * Load translations for given language
     */
    private function loadTranslations(string $language): void
    {
        $langFile = __DIR__ . "/../../translations/{$language}.php";
        if (file_exists($langFile)) {
            $this->translations = require $langFile;
        } else {
            // Fallback to Slovak
            $this->translations = require __DIR__ . '/../../lang/sk.php';
        }
    }
    
    /**
     * Get translation by key
     */
    private function trans(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }
    
    /**
     * Send notification via Telegram
     */
    public function sendTelegramNotification(array $booking, string $type = 'new'): bool
    {
        $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? null;
        $chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? null;
        
        if (!$token || !$chatId) {
            error_log('Telegram credentials not configured');
            return false;
        }
        
        $message = $this->formatTelegramMessage($booking, $type);
        
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        if ($type === 'new') {
            $data['reply_markup'] = json_encode([
                'inline_keyboard' => [[
                    ['text' => '✅ Potvrdiť', 'callback_data' => 'confirm_' . $booking['id']],
                    ['text' => '❌ Zrušiť', 'callback_data' => 'cancel_' . $booking['id']]
                ]]
            ]);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result !== false;
    }
    
    /**
     * Format message for Telegram
     */
    private function formatTelegramMessage(array $booking, string $type): string
    {
        $language = $booking['language'] ?? 'sk';
        $this->loadTranslations($language);
        
        $icons = [
            'new' => '🔔',
            'confirmed' => '✅',
            'cancelled' => '❌',
            'completed' => '🎉'
        ];
        
        $icon = $icons[$type] ?? '📋';
        
        // Language flags
        $flags = [
            'sk' => '🇸🇰',
            'ru' => '🇷🇺',
            'en' => '🇬🇧',
            'uk' => '🇺🇦',
            'de' => '🇩🇪'
        ];
        $flag = $flags[$language] ?? '🌐';
        
        $title = $type === 'new' 
            ? $this->trans('notification_new_booking') 
            : $this->trans('notification_booking_update');
        
        // Format date as dd.mm.yyyy
        $date = date('d.m.Y', strtotime($booking['booking_date']));
        
        // Remove seconds from time
        $startTime = substr($booking['start_time'], 0, 5);
        $endTime = substr($booking['end_time'], 0, 5);

        // Table numbers
        $tableNumber = (string)($booking['resource_id'] ?? '');
        try {
            if (!empty($booking['resource_name']) && preg_match('/(\d+)/u', (string)$booking['resource_name'], $m)) {
                $tableNumber = $m[1];
            } else {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare('SELECT name FROM resources WHERE id = ?');
                $stmt->execute([(int)($booking['resource_id'] ?? 0)]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && preg_match('/(\d+)/u', (string)$row['name'], $m)) {
                    $tableNumber = $m[1];
                }
            }
        } catch (\Throwable $e) {
            // keep fallback to resource_id
        }
        
        $message = "{$icon} <b>{$title}</b>\n";
        $message .= "🆔 ID: {$booking['id']}\n\n";
        $message .= "📅 {$this->trans('notification_date')}: {$date}\n";
        $message .= "🕐 {$this->trans('notification_time')}: {$startTime} - {$endTime}\n\n";
        $message .= "🎯 {$this->trans('notification_service')}: {$booking['service_name']}\n";
        $message .= "🎱 {$this->trans('notification_table')}: {$tableNumber}\n\n";
        $message .= "{$flag} {$this->trans('notification_language')}: {$language}\n";
        $message .= "👤 {$this->trans('notification_customer_name')}: {$booking['customer_name']}\n";
        $message .= "📞 {$this->trans('notification_customer_phone')}: {$booking['customer_phone']}\n";
        $message .= "📧 Email: {$booking['customer_email']}\n\n";
        
        if (!empty($booking['notes'])) {
            $message .= "📝 {$this->trans('notification_notes')}: {$booking['notes']}\n\n";
        }

        // Price with discount info
        if (!empty($booking['coupon_redeemed'])) {
            $originalPrice = isset($booking['original_price']) ? number_format($booking['original_price'], 2) : number_format($booking['price'], 2);
            $discountPercent = $booking['discount_percent'] ?? 10;
            $message .= "💰 {$this->trans('notification_total_price')}: <s>{$originalPrice} €</s> → <b>" . number_format($booking['price'], 2) . " €</b>\n";
            $message .= "🎟️ {$this->trans('notification_coupon')}: {$booking['coupon_redeemed']} (-{$discountPercent}%)\n";
        } else {
            $message .= "💰 {$this->trans('notification_total_price')}: " . number_format($booking['price'], 2) . " €\n";
        }
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        
        return $message;
    }
    
    /**
     * Send email notification via Mailgun
     */
    public function sendEmailNotification(array $booking, string $status = 'pending'): bool
    {
        $domain = $_ENV['MAILGUN_DOMAIN'] ?? null;
        $apiKey = $_ENV['MAILGUN_API_KEY'] ?? null;
        
        if (!$domain || !$apiKey) {
            error_log('Mailgun credentials not configured');
            return false;
        }
        
        $to = $booking['customer_email'] ?? null;
        if (!$to) {
            return false;
        }
        
        $language = $booking['language'] ?? 'sk';
        $this->loadTranslations($language);
        
        // Map status to template
        $templateMap = [
            'pending' => 'booking_confirmation',
            'confirmed' => 'confirmed',
            'cancelled' => 'cancelled'
        ];
        
        $template = $templateMap[$status] ?? 'booking_confirmation';
        
        // Subject based on status
        $subjects = [
            'pending' => $this->trans('email_subject_pending'),
            'confirmed' => $this->trans('email_subject_confirmed'),
            'cancelled' => $this->trans('email_subject_cancelled')
        ];
        
        $subject = $subjects[$status] ?? $this->trans('email_subject_pending');
        
        $url = "https://api.eu.mailgun.net/v3/{$domain}/messages";
        
        $data = [
            'from' => 'Biliardovňa <' . $_ENV['MAILGUN_FROM_EMAIL'] . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $this->renderEmailTemplate($booking, $template)
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
    
    /**
     * Send review request email (once per email)
     */
    public function sendReviewRequestEmail(array $booking): bool
    {
        $domain = $_ENV['MAILGUN_DOMAIN'] ?? null;
        $apiKey = $_ENV['MAILGUN_API_KEY'] ?? null;
        if (!$domain || !$apiKey) {
            error_log('Mailgun credentials not configured');
            return false;
        }

        $to = $booking['customer_email'] ?? null;
        if (!$to) return false;

        // Only after actual visit
        if (($booking['status'] ?? null) !== 'completed') {
            return false;
        }

        $email = strtolower(trim($to));
        $bookingId = (int)($booking['id'] ?? 0);
        if ($bookingId <= 0) return false;

        $db = \App\Database\Database::getInstance();

        // 1) Already sent for this email?
        $stmt = $db->prepare("SELECT 1 FROM bookings WHERE LOWER(customer_email)=LOWER(:email) AND review_request_sent=1 LIMIT 1");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        // 2) Generate & save token into this booking
        $reviewToken = bin2hex(random_bytes(32));
        $upd = $db->prepare("UPDATE bookings SET review_token=:token WHERE id=:id");
        $upd->execute(['token' => $reviewToken, 'id' => $bookingId]);

        $language = $booking['language'] ?? 'sk';
        $this->loadTranslations($language);

        // 3) Review URL with token
        $reviewUrl = rtrim($_ENV['APP_URL'] ?? '', '/') . '/review?booking=' . $bookingId . '&token=' . $reviewToken;

        // 4) Send email
        $subject = $this->trans('email_subject_review_request');
        $url = "https://api.eu.mailgun.net/v3/{$domain}/messages";

        $bookingForEmail = $booking;
        $bookingForEmail['review_url'] = $reviewUrl;

        $data = [
            'from' => 'Biliardovňa <' . $_ENV['MAILGUN_FROM_EMAIL'] . '>',
            'to' => $to,
            'subject' => $subject,
            'html' => $this->renderEmailTemplate($bookingForEmail, 'review_request')
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$apiKey}");
        $result = curl_exec($ch);
        curl_close($ch);

        if ($result === false) {
            return false;
        }

        // 5) Mark sent for this booking (blocks repeats for the same email)
        $upd2 = $db->prepare("UPDATE bookings SET review_request_sent=1 WHERE id=:id");
        $upd2->execute(['id' => $bookingId]);

        return true;
    }

    /**
     * Send coupon email after first review link click (only on first coupon creation)
     */
    public function sendReviewCouponEmail(array $booking, string $couponCode, string $expiryDateSql): bool
    {
        $domain = $_ENV['MAILGUN_DOMAIN'] ?? null;
        $apiKey = $_ENV['MAILGUN_API_KEY'] ?? null;
        if (!$domain || !$apiKey) {
            error_log('Mailgun credentials not configured');
            return false;
        }

        $to = $booking['customer_email'] ?? null;
        if (!$to) return false;

        $language = $booking['language'] ?? 'sk';
        $this->loadTranslations($language);

        $subject = $this->trans('email_review_coupon_subject');
        $url = "https://api.eu.mailgun.net/v3/{$domain}/messages";

        $bookingForEmail = $booking;
        $bookingForEmail['coupon_code']        = $couponCode;
        $bookingForEmail['coupon_expiry_date'] = date('d.m.Y', strtotime($expiryDateSql));

        $data = [
            'from'    => 'Biliardovňa <' . $_ENV['MAILGUN_FROM_EMAIL'] . '>',
            'to'      => $to,
            'subject' => $subject,
            'html'    => $this->renderEmailTemplate($bookingForEmail, 'review_coupon'),
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

    /**
     * Render email template
     */
    private function renderEmailTemplate(array $booking, string $template): string
    {
        $greeting = $this->trans('email_greeting');
        $thanks = $this->trans('email_thanks');
        $team = $this->trans('email_team');
        
        $language = $booking['language'] ?? 'sk';
        $siteUrl = $_ENV['APP_URL'] . ($language !== 'sk' ? '/' . $language : '');
        
        // Review request
        if ($template === 'review_request') {
            $reviewUrl = $booking['review_url'] ?? '#';

            return "
            <html>
            <head>
                <meta charset='UTF-8'>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #2c3e50;'>{$this->trans('email_review_title')}</h2>
                    <p>{$greeting} {$booking['customer_name']},</p>
                    <p>{$this->trans('email_review_text')}</p>

                    <p style='margin: 30px 0;'>
                        <a href='{$reviewUrl}' style='display: inline-block; padding: 15px 30px; background: #4caf50; color: white; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;'>{$this->trans('email_review_button')}</a>
                    </p>

                    <p style='color: #666; font-size: 14px;'>{$this->trans('email_review_coupon_info')}</p>

                    <p style='margin-top: 30px;'>{$thanks},<br><strong><a href='{$siteUrl}' style='color: #2c3e50; text-decoration: none;'>{$team}</a></strong></p>
                </div>
            </body>
            </html>";
        }

        // Review coupon
        if ($template === 'review_coupon') {
            $code   = $booking['coupon_code'] ?? '';
            $expiry = $booking['coupon_expiry_date'] ?? '';

            return "
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
              <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2c3e50;'>{$this->trans('email_review_coupon_title')}</h2>
                <p>{$greeting} {$booking['customer_name']},</p>
                <p>{$this->trans('email_review_coupon_text')}</p>
                <div style='background:#f8f9fa;border-radius:6px;padding:16px;margin:20px 0;font-size:18px;'>
                  <strong>{$this->trans('notification_coupon')}:</strong> <span style='font-family:monospace;font-size:20px;'>{$code}</span><br>
                  <span style='color:#555;'>{$this->trans('email_coupon_valid_until')}: {$expiry}</span>
                </div>
                <p style='margin-top: 30px;'>{$thanks},<br><strong><a href='{$siteUrl}' style='color: #2c3e50; text-decoration: none;'>{$team}</a></strong></p>
              </div>
            </body>
            </html>";
        }
        
        // Standard booking templates
        $dateLabel = $this->trans('notification_date');
        $timeLabel = $this->trans('notification_time');
        $serviceLabel = $this->trans('notification_service');
        $totalLabel = $this->trans('notification_total_price');
        
        $date = date('d.m.Y', strtotime($booking['booking_date']));
        $startTime = substr($booking['start_time'], 0, 5);
        $endTime = substr($booking['end_time'], 0, 5);
        
        $statusMessages = [
            'booking_confirmation' => $this->trans('email_booking_received'),
            'confirmed' => $this->trans('email_booking_confirmed'),
            'cancelled' => $this->trans('email_booking_cancelled')
        ];
        
        $message = $statusMessages[$template] ?? $statusMessages['booking_confirmation'];
        
        $statusColors = [
            'pending' => '#ff9800',
            'confirmed' => '#4caf50',
            'cancelled' => '#f44336'
        ];
        
        $statusLabels = [
            'pending' => $this->trans('status_pending'),
            'confirmed' => $this->trans('status_confirmed'),
            'cancelled' => $this->trans('status_cancelled')
        ];
        
        $statusColor = $statusColors[$booking['status']] ?? '#ff9800';
        $statusLabel = $statusLabels[$booking['status']] ?? $this->trans('status_pending');
        
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2c3e50;'>{$this->trans('email_subject_' . ($template === 'booking_confirmation' ? 'pending' : $template))}</h2>
                <p>{$greeting} {$booking['customer_name']},</p>
                <p>{$message}</p>";
        
        if ($booking['status'] === 'pending') {
            $html .= "
                <p style='margin: 15px 0;'>
                    <strong>{$this->trans('email_pending_wait_call')}</strong><br>
                    {$this->trans('email_pending_not_valid')}
                </p>";
        }
        
        $html .= "
                <div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>{$dateLabel}:</strong> {$date}</p>
                    <p style='margin: 5px 0;'><strong>{$timeLabel}:</strong> {$startTime} - {$endTime}</p>
                    <p style='margin: 5px 0;'><strong>{$serviceLabel}:</strong> {$booking['service_name']}</p>
                    <p style='margin: 5px 0;'><strong>{$totalLabel}:</strong> {$booking['price']} €</p>
                    <p style='margin: 10px 0 5px 0;'><strong>Status:</strong> <span style='color: {$statusColor}; font-weight: bold;'>{$statusLabel}</span></p>
                </div>";
        
        if (in_array($booking['status'], ['pending', 'confirmed']) && !empty($booking['cancellation_token'])) {
            $cancelUrl = $_ENV['APP_URL'] . '/booking/cancel?token=' . $booking['cancellation_token'];
            $cancelText = $this->trans('email_cancel_booking');
            
            $html .= "
                <p style='margin-top: 30px;'>
                    <a href='{$cancelUrl}' style='display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px;'>{$cancelText}</a>
                </p>";
        }
        
        $html .= "
                <p style='margin-top: 30px;'>{$thanks},<br><strong><a href='{$siteUrl}' style='color: #2c3e50; text-decoration: none;'>{$team}</a></strong></p>
            </div>
        </body>
        </html>
        ";
        
        return $html;
    }
}