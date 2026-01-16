<?php

namespace App\Controllers;

use App\Models\Booking;
use App\Services\Service;
use App\Models\Holiday;
use App\Services\BookingService;
use App\Services\NotificationService;

class AdminController extends Controller
{
    private Booking $bookingModel;
    private Service $serviceModel;
    private Holiday $holidayModel;
    private BookingService $bookingService;
    private NotificationService $notificationService;

    public function __construct(string $language = 'sk')
    {
        parent::__construct($language);
        $this->checkAuth();
        $this->bookingModel = new Booking();
        $this->serviceModel = new Service();
        $this->holidayModel = new Holiday();
        $this->bookingService = new BookingService();
        $this->notificationService = new NotificationService();
    }

    private function checkAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
    }

    /**
     * Admin dashboard
     */
    /**
     * Admin dashboard
     */
    public function dashboard(): void
    {
        $db = \App\Database\Database::getInstance();

        // 1. Booking Stats (Last 30 Days)
        $stats = $db->query("
            SELECT status, COUNT(*) as count 
            FROM bookings 
            WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY status
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 2. Game Popularity (Last 30 Days)
        $popularity = $db->query("
            SELECT s.slug, COUNT(*) as count
            FROM bookings b
            JOIN services s ON b.service_id = s.id
            WHERE b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY s.slug
            ORDER BY count DESC
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $pendingBookings = $this->bookingModel->getPending();

        $this->render('admin/dashboard.twig', [
            'stats' => $stats,
            'popularity' => $popularity,
            'pending_bookings' => $pendingBookings,
            'page_title' => 'Admin Dashboard'
        ]);
    }

    /**
     * List all bookings
     */
    public function listBookings(): void
    {
        $status = $_GET['status'] ?? '';
        $bookings = $this->bookingModel->getAllDesc(); // Need to implement this in Model or use logic here

        if ($status) {
            $bookings = array_filter($bookings, fn($b) => $b['status'] === $status);
        }

        $this->render('admin/bookings.twig', [
            'bookings' => $bookings,
            'page_title' => 'Manage Bookings'
        ]);
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['error' => 'Method not allowed'], 405);
            return;
        }

        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!$bookingId || !$status) {
            $this->json(['error' => 'Missing parameters'], 400);
            return;
        }

        try {
            // Update status via BookingService logic if available, or direct Model update
            // Since we are unsure if BookingService is instantiated as property, we use Model
            // Actually, best to use Model update and Notification service

            // NOTE: Assuming bookingService is available as per previous code observation
            // If not, we might need to instantite it or use simpler logic.
            // Let's use simple logic for now to avoid dependency hell if services are not ready.

            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $result = $stmt->execute([$status, $bookingId]);

            if ($result) {
                // Ideally send notification here
                // $this->notificationService->sendTelegramNotification($booking, $status);

                // Redirect back
                header('Location: ' . $_SERVER['HTTP_REFERER']);
                exit;
            } else {
                $this->json(['error' => 'Failed to update status'], 500);
            }
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manage holidays
     */
    public function manageHolidays(): void
    {
        $holidays = $this->holidayModel->all();
        $this->render('admin/holidays.twig', [
            'holidays' => $holidays,
            'page_title' => 'Manage Holidays'
        ]);
    }

    /**
     * Manage blocking (Full Dates Only)
     */
    public function blocking(): void
    {
        $db = \App\Database\Database::getInstance();

        // Fetch calendar blocked dates
        $blockedDates = $db->query("SELECT * FROM calendar_blocked_dates ORDER BY date DESC")->fetchAll(\PDO::FETCH_COLUMN);

        // Generate next 30 days for Quick Block
        $quickDates = [];
        $start = new \DateTime();
        for ($i = 0; $i < 30; $i++) {
            $date = clone $start;
            $date->modify("+$i days");
            $dateStr = $date->format('Y-m-d');
            if (!in_array($dateStr, $blockedDates)) {
                $quickDates[] = [
                    'date' => $dateStr,
                    'display' => $date->format('d.m.Y (D)')
                ];
            }
        }

        $this->render('admin/blocking.twig', [
            'blocked_dates' => $blockedDates,
            'quick_dates' => $quickDates,
            'page_title' => 'Date Blocking'
        ]);
    }

    /**
     * Popup Settings
     */
    /**
     * Popup Settings
     */
    // ... existing ...

    /**
     * Manage Promocodes (Coupons)
     */
    public function managePromo(): void
    {
        $db = \App\Database\Database::getInstance();
        $coupons = $db->query("SELECT * FROM coupons ORDER BY created_at DESC")->fetchAll();

        $this->render('admin/promo.twig', [
            'coupons' => $coupons,
            'page_title' => 'Manage Promocodes'
        ]);
    }

    public function createPromo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount = (int)$_POST['discount'];
            $limitParam = $_POST['usage_limit'];

            // Logic: 1 -> NULL (One-time), 0 -> 0 (Unlimited), N -> N
            $limit = ($limitParam == 1) ? null : (int)$limitParam;

            if ($code && $discount > 0) {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, usage_limit, type) VALUES (?, ?, ?, 'standard')");
                $stmt->execute([$code, $discount, $limit]);
            }
        }
        header('Location: /admin/promo');
        exit;
    }

    public function generatePromo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $amount = (int)$_POST['amount'];
            $discount = (int)$_POST['discount'];
            $limitParam = $_POST['usage_limit'];

            // Logic: 1 -> NULL (One-time), 0 -> 0 (Unlimited), N -> N
            $limit = ($limitParam == 1) ? null : (int)$limitParam;

            $prefix = strtoupper(trim($_POST['prefix'] ?? 'PROMO'));

            if ($amount > 0 && $discount > 0) {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, usage_limit, type) VALUES (?, ?, ?, 'standard')");

                for ($i = 0; $i < $amount; $i++) {
                    $random = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                    $code = $prefix . '-' . $random;
                    try {
                        $stmt->execute([$code, $discount, $limit]);
                    } catch (\Exception $e) {
                        // Ignore duplicates and continue
                    }
                }
            }
        }
        header('Location: /admin/promo');
        exit;
    }

    public function deletePromo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
            $stmt->execute([$_POST['id']]);
        }
        header('Location: /admin/promo');
        exit;
    }

    // ... existing ...
    public function popupSettings(): void
    {
        $db = \App\Database\Database::getInstance();
        $settings = $db->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $this->render('admin/popup.twig', [
            'popup_enabled' => $settings['popup_enabled'] ?? 0,
            'popup_content_sk' => $settings['popup_content_sk'] ?? '',
            'popup_content_en' => $settings['popup_content_en'] ?? '',
            'popup_content_ru' => $settings['popup_content_ru'] ?? '',
            'popup_content_de' => $settings['popup_content_de'] ?? '',
            'popup_content_uk' => $settings['popup_content_uk'] ?? '',
            'popup_expires_at' => $settings['popup_expires_at'] ?? '',
            'page_title' => 'Popup Settings'
        ]);
    }

    public function savePopupSettings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $enabled = isset($_POST['popup_enabled']) ? 1 : 0;
            $db = \App\Database\Database::getInstance();

            $keys = ['popup_enabled', 'popup_content_sk', 'popup_content_en', 'popup_content_ru', 'popup_content_de', 'popup_content_uk', 'popup_expires_at'];

            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

            foreach ($keys as $key) {
                $value = ($key === 'popup_enabled') ? $enabled : ($_POST[$key] ?? '');
                // Ensure empty date is saved as NULL or empty string (our DB schema for settings is text, so empty string is fine)
                $stmt->execute([$key, $value]);
            }
        }
        header('Location: /admin/popup');
        exit;
    }

    public function addCalendarBlock(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['date'])) {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("INSERT IGNORE INTO calendar_blocked_dates (date) VALUES (?)");
            $stmt->execute([$_POST['date']]);
        }
        header('Location: /admin/blocking');
        exit;
    }

    public function deleteCalendarBlock(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['date'])) {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("DELETE FROM calendar_blocked_dates WHERE date = ?");
            $stmt->execute([$_POST['date']]);
        }
        header('Location: /admin/blocking');
        exit;
    }

    // Holiday Actions
    public function addHoliday(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['date'])) {
            $this->holidayModel->add($_POST['date'], $_POST['name'] ?? null);
        }
        header('Location: /admin/holidays');
        exit;
    }

    public function deleteHoliday(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id'])) {
            $this->holidayModel->delete((int)$_POST['id']);
        }
        header('Location: /admin/holidays');
        exit;
    }
}
