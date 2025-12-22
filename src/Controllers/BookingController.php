<?php

namespace App\Controllers;

use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\NotificationService;
use App\Services\Service;

class BookingController extends Controller
{
    private BookingService $bookingService;
    private PricingService $pricingService;
    private NotificationService $notificationService;
    private Service $serviceModel;
    
    public function __construct(string $language = 'sk')
    {
        parent::__construct($language);
        $this->bookingService = new BookingService();
        $this->pricingService = new PricingService();
        $this->notificationService = new NotificationService();
        $this->serviceModel = new Service();
    }
    
    /**
     * Show booking form (home page)
     */
    public function index(): void
    {
        $services = $this->serviceModel->getWithTranslations($this->language);
        
        $this->render('index.twig', [
            'services' => $services,
            'page_title' => 'Biliardovna.sk - Online rezervácie',
            'current_page' => 'home'
        ]);
    }
    
    /**
     * Get available slots for a service/date (AJAX)
     */
    public function getAvailableSlots(): void
    {
        $serviceId = (int)($_GET['service_id'] ?? 0);
        $date = $_GET['date'] ?? '';
        
        if (!$serviceId || !$date) {
            $this->json(['error' => 'Missing parameters'], 400);
            return;
        }
        
        try {
            $slots = $this->pricingService->getAvailableSlots($serviceId, $date);
            
            foreach ($slots as &$slot) {
                $slot['available'] = $this->bookingService->checkAvailability(
                    $serviceId,
                    $date,
                    $slot['time'],
                    1
                );
            }
            
            $this->json(['slots' => $slots]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Calculate price for a booking (AJAX)
     */
    public function calculatePrice(): void
    {
        $serviceId = (int)($_GET['service_id'] ?? 0);
        $date = $_GET['date'] ?? '';
        $time = $_GET['time'] ?? '';
        $duration = (int)($_GET['duration'] ?? 1);
        
        if (!$serviceId || !$date || !$time) {
            $this->json(['error' => 'Missing parameters'], 400);
            return;
        }
        
        try {
            $breakdown = $this->pricingService->getPriceBreakdown(
                $serviceId,
                $date,
                $time,
                $duration
            );
            
            $this->json($breakdown);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get resources availability for a service/date (AJAX)
     */
    public function getResourcesAvailability(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $serviceId = (int)($_GET['service_id'] ?? 0);
        $date = $_GET['date'] ?? '';
        
        if (!$serviceId || !$date) {
            $this->json(['error' => 'Missing parameters'], 400);
            return;
        }
        
        try {
            $availability = $this->bookingService->getResourcesAvailability($serviceId, $date);
            
            if (isset($availability['resources'])) {
                $lang = $_GET['lang'] ?? $this->language;
                $translations = $this->translationService->getUITranslations($lang);
                
                foreach ($availability['resources'] as &$resource) {
                    $key = 'resource_' . $resource['id'];
                    $resource['name'] = $translations[$key] ?? $resource['name'];
                }
                unset($resource);
            }
            
            $this->json($availability);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Validate coupon code (AJAX)
     */
    public function validateCoupon(): void
    {
        $couponCode = $_GET['code'] ?? '';
        
        if (!$couponCode) {
            $this->json(['valid' => false, 'message' => 'Missing coupon code'], 400);
            return;
        }
        
        try {
            $db = \App\Database\Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT id, type, discount_percent, used, valid_until
                FROM coupons 
                WHERE code = ? 
                AND (valid_until IS NULL OR valid_until >= CURDATE())
            ");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                $this->json([
                    'valid' => false,
                    'message' => 'Invalid or expired coupon'
                ]);
                return;
            }
            
            if ($coupon['type'] === 'auto' && $coupon['used'] >= 1) {
                $this->json([
                    'valid' => false,
                    'message' => 'Coupon already used'
                ]);
                return;
            }
            
            if ($coupon['type'] === 'promo' && $coupon['used'] >= 1) {
                $this->json([
                    'valid' => false,
                    'message' => 'Coupon already used'
                ]);
                return;
            }
            
            $this->json([
                'valid' => true,
                'discount_percent' => (int)$coupon['discount_percent'],
                'message' => 'Coupon valid'
            ]);
            
        } catch (\Exception $e) {
            $this->json(['valid' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create booking (AJAX)
     */
    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Invalid request method'], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);

        $token = trim($input['recaptcha_token'] ?? '');
        $action = $input['recaptcha_action'] ?? 'booking_submit';

        [$passed, $reason] = $this->verifyRecaptchaV3($token, $action);
        if (!$passed) {
            $this->json(['success' => false, 'message' => 'captcha_failed', 'reason' => $reason], 400);
            return;
        }
        
        $data = [
            'service_id' => (int)($input['service_id'] ?? 0),
            'resource_id' => (int)($input['resource_id'] ?? 0),
            'booking_date' => $input['date'] ?? '',
            'start_time' => $input['start_time'] ?? '',
            'end_time' => $input['end_time'] ?? '',
            'customer_name' => $input['name'] ?? '',
            'customer_phone' => $input['phone'] ?? '',
            'customer_email' => $input['email'] ?? '',
            'notes' => $input['notes'] ?? '',
            'language' => $input['language'] ?? $_SESSION['language'] ?? 'sk',
            'coupon_code' => $input['coupon_code'] ?? null,
            'discount_percent' => isset($input['discount_percent']) ? (float)$input['discount_percent'] : null,
            'original_price' => isset($input['original_price']) ? (float)$input['original_price'] : null
        ];
        
        try {
            $result = $this->bookingService->create($data);
            
            if ($result['success']) {
                $booking = $result['booking'];
                
                $this->notificationService->sendTelegramNotification($booking, 'new');
                
                if (!empty($booking['customer_email'])) {
                    $this->notificationService->sendEmailNotification($booking);
                }
                
                $this->json([
                    'success' => true,
                    'booking_id' => $booking['id'],
                    'message' => 'Booking created successfully'
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'message' => implode(', ', $result['errors'] ?? ['Unknown error'])
                ], 400);
            }
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit booking (regular form - not used, kept for compatibility)
     */
    public function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
            return;
        }

        $token = trim($_POST['recaptcha_token'] ?? '');
        $action = $_POST['recaptcha_action'] ?? 'booking_submit';

        [$passed, $reason] = $this->verifyRecaptchaV3($token, $action);
        if (!$passed) {
            if (isset($_POST['ajax'])) {
                $this->json(['success' => false, 'message' => 'captcha_failed', 'reason' => $reason], 400);
            } else {
                $_SESSION['booking_errors'] = ['captcha_failed' => $reason];
                $this->redirect('/');
            }
            return;
        }
        
        $data = [
            'service_id' => (int)($_POST['service_id'] ?? 0),
            'booking_date' => $_POST['booking_date'] ?? '',
            'start_time' => $_POST['start_time'] ?? '',
            'duration_hours' => (int)($_POST['duration_hours'] ?? 1),
            'customer_name' => $_POST['customer_name'] ?? '',
            'customer_phone' => $_POST['customer_phone'] ?? '',
            'customer_email' => $_POST['customer_email'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'language' => $this->language,
            'coupon_code' => $_POST['coupon_code'] ?? null,
            'discount_percent' => isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : null,
            'original_price' => isset($_POST['original_price']) ? (float)$_POST['original_price'] : null
        ];
        
        $result = $this->bookingService->create($data);
        
        if ($result['success']) {
            $booking = $result['booking'];
            $this->notificationService->sendTelegramNotification($booking, 'new');
            
            if (isset($_POST['ajax'])) {
                $this->json($result);
            } else {
                $_SESSION['booking_success'] = true;
                $_SESSION['booking_id'] = $result['booking_id'];
                $this->redirect('/booking/success');
            }
        } else {
            if (isset($_POST['ajax'])) {
                $this->json($result, 400);
            } else {
                $_SESSION['booking_errors'] = $result['errors'];
                $this->redirect('/');
            }
        }
    }
    
    /**
     * Show booking success page
     */
    public function success(): void
    {
        if (empty($_SESSION['booking_success'])) {
            $this->redirect('/');
            return;
        }
        
        $bookingId = $_SESSION['booking_id'] ?? null;
        unset($_SESSION['booking_success'], $_SESSION['booking_id']);
        
        $this->render('public/success.twig', [
            'booking_id' => $bookingId,
            'page_title' => 'Rezervácia úspešná'
        ]);
    }

    /**
     * Cancel booking by token (from email link)
     */
    public function cancel(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (!$token) {
            http_response_code(400);
            echo "<h1>Invalid token</h1>";
            return;
        }
        
        try {
            $booking = $this->bookingService->getByToken($token);
            
            if (!$booking) {
                http_response_code(404);
                echo "<h1>Booking not found</h1>";
                return;
            }
            
            if ($booking['status'] === 'cancelled') {
                echo $this->twig->render('cancel.twig', [
                    'already_cancelled' => true,
                    'language' => $this->language,
                    'translations' => [
                        'cancel_already_cancelled' => $this->trans('cancel_already_cancelled'),
                        'cancel_already_cancelled_text' => $this->trans('cancel_already_cancelled_text')
                    ]
                ]);
                return;
            }
            
            $this->bookingService->updateStatus($booking['id'], 'cancelled');
            
            echo $this->twig->render('cancel.twig', [
                'success' => true,
                'language' => $this->language,
                'translations' => [
                    'cancel_success' => $this->trans('cancel_success'),
                    'cancel_success_text' => $this->trans('cancel_success_text'),
                    'cancel_welcome_back' => $this->trans('cancel_welcome_back')
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo "<h1>Error: " . htmlspecialchars($e->getMessage()) . "</h1>";
        }
    }

    /**
     * Verify reCAPTCHA v3 token
     */
    private function verifyRecaptchaV3(string $token, string $expectedAction): array
    {
        $secret = getenv('RECAPTCHA_SECRET_KEY') ?: ($_ENV['RECAPTCHA_SECRET_KEY'] ?? '');
        $minScore = 0.5;

        if (!$secret) return [false, 'missing_secret'];
        if (!$token) return [false, 'missing_token'];

        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            return [false, 'http_error:' . $err];
        }
        curl_close($ch);

        $res = json_decode($raw, true) ?: [];
        $ok =
            (($res['success'] ?? false) === true) &&
            (($res['score'] ?? 0) >= $minScore) &&
            (($res['action'] ?? '') === $expectedAction);

        if ($ok) return [true, 'ok'];

        $errors = isset($res['error-codes']) ? implode(',', (array)$res['error-codes']) : 'unknown';
        $diag = sprintf(
            'success=%s score=%.2f action=%s errors=%s',
            ($res['success'] ?? false) ? '1' : '0',
            $res['score'] ?? 0,
            $res['action'] ?? 'none',
            $errors
        );
        return [false, $diag];
    }
}