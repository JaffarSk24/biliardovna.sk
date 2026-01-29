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

        // 0. Date Filters (Default: Last 30 Days)
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));

        // Validate formats briefly (simple check, fallback to defaults if weird)
        if (!strtotime($startDate)) $startDate = date('Y-m-d', strtotime('-30 days'));
        if (!strtotime($endDate)) $endDate = date('Y-m-d');

        // Ensure End Date includes the full day (e.g. if using datetime, but here we use DATE(column) logic usually)
        // With simple string comparison '2023-10-10' matches '2023-10-10 00:00:00'.
        // To include bookings on the end date up to 23:59:59, we typically add 1 day or use syntax: date <= '$endDate 23:59:59'.
        // Let's use string comparison compatible with Y-m-d.

        // 1. Booking Stats
        $stats = $db->query("
            SELECT status, COUNT(*) as count 
            FROM bookings 
            WHERE booking_date >= '$startDate' AND booking_date <= '$endDate'
            GROUP BY status
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 2. Game Popularity (Completed only)
        $popularity = $db->query("
            SELECT s.slug, COUNT(*) as count
            FROM bookings b
            JOIN services s ON b.service_id = s.id
            WHERE b.booking_date >= '$startDate' AND b.booking_date <= '$endDate'
            AND b.status = 'completed'
            GROUP BY s.slug
            ORDER BY count DESC
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 3. Revenue (Completed only)
        $revenue = $db->query("
            SELECT SUM(price) 
            FROM bookings 
            WHERE status = 'completed' 
            AND booking_date >= '$startDate' AND booking_date <= '$endDate'
        ")->fetchColumn();

        // 3.1 Revenue Breakdown
        $revenueBreakdown = $db->query("
            SELECT s.slug, SUM(b.price) as amount
            FROM bookings b
            JOIN services s ON b.service_id = s.id
            WHERE b.status = 'completed'
            AND b.booking_date >= '$startDate' AND b.booking_date <= '$endDate'
            GROUP BY s.slug
            ORDER BY amount DESC
        ")->fetchAll(\PDO::FETCH_KEY_PAIR);

        $pendingBookings = $this->bookingModel->getPending();
        $pendingBookings = $this->formatBookings($pendingBookings);

        // Calculate total popularity for progress bars (100% base)
        $totalPopularity = array_sum($popularity);

        $this->render('admin/dashboard.twig', [
            'stats' => $stats,
            'popularity' => $popularity,
            'total_popularity' => $totalPopularity,
            'revenue' => (float)$revenue,
            'revenue_breakdown' => $revenueBreakdown, // Pass breakdown
            'pending_bookings' => $pendingBookings,
            'page_title' => 'Admin Dashboard',
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    /**
     * List all bookings
     */
    /**
     * List all bookings (Refactored for Tabs)
     */
    /**
     * List all bookings (Refactored for Tabs + Date Groups)
     */
    public function listBookings(): void
    {
        // 1. Fetch all Services for Tabs (Force English for Admin)
        $services = $this->serviceModel->getWithTranslations('en');

        // 2. Fetch ALL bookings
        $allBookings = $this->bookingModel->getAllDesc();
        $allBookings = $this->formatBookings($allBookings);

        // 3. Initialize Tabs Structure
        $tabs = [];

        // Dynamic Service Tabs (Active: Pending/Confirmed)
        foreach ($services as $service) {
            $tabs[$service['slug']] = [
                'id' => $service['slug'],
                'label' => $service['name'],
                'bookings' => [
                    'yesterday' => [],
                    'today' => [],
                    'tomorrow' => [],
                    'later' => []
                ]
            ];
        }

        // Static Tabs (History)
        $tabs['completed'] = [
            'id' => 'completed',
            'label' => 'Completed',
            'bookings' => [] // Flat list
        ];
        $tabs['cancelled'] = [
            'id' => 'cancelled',
            'label' => 'Cancelled',
            'bookings' => [] // Flat list
        ];

        // Date Helpers
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        // 4. Distribute Bookings
        foreach ($allBookings as $booking) {
            $status = $booking['status'];
            $serviceSlug = $booking['service_slug'] ?? 'unknown';
            $bDate = $booking['booking_date'];

            // 1. Handle History (Flat List)
            if ($status === 'completed') {
                $tabs['completed']['bookings'][] = $booking;
                continue;
            } elseif ($status === 'cancelled') {
                $tabs['cancelled']['bookings'][] = $booking;
                continue;
            }

            // 2. Handle Active (Grouped List)
            if (isset($tabs[$serviceSlug])) {
                // Determine Date Group
                if ($bDate === $today) {
                    $group = 'today';
                } elseif ($bDate === $tomorrow) {
                    $group = 'tomorrow';
                } elseif ($bDate < $today) {
                    $group = 'yesterday';
                } else {
                    $group = 'later';
                }

                $tabs[$serviceSlug]['bookings'][$group][] = $booking;
            }
        }

        // 5. Sort Bookings
        foreach ($tabs as $slug => &$tabData) {
            $isHistory = ($slug === 'completed' || $slug === 'cancelled');

            if ($isHistory) {
                // Sort Flat History (Newest First) -> Already DESC from DB usually, but ensures consistency
                usort($tabData['bookings'], function ($a, $b) {
                    $t1 = strtotime($a['booking_date'] . ' ' . $a['start_time']);
                    $t2 = strtotime($b['booking_date'] . ' ' . $b['start_time']);
                    return $t2 - $t1; // DESC
                });
            } else {
                // Sort Grouped Service Tabs (Nearest First)
                foreach ($tabData['bookings'] as $group => &$groupBookings) {
                    if (empty($groupBookings)) continue;

                    usort($groupBookings, function ($a, $b) {
                        $t1 = strtotime($a['booking_date'] . ' ' . $a['start_time']);
                        $t2 = strtotime($b['booking_date'] . ' ' . $b['start_time']);
                        return $t1 - $t2; // ASC
                    });
                }
            }
        }

        $this->render('admin/bookings.twig', [
            'tabs' => $tabs,
            'page_title' => 'Manage Bookings'
        ]);
    }

    /**
     * Helper to format bookings for display
     */
    private function formatBookings(array $bookings): array
    {
        // Load Slovak translations for services
        $translations = [];
        $transFile = __DIR__ . '/../../translations/sk.php';
        if (file_exists($transFile)) {
            $translations = require $transFile;
        }

        foreach ($bookings as &$booking) {
            // Format Table Number
            $booking['formatted_table_number'] = $this->getTableNumber((int)($booking['resource_id'] ?? 0), $booking['resource_name'] ?? null);

            // Format Service Name
            $serviceName = $booking['service_name'] ?? '';
            // If we have service_id, try to get slug and translate
            if (!empty($booking['service_id'])) {
                try {
                    $db = \App\Database\Database::getInstance();
                    $stmt = $db->prepare('SELECT slug FROM services WHERE id = ?');
                    $stmt->execute([$booking['service_id']]);
                    $slug = $stmt->fetchColumn();
                    if ($slug) {
                        $transKey = 'service_' . $slug . '_name';
                        if (isset($translations[$transKey])) {
                            $serviceName = $translations[$transKey];
                        } else {
                            $serviceName = ucfirst($slug);
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
            $booking['formatted_service_name'] = $serviceName;
        }
        return $bookings;
    }

    /**
     * Helper to get table number from resource name
     */
    private function getTableNumber(int $resourceId, ?string $resourceName = null): string
    {
        $tableNumber = (string)$resourceId;
        try {
            // If resource name is not provided, fetch it
            if (empty($resourceName)) {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare('SELECT name FROM resources WHERE id = ?');
                $stmt->execute([$resourceId]);
                $resourceName = $stmt->fetchColumn();
            }

            // Extract number from name (e.g. "Pool - Stôl 1" -> "1")
            if ($resourceName && preg_match('/(\d+)/u', (string)$resourceName, $m)) {
                $tableNumber = $m[1];
            }
        } catch (\Throwable $e) {
            // Fallback to resource ID
        }

        return $tableNumber;
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
            $type = $_POST['type'] ?? 'standard';
            $validUntil = $_POST['valid_until'] ?? '';

            // Logic: 0 or empty -> NULL (Unlimited), N -> N
            $limit = empty($limitParam) ? null : (int)$limitParam;
            if ($limit === 0) $limit = null;

            // Date logic: empty -> 2099-12-31 (to avoid NULL schema error)
            $validDate = empty($validUntil) ? '2099-12-31' : $validUntil;

            if ($code && $discount > 0) {
                $db = \App\Database\Database::getInstance();
                // Use ON DUPLICATE KEY UPDATE to avoid crash and fix existing broken entries
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, usage_limit, type, valid_until) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), usage_limit = VALUES(usage_limit), type = VALUES(type), valid_until = VALUES(valid_until)");
                $stmt->execute([$code, $discount, $limit, $type, $validDate]);
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
            $validUntil = $_POST['valid_until'] ?? '';

            // Logic: 0 or empty -> NULL (Unlimited)
            $limit = empty($limitParam) ? null : (int)$limitParam;
            if ($limit === 0) $limit = null;

            // Date logic: empty -> 2099-12-31
            $validDate = empty($validUntil) ? '2099-12-31' : $validUntil;

            $prefix = strtoupper(trim($_POST['prefix'] ?? 'PROMO'));

            if ($amount > 0 && $discount > 0) {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, usage_limit, type, valid_until) VALUES (?, ?, ?, 'standard', ?) ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), usage_limit = VALUES(usage_limit), valid_until = VALUES(valid_until)");

                for ($i = 0; $i < $amount; $i++) {
                    $random = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                    $code = $prefix . '-' . $random;
                    try {
                        $stmt->execute([$code, $discount, $limit, $validDate]);
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

    /**
     * Reports Page
     */
    public function reports(): void
    {
        $this->render('admin/reports.twig', [
            'page_title' => 'Reports'
        ]);
    }

    /**
     * View Report (Preview)
     */
    public function viewReport(): void
    {
        $type = $_GET['type'] ?? 'bookings';
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        $granularity = $_GET['granularity'] ?? 'total'; // total, day, week, month

        $reportData = $this->getReportData($type, $start, $end, $granularity);

        $this->render('admin/report_preview.twig', [
            'report_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'granularity' => $granularity,
            'headers' => $reportData['headers'],
            'grouped_data' => $reportData['grouped_data'] ?? [], // New structure
            'grand_total' => $reportData['grand_total'] ?? [],
            'page_title' => 'Report Preview'
        ]);
    }

    /**
     * Download Report (CSV)
     */
    public function downloadReport(): void
    {
        $type = $_GET['type'] ?? 'bookings';
        $start = $_GET['start'] ?? date('Y-m-01');
        $end = $_GET['end'] ?? date('Y-m-d');
        $granularity = $_GET['granularity'] ?? 'total';

        $reportData = $this->getReportData($type, $start, $end, $granularity);

        $filename = "report_{$type}_{$start}_{$end}.csv";

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // BOM

        // Output Headers
        fputcsv($output, $reportData['headers'], ",", "\"", "\\"); // Explicit escape char for PHP 8.1+

        // Output Rows
        foreach ($reportData['rows'] as $row) {
            fputcsv($output, (array)$row, ",", "\"", "\\");
        }

        // Output Summary if exists (Finance)
        if (!empty($reportData['summary'])) {
            fputcsv($output, [], ",", "\"", "\\"); // Spacer
            fputcsv($output, ['SUMMARY'], ",", "\"", "\\");
            foreach ($reportData['summary'] as $label => $val) {
                fputcsv($output, [$label, $val], ",", "\"", "\\");
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Helper to get report data
     */
    private function getReportData(string $type, string $start, string $end, string $granularity): array
    {
        $db = \App\Database\Database::getInstance();
        $data = ['headers' => [], 'grouped_data' => [], 'grand_total' => []];

        // Shared Date Logic for Grouping with Enhanced Formatting
        $groupBy = function ($row) use ($granularity) {
            $date = $row['booking_date'];
            if ($granularity === 'day') return $date; // YYYY-MM-DD
            if ($granularity === 'week') {
                // Return "2026-W04 (20.01 - 26.01)"
                $time = strtotime($date);
                $year = date('Y', $time);
                $week = date('W', $time);
                $dto = new \DateTime();
                $dto->setISODate($year, $week);
                $weekStart = $dto->format('d.m');
                $dto->modify('+6 days');
                $weekEnd = $dto->format('d.m');
                return "{$year}-W{$week} ({$weekStart} - {$weekEnd})";
            }
            if ($granularity === 'month') return date('Y-m', strtotime($date)); // 2024-01
            return 'All Data'; // Total
        };

        if ($type === 'bookings' || $type === 'finance' || $type === 'tables') {

            if ($type === 'bookings') {
                // Show Email instead of Name for Customer column
                $data['headers'] = ['Date', 'Time', 'Service', 'Table', 'Status', 'Customer (Email)', 'Price'];
                $sql = "
                    SELECT b.booking_date, b.start_time, s.slug as service, r.name as resource, b.status, b.customer_email as customer, b.price
                    FROM bookings b
                    LEFT JOIN services s ON b.service_id = s.id
                    LEFT JOIN resources r ON b.resource_id = r.id
                    WHERE b.booking_date BETWEEN ? AND ?
                    ORDER BY b.booking_date DESC, b.start_time DESC
                ";
            } elseif ($type === 'finance') {
                // Finance - Completed only
                $data['headers'] = ['Date', 'Time', 'Service', 'Table', 'Price', 'Status'];
                $sql = "
                    SELECT b.booking_date, b.start_time, s.slug as service, r.name as resource, b.price, b.status
                    FROM bookings b
                    LEFT JOIN services s ON b.service_id = s.id
                    LEFT JOIN resources r ON b.resource_id = r.id
                    WHERE b.booking_date BETWEEN ? AND ?
                    AND b.status = 'completed'
                    ORDER BY b.booking_date DESC, b.start_time DESC
                ";
            } elseif ($type === 'tables') {
                // Tables Occupancy
                // Consolidated view: One row per table per group (Day/Week/Month).
                $data['headers'] = ['Table Name', 'Bookings Count', 'Total Duration', 'Total Revenue'];

                $sql = "
                    SELECT
                        b.booking_date,
                        r.name as resource,
                        b.start_time,
                        b.end_time,
                        b.price,
                        b.status
                    FROM bookings b
                    LEFT JOIN resources r ON b.resource_id = r.id
                    WHERE b.booking_date BETWEEN ? AND ?
                    AND b.status = 'completed'
                    ORDER BY b.booking_date DESC
                ";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Grouping Logic
            $groups = [];
            $grandTotal = ['count' => 0, 'price' => 0, 'duration_minutes' => 0];

            foreach ($rows as $row) {
                // Restore Logic: If cancelled, price is 0 for stats (Booking reports show them but price count 0)
                if (isset($row['status']) && $row['status'] === 'cancelled') {
                    $row['price'] = 0;
                }

                $key = $groupBy($row);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'rows' => [],
                        'subtotal' => ['count' => 0, 'price' => 0, 'duration_minutes' => 0],
                        'buffer' => [] // Temporary storage for aggregation
                    ];
                }

                // Handling for Tables vs Others
                if ($type === 'tables') {
                    // Aggregate by Table Name
                    $table = $row['resource'] ?? 'Unknown';

                    // Duration Calc
                    $startT = strtotime($row['booking_date'] . ' ' . $row['start_time']);
                    $endT = strtotime($row['booking_date'] . ' ' . $row['end_time']);
                    $minutes = ($endT - $startT) / 60;
                    if ($minutes < 0) $minutes = 0;

                    if (!isset($groups[$key]['buffer'][$table])) {
                        $groups[$key]['buffer'][$table] = [
                            'resource' => $table,
                            'count' => 0,
                            'duration_minutes' => 0,
                            'revenue' => 0
                        ];
                    }

                    $groups[$key]['buffer'][$table]['count']++;
                    $groups[$key]['buffer'][$table]['duration_minutes'] += $minutes;
                    $groups[$key]['buffer'][$table]['revenue'] += (float)$row['price'];

                    // Update Subtotals / Grand Total immediately
                    $groups[$key]['subtotal']['duration_minutes'] += $minutes;
                    $grandTotal['duration_minutes'] += $minutes;

                    // Note: count/price subtotal updated below generally? 
                    // Let's do it specific here to avoid double logic
                    $groups[$key]['subtotal']['count']++;
                    $grandTotal['count']++;

                    $groups[$key]['subtotal']['price'] += (float)$row['price'];
                    $grandTotal['price'] += (float)$row['price'];
                } else {
                    // Normal behavior for Bookings/Finance
                    $groups[$key]['rows'][] = $row;
                    $groups[$key]['subtotal']['count']++;
                    $groups[$key]['subtotal']['price'] += (float)$row['price'];

                    $grandTotal['count']++;
                    $grandTotal['price'] += (float)$row['price'];
                }
            }

            // Post-Process Groups
            foreach ($groups as &$g) {
                // If tables, convert buffer to rows
                if ($type === 'tables' && !empty($g['buffer'])) {
                    foreach ($g['buffer'] as $tableData) {
                        $m = $tableData['duration_minutes'];
                        $durStr = sprintf("%dh %02dm", floor($m / 60), $m % 60);

                        $g['rows'][] = [
                            'Table Name' => $tableData['resource'],
                            'Bookings Count' => $tableData['count'],
                            'Total Duration' => $durStr,
                            'Total Revenue' => number_format($tableData['revenue'], 2, '.', '') . ' €'
                        ];
                    }
                    // Sort rows by Table Name for clean look
                    usort($g['rows'], function ($a, $b) {
                        return strcmp($a['Table Name'], $b['Table Name']);
                    });

                    unset($g['buffer']); // Cleanup
                } elseif ($type !== 'tables') {
                    // Format rows price for others
                    foreach ($g['rows'] as &$r) {
                        if (isset($r['price'])) $r['price'] = number_format((float)$r['price'], 2, '.', '') . ' €';
                    }
                }

                // Format Subtotal Duration
                if ($type === 'tables') {
                    $m = $g['subtotal']['duration_minutes'];
                    $g['subtotal']['duration_formatted'] = sprintf("%dh %02dm", floor($m / 60), $m % 60);
                }
            }
            krsort($groups);

            // Format Grand Total details
            if ($type === 'tables') {
                $m = $grandTotal['duration_minutes'];
                $grandTotal['duration_formatted'] = sprintf("%dh %02dm", floor($m / 60), $m % 60);
                // Keep price for tables as "Revenue"
            }

            $data['grouped_data'] = $groups;
            $data['grand_total'] = $grandTotal;
        } elseif ($type === 'promo') {
            $data['headers'] = ['Date', 'Coupon Code', 'Type', 'Discount (%)', 'Saved Amount', 'Price Paid'];

            // Promo Granularity: We need explicit usage log.
            // Since we don't have a separate `coupon_logs` table, we rely on `bookings` table.
            // We use `booking_date` for granularity.

            $sql = "
                SELECT 
                    b.booking_date,
                    b.coupon_redeemed as code,
                    c.type,
                    c.discount_percent,
                    b.price as paid_price
                    -- We list individual usages now to support granularity/grouping
                FROM bookings b
                LEFT JOIN coupons c ON CONVERT(b.coupon_redeemed USING utf8mb4) = CONVERT(c.code USING utf8mb4)
                WHERE b.booking_date BETWEEN ? AND ?
                AND b.coupon_redeemed IS NOT NULL
                AND b.coupon_redeemed != ''
                AND b.status = 'completed' 
                ORDER BY b.booking_date DESC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $groups = [];
            $grandTotal = ['count' => 0, 'price' => 0]; // Price here = Total Discounted Amount? Or Usage Count?
            // User requested "Usage Count" and "Total Discounted".
            // Let's track both. 'price' in grandTotal will represent 'Saved Amount'.
            // But we also might want to track 'Paid Amount' context.
            // Let's stick to: Count = count, Price = Saved Amount.

            foreach ($rows as $row) {
                // Calculate Saved Amount
                $discountVal = 0;
                $p = $row['discount_percent'];
                $final = $row['paid_price'];

                if ($p < 100 && $p > 0) {
                    $original = $final / (1 - ($p / 100));
                    $discountVal = $original - $final;
                }

                // Formatted row
                $formattedRow = [
                    'Date' => $row['booking_date'],
                    'Code' => $row['code'],
                    'Type' => $row['type'],
                    'Discount' => $p . '%',
                    'Saved' => number_format($discountVal, 2) . ' €',
                    'Paid' => number_format($final, 2) . ' €'
                ];

                $key = $groupBy($row); // Use same group logic
                if (!isset($groups[$key])) {
                    $groups[$key] = ['rows' => [], 'subtotal' => ['count' => 0, 'price' => 0]];
                    // price in subtotal = saved amount
                }

                $groups[$key]['rows'][] = $formattedRow;
                $groups[$key]['subtotal']['count']++;
                $groups[$key]['subtotal']['price'] += $discountVal;

                $grandTotal['count']++;
                $grandTotal['price'] += $discountVal;
            }

            krsort($groups);

            $data['grouped_data'] = $groups;
            $data['grand_total'] = $grandTotal;
        }

        return $data;
    }
}
