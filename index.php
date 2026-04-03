<?php

/**
 * Biliardovna.sk Booking System
 * Entry point
 */

session_start();

// Allow webhook access without session check
if ($_SERVER['REQUEST_URI'] && strpos($_SERVER['REQUEST_URI'], '/webhook/telegram') !== false) {
    $_SESSION['access_granted'] = true;
}

/*
if (!isset($_SESSION['access_granted'])) {
    if (isset($_POST['access'])) {
        $_SESSION['access_granted'] = true;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Site under construction</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0;
                background: #f5f5f5;
            }
            .access-form {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                text-align: center;
            }
            h2, p {
                color: #333;
                font-size: 24px;
            }
            button {
                padding: 12px 30px;
                font-size: 16px;
                background: transparent;
                color: transparent;
                border: 1px solid transparent;
                border-radius: 4px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <form method="POST" class="access-form">
            <h2>Site under construction</h2>
            <p>See you later 😉✌️</p>
            <button type="submit" name="access">OK</button>
        </form>
    </body>
    </html>';
    exit;
}
*/

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Controllers/DealsController.php';
require_once __DIR__ . '/src/Controllers/BlogController.php';
// Admin promo function controllers and repository
require_once __DIR__ . '/src/Controllers/AdminPromoController.php';
require_once __DIR__ . '/repo/ContentRepository.php';

use App\Router;
use App\Controllers\BookingController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    $config = require __DIR__ . '/config/app.php';
    session_start([
        'cookie_lifetime' => $config['session']['lifetime'],
        'cookie_secure' => $config['session']['secure'],
        'cookie_httponly' => $config['session']['httponly'],
    ]);
}

// Error handling
if (!$_ENV['APP_DEBUG']) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Initialize router
$router = new Router();
$language = $router->getLanguage();

// Public routes - Homepage
$router->get('/', function () use ($language) {
    $controller = new PageController($language);
    $controller->home();
});

// Booking page
$router->get('/rezervacia', function () use ($language) {
    $controller = new BookingController($language);
    $controller->index();
});

$router->get('/booking', function () use ($language) {
    $controller = new BookingController($language);
    $controller->index();
});

// Public routes - Static pages (SK without prefix)
$router->get('/biliard-a-hry', function () use ($language) {
    $controller = new PageController($language);
    $controller->games();
});

$router->get('/cennik', function () use ($language) {
    $controller = new PageController($language);
    $controller->pricing();
});

$router->get('/akcie', function () use ($language) {
    $controller = new PageController($language);
    $controller->deals();
});

$router->get('/kaviaren', function () use ($language) {
    $controller = new PageController($language);
    $controller->cafe();
});

$router->get('/kontakt', function () use ($language) {
    $controller = new PageController($language);
    $controller->contact();
});

// Privacy & Terms
$router->get('/ochrana-osobnych-udajov', function () use ($language) {
    (new PageController($language))->privacy();
});

$router->get('/vop', function () use ($language) {
    (new PageController($language))->terms();
});

$router->get('/cookies', function () use ($language) {
    (new PageController($language))->cookies();
});

// International URL support
$router->get('/privacy', function () use ($language) {
    (new PageController($language))->privacy();
});
$router->get('/terms', function () use ($language) {
    (new PageController($language))->terms();
});

// Blog routes
$router->get('/blog', function () use ($router, $language) {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader, [
        'cache' => false,
        'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    ]);

    // Load translations
    $translationsFile = __DIR__ . '/translations/' . $language . '.php';
    $translations = file_exists($translationsFile) ? require $translationsFile : [];

    // Add globals
    $twig->addGlobal('router', $router);
    $twig->addGlobal('language', $language);
    $twig->addGlobal('translations', $translations);

    // Add url function
    $twig->addFunction(new \Twig\TwigFunction('url', function (string $path, ?string $lang = null) use ($router) {
        return $router->url($path, $lang);
    }));

    // Add trans filter
    $twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
        return $translations[$key] ?? $key;
    }));

    $controller = new \App\Controllers\BlogController($twig, $router);
    $controller->index();
});

$router->get('/blog/{slug}', function ($slug) use ($router, $language) {
    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
    $twig = new \Twig\Environment($loader, [
        'cache' => false,
        'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    ]);

    // Load translations
    $translationsFile = __DIR__ . '/translations/' . $language . '.php';
    $translations = file_exists($translationsFile) ? require $translationsFile : [];

    // Add globals
    $twig->addGlobal('router', $router);
    $twig->addGlobal('language', $language);
    $twig->addGlobal('translations', $translations);

    // Add url function
    $twig->addFunction(new \Twig\TwigFunction('url', function (string $path, ?string $lang = null) use ($router) {
        return $router->url($path, $lang);
    }));

    // Add trans filter
    $twig->addFilter(new \Twig\TwigFilter('trans', function ($key) use ($translations) {
        return $translations[$key] ?? $key;
    }));

    $controller = new \App\Controllers\BlogController($twig, $router);
    $controller->show($slug);
});

// Public routes - Static pages (EN/RU/UA/DE with prefix - using English slugs)
$router->get('/games', function () use ($language) {
    $controller = new PageController($language);
    $controller->games();
});

$router->get('/pricing', function () use ($language) {
    $controller = new PageController($language);
    $controller->pricing();
});

$router->get('/deals', function () use ($language) {
    $controller = new PageController($language);
    $controller->deals();
});

$router->get('/cafe', function () use ($language) {
    $controller = new PageController($language);
    $controller->cafe();
});

$router->get('/contact', function () use ($language) {
    $controller = new PageController($language);
    $controller->contact();
});

// Privacy & Terms
$router->get('/ochrana-osobnych-udajov', function () use ($language) {
    (new PageController($language))->privacy();
});

$router->get('/vop', function () use ($language) {
    (new PageController($language))->terms();
});

$router->get('/cookies', function () use ($language) {
    (new PageController($language))->cookies();
});

// International URL support (optional)
$router->get('/privacy', function () use ($language) {
    (new PageController($language))->privacy();
});
$router->get('/terms', function () use ($language) {
    (new PageController($language))->terms();
});

// Booking API routes
$router->get('/api/slots', function () use ($language) {
    $controller = new BookingController($language);
    $controller->getAvailableSlots();
});

$router->get('/api/price', function () use ($language) {
    $controller = new BookingController($language);
    $controller->calculatePrice();
});

$router->get('/api/resources-availability', function () use ($language) {
    $controller = new BookingController($language);
    $controller->getResourcesAvailability();
});

$router->get('/api/coupon/validate', function () use ($language) {
    $controller = new BookingController($language);
    $controller->validateCoupon();
});

$router->post('/api/booking/create', function () use ($language) {
    $controller = new BookingController($language);
    $controller->create();
});

$router->post('/booking/submit', function () use ($language) {
    $controller = new BookingController($language);
    $controller->submit();
});

$router->get('/booking/success', function () use ($language) {
    $controller = new BookingController($language);
    $controller->success();
});

$router->get('/booking/cancel', function () use ($language) {
    $controller = new BookingController($language);
    $controller->cancel();
});

// Review page
$router->get('/review', function () use ($language) {
    $controller = new \App\Controllers\ReviewController($language);
    $controller->show();
});

// Admin routes
$router->get('/admin/login', function () use ($language) {
    $controller = new AuthController($language);
    $controller->showLogin();
});

$router->post('/admin/login', function () use ($language) {
    $controller = new AuthController($language);
    $controller->login();
});

$router->get('/admin/logout', function () use ($language) {
    $controller = new AuthController($language);
    $controller->logout();
});

$router->get('/admin', function () use ($language) {
    $controller = new AdminController($language);
    $controller->dashboard();
});

$router->get('/admin/bookings', function () use ($language) {
    $controller = new AdminController($language);
    $controller->listBookings();
});

$router->post('/admin/bookings/update', function () use ($language) {
    $controller = new AdminController($language);
    $controller->updateBookingStatus();
});

$router->get('/admin/holidays', function () use ($language) {
    $controller = new AdminController($language);
    $controller->manageHolidays();
});

// Admin Reports
$router->get('/admin/reports', function () use ($language) {
    (new AdminController($language))->reports();
});
$router->get('/admin/reports/view', function () use ($language) {
    (new AdminController($language))->viewReport();
});
$router->get('/admin/reports/download', function () use ($language) {
    (new AdminController($language))->downloadReport();
});

// Admin Blocking
$router->get('/admin/blocking', function () use ($language) {
    $controller = new AdminController($language);
    $controller->blocking();
});

// Calendar Blocking
$router->post('/admin/blocking/calendar/add', function () use ($language) {
    (new AdminController($language))->addCalendarBlock();
});

$router->post('/admin/blocking/calendar/delete', function () use ($language) {
    (new AdminController($language))->deleteCalendarBlock();
});

// Popup Settings
$router->get('/admin/popup', function () use ($language) {
    (new AdminController($language))->popupSettings();
});

$router->post('/admin/popup/save', function () use ($language) {
    (new AdminController($language))->savePopupSettings();
});

$router->get('/api/blocked-dates', function () {
    $db = \App\Database\Database::getInstance();
    $dates = $db->query("SELECT date FROM calendar_blocked_dates ORDER BY date ASC")->fetchAll(\PDO::FETCH_COLUMN);
    $holidays = $db->query("SELECT holiday_date FROM holidays ORDER BY holiday_date ASC")->fetchAll(\PDO::FETCH_COLUMN);
    header('Content-Type: application/json');
    echo json_encode(['dates' => $dates, 'holidays' => $holidays]);
});

$router->get('/admin/promo', function () use ($language) {
    (new AdminController($language))->managePromo();
});

$router->post('/admin/promo/create', function () use ($language) {
    (new AdminController($language))->createPromo();
});

$router->post('/admin/promo/generate', function () use ($language) {
    (new AdminController($language))->generatePromo();
});

$router->post('/admin/promo/delete', function () use ($language) {
    (new AdminController($language))->deletePromo();
});

$router->post('/admin/holidays/add', function () use ($language) {
    (new AdminController($language))->addHoliday();
});


$router->post('/admin/holidays/delete', function () use ($language) {
    (new AdminController($language))->deleteHoliday();
});

// Admin API: Cafe POS & Extras
$router->get('/admin/api/cafe-menu', function () use ($language) {
    (new AdminController($language))->getCafeMenu();
});

$router->get('/admin/api/booking-extras', function () use ($language) {
    (new AdminController($language))->getBookingExtras();
});


$router->post('/admin/api/update-extras', function () use ($language) {
    (new AdminController($language))->updateBookingExtras();
});

$router->get('/admin/api/upsell-suggestions', function () use ($language) {
    (new AdminController($language))->getUpsellSuggestions();
});



// Telegram webhook endpoint (for future implementation)
$router->post('/webhook/telegram', function () {
    $logFile = __DIR__ . '/telegram_debug.log';
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
        $callbackQuery = $update['callback_query'];
        $data = $callbackQuery['data'];
        $messageId = $callbackQuery['message']['message_id'];
        $chatId = $callbackQuery['message']['chat']['id'];

        // Check access
        if (!in_array((string)$chatId, $allowedChats, true)) {
            http_response_code(403);
            exit;
        }

        try {
            if (strpos($data, 'confirm_') === 0) {
                $bookingId = (int)str_replace('confirm_', '', $data);

                $bookingService = new \App\Services\BookingService();
                $result = $bookingService->attemptConfirmation($bookingId, false);

                $originalText = $callbackQuery['message']['text'];
                $lines = explode("\n", $originalText);

                if ($result['success']) {
                    $lines[0] = '✅ Potvrdené!';
                    $newText = implode("\n", $lines);
                    $answerText = 'Rezervácia potvrdená ✅';

                    $editData = [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $newText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode([
                            'inline_keyboard' => [[
                                ['text' => '❌ Zrušiť', 'callback_data' => 'cancel_' . $bookingId]
                            ]]
                        ])
                    ];
                } else {
                    if (!empty($result['conflict'])) {
                        $lines[0] = '❌ Zrušené (Termín obsadený)!';
                        $answerText = '❌ Termín obsadený! Rezervácia zrušená.';
                    } else {
                        $lines[0] = '⚠️ Chyba: ' . ($result['error'] ?? 'Unknown');
                        $answerText = '⚠️ Chyba pri potvrdení';
                    }
                    $newText = implode("\n", $lines);

                    $editData = [
                        'chat_id' => $chatId,
                        'message_id' => $messageId,
                        'text' => $newText,
                        'parse_mode' => 'HTML'
                    ];
                }

                $url = "https://api.telegram.org/bot{$token}/editMessageText";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($editData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resEdit = curl_exec($ch);
                curl_close($ch);
                file_put_contents(__DIR__ . '/telegram_debug.log', "EDIT RESPONSE: " . $resEdit . "\n", FILE_APPEND);

                $answerUrl = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
                $answerData = [
                    'callback_query_id' => $callbackQuery['id'],
                    'text' => $answerText
                ];

                $ch = curl_init($answerUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($answerData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $resAnswer = curl_exec($ch);
                curl_close($ch);
                file_put_contents(__DIR__ . '/telegram_debug.log', "ANSWER RESPONSE: " . $resAnswer . "\n", FILE_APPEND);
            } elseif (strpos($data, 'cancel_') === 0) {
                $bookingId = (int)str_replace('cancel_', '', $data);

                $bookingService = new \App\Services\BookingService();
                $bookingService->updateStatus($bookingId, 'cancelled');

                $originalText = $callbackQuery['message']['text'];
                $lines = explode("\n", $originalText);
                $lines[0] = '❌ Zrušené!';
                $newText = implode("\n", $lines);

                $url = "https://api.telegram.org/bot{$token}/editMessageText";
                $editData = [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $newText,
                    'parse_mode' => 'HTML'
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($editData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);

                $answerUrl = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
                $answerData = [
                    'callback_query_id' => $callbackQuery['id'],
                    'text' => 'Rezervácia zrušená ❌'
                ];

                $ch = curl_init($answerUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($answerData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (\Throwable $e) {
            file_put_contents($logFile, "Callback Error: " . $e->getMessage() . "\n", FILE_APPEND);

            // Notify user of error
            $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
            $answerData = [
                'callback_query_id' => $callbackQuery['id'],
                'text' => '❌ Chyba: ' . substr($e->getMessage(), 0, 100),
                'show_alert' => true
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($answerData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    } elseif (isset($update['message'])) {
        $message = $update['message'];
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? '';

        // Check access
        if (!in_array((string)$chatId, $allowedChats, true)) {
            http_response_code(403);
            exit;
        }

        if ($text === '?') {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => 'Zadajte číslo rezervácie:',
                'reply_markup' => json_encode(['force_reply' => true])
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } elseif ($text === '%') {
            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $data = [
                'chat_id' => $chatId,
                'text' => 'Zadajte promo kód:',
                'reply_markup' => json_encode(['force_reply' => true])
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } elseif (isset($message['reply_to_message']) && $message['reply_to_message']['text'] === 'Zadajte promo kód:') {
            $couponCode = trim($text);

            try {
                $conn = \App\Database\Database::getInstance();

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

                $url = "https://api.telegram.org/bot{$token}/sendMessage";
                $data = [
                    'chat_id' => $chatId,
                    'text' => $responseText,
                    'parse_mode' => 'HTML'
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Throwable $e) {
                file_put_contents($logFile, "Coupon Error: " . $e->getMessage() . "\n", FILE_APPEND);

                $url = "https://api.telegram.org/bot{$token}/sendMessage";
                $data = [
                    'chat_id' => $chatId,
                    'text' => "❌ Chyba: " . $e->getMessage()
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_exec($ch);
                curl_close($ch);
            }
        } elseif (isset($message['reply_to_message']) && $message['reply_to_message']['text'] === 'Zadajte číslo rezervácie:') {
            $bookingId = (int)trim($text);

            file_put_contents($logFile, "Searching for booking ID: {$bookingId}\n", FILE_APPEND);

            try {
                file_put_contents($logFile, "Step 1: Getting DB connection\n", FILE_APPEND);
                $conn = \App\Database\Database::getInstance();

                file_put_contents($logFile, "Step 2: Preparing query\n", FILE_APPEND);
                $stmt = $conn->prepare("
                    SELECT b.* 
                    FROM bookings b 
                    WHERE b.id = ?
                ");

                file_put_contents($logFile, "Step 3: Executing query\n", FILE_APPEND);
                $stmt->execute([$bookingId]);

                file_put_contents($logFile, "Step 4: Fetching result\n", FILE_APPEND);
                $booking = $stmt->fetch(\PDO::FETCH_ASSOC);

                file_put_contents($logFile, "Step 5: Result = " . json_encode($booking) . "\n", FILE_APPEND);

                if ($booking) {
                    // Get service slug
                    $serviceStmt = $conn->prepare("SELECT slug FROM services WHERE id = ?");
                    $serviceStmt->execute([$booking['service_id']]);
                    $service = $serviceStmt->fetch(\PDO::FETCH_ASSOC);
                    $booking['service_name'] = ucfirst($service['slug'] ?? 'Unknown');

                    // Get table number from resource name
                    $resourceStmt = $conn->prepare("SELECT name FROM resources WHERE id = ?");
                    $resourceStmt->execute([$booking['resource_id']]);
                    $resourceName = $resourceStmt->fetchColumn();

                    // Extract table number from name (e.g., "Pool - Stôl 2" -> "2")
                    $tableNumber = $booking['resource_id']; // fallback to resource_id
                    if ($resourceName && preg_match('/(\d+)/', $resourceName, $matches)) {
                        $tableNumber = $matches[1];
                    }

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
                    $messageText .= "🎱 Stôl č.: {$tableNumber}\n\n";
                    $messageText .= "{$flag} Jazyk: {$booking['language']}\n";
                    $messageText .= "👤 Meno: {$booking['customer_name']}\n";
                    $messageText .= "📞 Telefón: {$booking['customer_phone']}\n\n";

                    if (!empty($booking['notes'])) {
                        $messageText .= "📝 Poznámka: {$booking['notes']}\n\n";
                    }

                    // Price with discount info
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

                    $url = "https://api.telegram.org/bot{$token}/sendMessage";
                    $data = [
                        'chat_id' => $chatId,
                        'text' => $messageText,
                        'parse_mode' => 'HTML'
                    ];

                    if ($replyMarkup) {
                        $data['reply_markup'] = $replyMarkup;
                    }

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                } else {
                    $url = "https://api.telegram.org/bot{$token}/sendMessage";
                    $data = [
                        'chat_id' => $chatId,
                        'text' => "❌ Rezervácia č.: {$bookingId} nebola nájdená"
                    ];

                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($ch);
                    curl_close($ch);
                }
            } catch (\Exception $e) {
                file_put_contents($logFile, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }

    echo json_encode(['ok' => true]);
});

// Dispatch request
$router->dispatch();
