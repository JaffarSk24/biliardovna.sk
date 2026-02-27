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
            'page_title' => 'Manage Bookings',
            'current_time' => new \DateTime('now', new \DateTimeZone('Europe/Bratislava'))
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

            // Decode Extras
            if (!empty($booking['extras'])) {
                $booking['extras'] = json_decode($booking['extras'], true);
            } else {
                $booking['extras'] = [];
            }

            // Calculate Duration dynamically to ensure accuracy (handling midnight)
            try {
                $startObj = new \DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
                $endObj = new \DateTime($booking['booking_date'] . ' ' . $booking['end_time']);

                // Handle Midnight (if end <= start, assume next day)
                if ($endObj <= $startObj) {
                    $endObj->modify('+1 day');
                }

                $diff = $endObj->diff($startObj); // logic: end - start
                // Total hours (days*24 + h + i/60)
                $calcHours = ($diff->days * 24) + $diff->h + ($diff->i / 60);

                // Use calculated duration (fallback to 1 if weird)
                $booking['duration_hours'] = $calcHours > 0 ? $calcHours : 1;
            } catch (\Exception $e) {
                $booking['duration_hours'] = 1;
            }
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
            $bookingService = new \App\Services\BookingService();
            $redirectUrl = $_SERVER['HTTP_REFERER'] ?? '/admin/dashboard';

            // Remove existing query params to avoid stacking
            $redirectUrl = strtok($redirectUrl, '?');

            if ($status === 'confirmed' || $status === 'restore') {
                if ($status === 'restore') {
                    $db = \App\Database\Database::getInstance();
                    $db->prepare("UPDATE bookings SET status = 'pending' WHERE id = ?")->execute([$bookingId]);
                }

                // Use attemptConfirmation to check for conflicts
                $result = $bookingService->attemptConfirmation($bookingId, false); // false = manual

                if ($result['success']) {
                    $msg = $status === 'restore' ? 'Booking restored and confirmed' : 'Booking confirmed';
                    header('Location: ' . $redirectUrl . '?success=' . urlencode($msg));
                } else {
                    // Check if it was a conflict
                    if (!empty($result['conflict'])) {
                        $errorMsg = urlencode('CHYBA: Termín bol obsadený inou rezerváciou! Táto rezervácia bola automaticky zrušená.');
                        header('Location: ' . $redirectUrl . '?error=' . $errorMsg);
                    } else {
                        $errorMsg = urlencode('Error: ' . ($result['error'] ?? 'Unknown error'));
                        header('Location: ' . $redirectUrl . '?error=' . $errorMsg);
                    }
                }
            } else {
                // Just update status (cancelled, pending, etc.)
                $success = $bookingService->updateStatus($bookingId, $status);

                if ($success) {
                    header('Location: ' . $redirectUrl . '?success=Status updated');
                } else {
                    header('Location: ' . $redirectUrl . '?error=Failed to update status');
                }
            }
            exit;
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
            $discountValue = (float)$_POST['discount'];
            $discountType = $_POST['discount_type'] ?? 'percent'; // 'percent' or 'amount'

            $discountPercent = ($discountType === 'percent') ? $discountValue : null;
            $discountAmount = ($discountType === 'amount') ? $discountValue : null;

            $limitParam = $_POST['usage_limit'];
            $type = $_POST['type'] ?? 'standard';
            $validUntil = $_POST['valid_until'] ?? '';

            // Logic: 0 or empty -> NULL (Unlimited), N -> N
            $limit = empty($limitParam) ? null : (int)$limitParam;
            if ($limit === 0) $limit = null;

            // Date logic: empty -> 2099-12-31 (to avoid NULL schema error)
            $validDate = empty($validUntil) ? '2099-12-31' : $validUntil;

            if ($code && $discountValue > 0) {
                $db = \App\Database\Database::getInstance();
                // Use ON DUPLICATE KEY UPDATE to avoid crash and fix existing broken entries
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, discount_amount, usage_limit, type, valid_until) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), discount_amount = VALUES(discount_amount), usage_limit = VALUES(usage_limit), type = VALUES(type), valid_until = VALUES(valid_until)");
                $stmt->execute([$code, $discountPercent, $discountAmount, $limit, $type, $validDate]);
            }
        }
        header('Location: /admin/promo');
        exit;
    }

    public function generatePromo(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $amountVal = (int)$_POST['amount'];
            $discountValue = (float)$_POST['discount'];
            $discountType = $_POST['discount_type'] ?? 'percent'; // 'percent' or 'amount'

            $discountPercent = ($discountType === 'percent') ? $discountValue : null;
            $discountAmount = ($discountType === 'amount') ? $discountValue : null;

            $limitParam = $_POST['usage_limit'];
            $validUntil = $_POST['valid_until'] ?? '';
            $type = ($discountType === 'amount') ? 'gift_card' : 'standard';

            // Logic: 0 or empty -> NULL (Unlimited)
            $limit = empty($limitParam) ? null : (int)$limitParam;
            if ($limit === 0) $limit = null;

            // Date logic: empty -> 2099-12-31
            $validDate = empty($validUntil) ? '2099-12-31' : $validUntil;

            $prefix = strtoupper(trim($_POST['prefix'] ?? 'PROMO'));

            if ($amountVal > 0 && $discountValue > 0) {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare("INSERT INTO coupons (code, discount_percent, discount_amount, usage_limit, type, valid_until) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE discount_percent = VALUES(discount_percent), discount_amount = VALUES(discount_amount), usage_limit = VALUES(usage_limit), type = VALUES(type), valid_until = VALUES(valid_until)");

                for ($i = 0; $i < $amountVal; $i++) {
                    $random = substr(str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 6);
                    $code = $prefix . '-' . $random;
                    try {
                        $stmt->execute([$code, $discountPercent, $discountAmount, $limit, $type, $validDate]);
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

        // Paging Params
        $page = (int)($_GET['page'] ?? 1);
        if ($page < 1) $page = 1;
        $perPage = 50;

        $reportData = $this->getReportData($type, $start, $end, $granularity);

        // Handle Pagination for Total View (Slice the rows, keep totals)
        $pagination = null;
        if ($granularity === 'total' && !empty($reportData['grouped_data'])) {
            // There should be only one group for 'total' granularity
            $groupKey = array_key_first($reportData['grouped_data']);
            $allRows = $reportData['grouped_data'][$groupKey]['rows'] ?? [];
            $totalRows = count($allRows);

            if ($totalRows > 0) {
                $totalPages = ceil($totalRows / $perPage);

                // Slice rows for current page
                $offset = ($page - 1) * $perPage;
                $slicedRows = array_slice($allRows, $offset, $perPage);

                // Replace rows in display data
                $reportData['grouped_data'][$groupKey]['rows'] = $slicedRows;

                // Allow template to show "Showing X-Y of Z"
                $pagination = [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_rows' => $totalRows,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $totalRows),
                    'per_page' => $perPage
                ];
            }
        }

        $this->render('admin/report_preview.twig', [
            'report_type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'granularity' => $granularity,
            'headers' => $reportData['headers'],
            'grouped_data' => $reportData['grouped_data'] ?? [], // New structure
            'grand_total' => $reportData['grand_total'] ?? [],
            'report_title' => $reportData['report_title'] ?? 'Report Preview',
            'page_title' => 'Report Preview',
            'pagination' => $pagination
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
            if ($granularity === 'year') return date('Y', strtotime($date)); // 2024
            return 'All Data'; // Total
        };

        if ($type === 'bookings' || $type === 'finance' || $type === 'tables') {

            if ($type === 'bookings') {
                // Show Email instead of Name for Customer column
                $data['headers'] = ['Date', 'ID', 'Service', 'Table', 'Status', 'Customer (Email)', 'Price'];
                $sql = "
                    SELECT b.booking_date, b.id, s.slug as service, r.name as resource, b.status, b.customer_email as customer, b.price
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
                        b.status,
                        s.slug as service_name
                    FROM bookings b
                    LEFT JOIN resources r ON b.resource_id = r.id
                    LEFT JOIN services s ON b.service_id = s.id
                    WHERE b.booking_date BETWEEN ? AND ?
                    AND b.status = 'completed'
                    ORDER BY b.booking_date DESC
                ";
            }

            // Report Title Mapping
            $titles = [
                'bookings' => 'Booking Reports',
                'finance' => 'Financial Reports',
                'promo' => 'Promo Code Reports',
                'tables' => 'Tables Occupancy'
            ];
            $data['report_title'] = $titles[$type] ?? 'Report Preview';

            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Grouping Logic
            $groups = [];
            $grandTotal = ['count' => 0, 'price' => 0, 'duration_minutes' => 0, 'completed_count' => 0, 'average' => 0];

            foreach ($rows as $row) {
                // Formatting for Display (Cancelled = 0 visual, others = real price)
                if (isset($row['status']) && $row['status'] === 'cancelled') {
                    $row['price'] = 0;
                }

                // Stats Calculation (Sums ONLY from Completed)
                $priceForStats = 0;
                $isCompleted = (isset($row['status']) && $row['status'] === 'completed');

                if ($isCompleted) {
                    $priceForStats = (float)($row['price'] ?? 0);
                }

                $key = $groupBy($row);
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'rows' => [],
                        'subtotal' => [
                            'count' => 0,
                            'price' => 0,
                            'duration_minutes' => 0,
                            'completed_count' => 0
                        ],
                        'buffer' => []
                    ];
                }

                if ($type === 'tables') {
                    $table = $row['resource'] ?? 'Unknown';
                    $service = $row['service_name'] ?? 'Other';

                    // Duration Calc
                    $startT = strtotime($row['booking_date'] . ' ' . $row['start_time']);
                    $endT = strtotime($row['booking_date'] . ' ' . $row['end_time']);
                    $minutes = ($endT - $startT) / 60;
                    if ($minutes < 0) $minutes = 0;

                    if (!isset($groups[$key]['buffer'][$service])) {
                        $groups[$key]['buffer'][$service] = [];
                    }
                    if (!isset($groups[$key]['buffer'][$service][$table])) {
                        $groups[$key]['buffer'][$service][$table] = [
                            'resource' => $table,
                            'count' => 0,
                            'duration_minutes' => 0,
                            'revenue' => 0
                        ];
                    }

                    $groups[$key]['buffer'][$service][$table]['count']++;
                    $groups[$key]['buffer'][$service][$table]['duration_minutes'] += $minutes;
                    $groups[$key]['buffer'][$service][$table]['revenue'] += (float)$row['price'];

                    // Update Date-Group Subtotals
                    $groups[$key]['subtotal']['duration_minutes'] += $minutes;
                    $grandTotal['duration_minutes'] += $minutes;

                    $groups[$key]['subtotal']['count']++;
                    $grandTotal['count']++;

                    $groups[$key]['subtotal']['price'] += (float)$row['price'];
                    $grandTotal['price'] += (float)$row['price'];

                    $groups[$key]['subtotal']['completed_count']++;
                    $grandTotal['completed_count']++;
                } else {
                    // Bookings / Finance / Promo
                    $groups[$key]['rows'][] = $row;
                    $groups[$key]['subtotal']['count']++;
                    $groups[$key]['subtotal']['price'] += $priceForStats;
                    if ($isCompleted) {
                        $groups[$key]['subtotal']['completed_count']++;
                        $grandTotal['completed_count']++;
                    }

                    $grandTotal['count']++;
                    $grandTotal['price'] += $priceForStats;
                }
            }

            // Post-Process Groups
            foreach ($groups as $key => &$g) {
                // Formatting for Tables Nested Structure
                if ($type === 'tables' && !empty($g['buffer'])) {
                    foreach ($g['buffer'] as $serviceName => $tables) {
                        $serviceSubtotal = [
                            'count' => 0,
                            'duration_minutes' => 0,
                            'price' => 0,
                            'completed_count' => 0, // All are completed in tables report
                            'average' => 0
                        ];

                        $serviceRows = [];

                        foreach ($tables as $tableData) {
                            $m = $tableData['duration_minutes'];
                            $durStr = sprintf("%dh %02dm", floor($m / 60), $m % 60);

                            $serviceRows[] = [
                                'Table Name' => $tableData['resource'],
                                'Bookings Count' => $tableData['count'],
                                'Total Duration' => $durStr,
                                'Total Revenue' => number_format($tableData['revenue'], 2, '.', '') . ' €'
                            ];

                            // Add to Service Subtotal
                            $serviceSubtotal['count'] += $tableData['count'];
                            $serviceSubtotal['completed_count'] += $tableData['count'];
                            $serviceSubtotal['duration_minutes'] += $m;
                            $serviceSubtotal['price'] += $tableData['revenue'];
                        }

                        // Avg check for service
                        if ($serviceSubtotal['completed_count'] > 0) {
                            $serviceSubtotal['average'] = $serviceSubtotal['price'] / $serviceSubtotal['completed_count'];
                        }
                        // Duration format for service
                        $mSer = $serviceSubtotal['duration_minutes'];
                        $serviceSubtotal['duration_formatted'] = sprintf("%dh %02dm", floor($mSer / 60), $mSer % 60);

                        usort($serviceRows, function ($a, $b) {
                            return strcmp($a['Table Name'], $b['Table Name']);
                        });

                        $g['services'][$serviceName] = [
                            'rows' => $serviceRows,
                            'subtotal' => $serviceSubtotal
                        ];
                    }

                    // Sort services by name
                    ksort($g['services']);
                    unset($g['buffer']);
                } elseif ($type !== 'tables') {
                    // Format rows price for others
                    foreach ($g['rows'] as &$r) {
                        if (isset($r['price'])) $r['price'] = number_format((float)$r['price'], 2, '.', '') . ' €';
                    }
                }

                // Duration Format for Date Group
                if ($type === 'tables') {
                    $m = $g['subtotal']['duration_minutes'];
                    $g['subtotal']['duration_formatted'] = sprintf("%dh %02dm", floor($m / 60), $m % 60);
                }

                // Average Check for Date Group
                $g['subtotal']['average'] = 0;
                if ($g['subtotal']['completed_count'] > 0) {
                    $g['subtotal']['average'] = $g['subtotal']['price'] / $g['subtotal']['completed_count'];
                }
            }
            krsort($groups);

            // Grand Total Formatting
            if ($type === 'tables') {
                $m = $grandTotal['duration_minutes'];
                $grandTotal['duration_formatted'] = sprintf("%dh %02dm", floor($m / 60), $m % 60);
            }
            $grandTotal['average'] = 0;
            if ($grandTotal['completed_count'] > 0) {
                $grandTotal['average'] = $grandTotal['price'] / $grandTotal['completed_count'];
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

    /**
     * API: Get Cafe Menu Items
     */
    public function getCafeMenu(): void
    {
        try {
            $db = \App\Database\Database::getInstance();
            $items = $db->query("SELECT * FROM cafe_items WHERE is_active = 1 ORDER BY category, name")->fetchAll(\PDO::FETCH_ASSOC);

            // Group by category
            $grouped = [];
            foreach ($items as $item) {
                $grouped[$item['category']][] = $item;
            }

            $this->json(['success' => true, 'menu' => $grouped]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get Booking Extras
     */
    public function getBookingExtras(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            $this->json(['success' => false, 'error' => 'Invalid ID'], 400);
            return;
        }

        try {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("SELECT extras, price FROM bookings WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['success' => false, 'error' => 'Booking not found'], 404);
                return;
            }

            $extras = !empty($row['extras']) ? json_decode($row['extras'], true) : [];
            $this->json(['success' => true, 'extras' => $extras, 'total_price' => $row['price']]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get Upsell Suggestions
     */
    public function getUpsellSuggestions(): void
    {
        $bookingId = (int)($_GET['id'] ?? 0);
        if (!$bookingId) {
            $this->json(['success' => false, 'error' => 'Missing ID'], 400);
            return;
        }

        try {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("SELECT booking_date, start_time, duration_hours FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$booking) {
                $this->json(['success' => true, 'suggestions' => []]);
                return;
            }

            $suggestions = [];
            $hour = (int)substr($booking['start_time'], 0, 2);
            $dayOfWeek = date('N', strtotime($booking['booking_date'])); // 1-7

            // 1. Time-based Rules
            if ($hour < 14) {
                // Morning -> Coffee
                $items = $db->query("SELECT * FROM cafe_items WHERE category = 'coffee' AND is_active = 1 LIMIT 2")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($items as $item) {
                    $item['suggestion_reason'] = '☀️ Morning Boost';
                    $item['type'] = 'cafe';
                    $suggestions[] = $item;
                }
            } elseif ($hour >= 17) {
                // Evening -> Beer & Snacks
                $beer = $db->query("SELECT * FROM cafe_items WHERE category = 'beer' AND is_active = 1 LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC);
                $snacks = $db->query("SELECT * FROM cafe_items WHERE category = 'snacks' AND is_active = 1 LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC);

                foreach (array_merge($beer, $snacks) as $item) {
                    $item['suggestion_reason'] = $item['category'] === 'beer' ? '🍺 Evening Chill' : '🥜 Best with drinks';
                    $item['type'] = 'cafe';
                    $suggestions[] = $item;
                }
            }

            // 2. Duration Rules (> 2 hours)
            if ($booking['duration_hours'] >= 2 && $hour < 17) {
                // Suggest Food if long booking and not yet evening (where snacks are already suggested)
                $food = $db->query("SELECT * FROM cafe_items WHERE category IN ('snacks', 'food') AND is_active = 1 LIMIT 1")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($food as $item) {
                    $item['suggestion_reason'] = '🍔 Long Game Fuel';
                    $item['type'] = 'cafe';
                    $suggestions[] = $item;
                }
            }

            // 3. Additional Services (Database Configured)
            $services = $db->query("SELECT * FROM additional_services WHERE is_active = 1")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($services as $service) {
                $activeDays = !empty($service['active_days']) ? json_decode($service['active_days'], true) : null;
                if ($activeDays && !in_array($dayOfWeek, $activeDays)) continue;

                if (!empty($service['active_hours_start'])) {
                    if ($booking['start_time'] < $service['active_hours_start'] || $booking['start_time'] > $service['active_hours_end']) continue;
                }

                $service['suggestion_reason'] = '✨ Special Offer';
                $service['type'] = 'service';
                $suggestions[] = $service;
            }

            $this->json(['success' => true, 'suggestions' => $suggestions]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Update Booking Extras (Add/Remove items, Extend Time)
     */
    public function updateBookingExtras(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $bookingId = (int)($input['booking_id'] ?? 0);
        $action = $input['action'] ?? ''; // 'add_item', 'remove_item', 'update_qty', 'extend_time'

        if (!$bookingId || !$action) {
            $this->json(['success' => false, 'error' => 'Missing parameters'], 400);
            return;
        }

        try {
            $db = \App\Database\Database::getInstance();
            $booking = $this->bookingModel->find($bookingId);
            if (!$booking) {
                $this->json(['success' => false, 'error' => 'Booking not found'], 404);
                return;
            }

            $currentExtras = !empty($booking['extras']) ? json_decode($booking['extras'], true) : [];
            if (!is_array($currentExtras)) $currentExtras = [];

            $priceDelta = 0;
            $updatedExtras = $currentExtras;


            if ($action === 'check_availability') {
                $hours = (int)($input['hours'] ?? 0);
                if ($hours == 0) throw new \Exception("Invalid duration");

                $startTimeObj = new \DateTime($booking['booking_date'] . ' ' . $booking['start_time']);

                // Calculate new End Time (Handle Midnight)
                $endTime = new \DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
                if ($endTime <= $startTimeObj) {
                    $endTime->modify('+1 day');
                }

                $newEndTime = clone $endTime;
                $newEndTime->modify(($hours > 0 ? "+" : "") . "{$hours} hours");

                $resourceId = $booking['resource_id'];
                $checkStart = $endTime->format('H:i');
                $checkEnd = $newEndTime->format('H:i');

                // Validate: New End Time must be > Start Time
                // Check if new duration is at least 1 hour (as per user request "minimum 1 hour left")
                $durationCheck = clone $startTimeObj;
                $durationCheck->modify('+1 hour');

                if ($newEndTime < $durationCheck) {
                    $this->json(['success' => false, 'error' => 'Booking must be at least 1 hour']);
                    return;
                }

                // DB Check Overlap (Only if extending)
                if ($hours > 0) {
                    $stmt = $db->prepare("SELECT count(*) FROM bookings WHERE resource_id = ? AND booking_date = ? AND status = 'confirmed' AND (
                        (start_time < ? AND end_time > ?)
                    )");
                    $stmt->execute([$resourceId, $booking['booking_date'], $checkEnd, $checkStart]);
                    $overlap = $stmt->fetchColumn();

                    if ($overlap > 0) {
                        $this->json(['success' => false, 'error' => 'Time slot is not available']);
                    } else {
                        $this->json(['success' => true, 'available' => true]);
                    }
                } else {
                    // Reducing time -> Always available (checked min duration above)
                    $this->json(['success' => true, 'available' => true]);
                }
                return; // Early return for check

            } elseif ($action === 'extend_time') {
                $hours = (int)($input['hours'] ?? 0);
                if ($hours == 0) throw new \Exception("Invalid duration");

                $startTimeObj = new \DateTime($booking['booking_date'] . ' ' . $booking['start_time']);

                // Check again to be safe (Handle Midnight)
                $endTime = new \DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
                if ($endTime <= $startTimeObj) {
                    $endTime->modify('+1 day');
                }

                $newEndTime = clone $endTime;
                $newEndTime->modify(($hours > 0 ? "+" : "") . "{$hours} hours");

                $durationCheck = clone $startTimeObj;
                $durationCheck->modify('+1 hour');

                if ($newEndTime < $durationCheck) throw new \Exception("Booking must be at least 1 hour");

                $pricingService = new \App\Services\PricingService();
                $extPrice = 0;

                if ($hours > 0) {
                    $checkStart = $endTime->format('H:i');
                    // Calculate Price for Extension
                    $extPrice = $pricingService->calculatePrice($booking['service_id'], $booking['booking_date'], $checkStart, $hours);
                } else {
                    // Calculate Refund for Reduction
                    // Refund for the LAST segment (from NewEndTime to OldEndTime)
                    // Duration of refund is abs(hours)
                    $refundStart = $newEndTime->format('H:i');
                    $refundDuration = abs($hours);
                    $refundAmount = $pricingService->calculatePrice($booking['service_id'], $booking['booking_date'], $refundStart, $refundDuration);
                    $extPrice = -1 * $refundAmount;
                }

                // SECURITY FIX: Verify availability before extending!
                // Only if extending (hours > 0)
                if ($hours > 0) {
                    $stmt = $db->prepare("SELECT count(*) FROM bookings WHERE resource_id = ? AND booking_date = ? AND status = 'confirmed' AND (
                        (start_time < ? AND end_time > ?)
                    )");

                    // We need to check the NEW segment: OldEnd -> NewEnd
                    $checkStart = $endTime->format('H:i');
                    $checkEnd = $newEndTime->format('H:i');

                    $stmt->execute([$booking['resource_id'], $booking['booking_date'], $checkEnd, $checkStart]);
                    $overlap = $stmt->fetchColumn();

                    if ($overlap > 0) {
                        $this->json(['success' => false, 'error' => 'Conflict detected! The slot is already booked.']);
                        return;
                    }
                }

                $priceDelta = (float)$extPrice;

                // Update Booking Time in DB
                $newEndTimeStr = $newEndTime->format('H:i:s');

                // Recalculate duration from scratch (Start vs NewEnd) to match UI logic
                // Handle Midnight for calculation
                $calcStart = clone $startTimeObj;
                $calcEnd = clone $newEndTime;
                if ($calcEnd <= $calcStart) {
                    $calcEnd->modify('+1 day');
                }
                $diff = $calcEnd->diff($calcStart);
                $newTotalDuration = ($diff->days * 24) + $diff->h + ($diff->i / 60);
                if ($newTotalDuration < 1) $newTotalDuration = 1; // Safety fallback

                $stmt = $db->prepare("UPDATE bookings SET end_time = ?, duration_hours = ? WHERE id = ?");
                $stmt->execute([$newEndTimeStr, $newTotalDuration, $bookingId]);

                // Add to Extras Log
                $updatedExtras[] = [
                    'type' => 'extension',
                    'name' => ($hours > 0 ? "Extra Time (+{$hours}h)" : "Time Reduction ({$hours}h)"),
                    'qty' => 1,
                    'price' => $extPrice,
                    'hours' => $hours, // Persist hours for revert logic
                    'added_at' => date('Y-m-d H:i:s')
                ];
            } elseif ($action === 'add_item') {
                $item = $input['item'] ?? [];
                $qty = (int)($item['qty'] ?? 1);
                if (!$item || $qty < 1) throw new \Exception("Invalid item");

                // Check if already in extras (merge)
                $found = false;
                foreach ($updatedExtras as &$ex) {
                    if (($ex['type'] ?? '') === 'cafe' && ($ex['item_id'] ?? 0) == $item['id']) {
                        $ex['qty'] += $qty;
                        $priceDelta += $ex['price'] * $qty; // Item price is unit price
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $updatedExtras[] = [
                        'type' => 'cafe',
                        'item_id' => $item['id'],
                        'name' => $item['name'],
                        'price' => (float)$item['price'],
                        'qty' => $qty
                    ];
                    $priceDelta += (float)$item['price'] * $qty;
                }
            } elseif ($action === 'remove_item') {
                $index = $input['index'] ?? -1;
                if (isset($updatedExtras[$index])) {
                    $ex = $updatedExtras[$index];
                    $itemTotal = $ex['price'] * ($ex['qty'] ?? 1);
                    $priceDelta -= $itemTotal;

                    // REVERT TIME LOGIC
                    if (($ex['type'] ?? '') === 'extension') {
                        $hoursToRevert = (int)($ex['hours'] ?? 1); // Default to 1 if missing for old entries

                        $endTime = new \DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
                        $endTime->modify("-{$hoursToRevert} hours"); // Subtract hours

                        $newEndTimeStr = $endTime->format('H:i:s');

                        // Recalculate duration absolute
                        $startTimeObj = new \DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
                        $calcStart = clone $startTimeObj;
                        $calcEnd = clone $endTime;
                        if ($calcEnd <= $calcStart) {
                            $calcEnd->modify('+1 day');
                        }
                        $diff = $calcEnd->diff($calcStart);
                        $newDuration = ($diff->days * 24) + $diff->h + ($diff->i / 60);
                        if ($newDuration < 1) $newDuration = 1; // Safety check/Constraint

                        $stmt = $db->prepare("UPDATE bookings SET end_time = ?, duration_hours = ? WHERE id = ?");
                        $stmt->execute([$newEndTimeStr, $newDuration, $bookingId]);
                    }

                    array_splice($updatedExtras, $index, 1);
                }
            } elseif ($action === 'update_qty') {
                $index = $input['index'] ?? -1;
                $change = (int)($input['change'] ?? 0);
                if (isset($updatedExtras[$index])) {
                    $ex = &$updatedExtras[$index];
                    if (($ex['type'] ?? '') === 'cafe') {
                        $oldQty = $ex['qty'];
                        $newQty = $oldQty + $change;
                        if ($newQty < 1) $newQty = 1; // Min 1

                        $diff = $newQty - $oldQty;
                        $ex['qty'] = $newQty;
                        $priceDelta += $ex['price'] * $diff;
                    }
                }
            }

            // Update Booking Price & Extras
            $newPrice = (float)$booking['price'] + $priceDelta;
            $jsonExtras = json_encode($updatedExtras);

            $stmt = $db->prepare("UPDATE bookings SET price = ?, extras = ? WHERE id = ?");
            $stmt->execute([$newPrice, $jsonExtras, $bookingId]);

            $this->json(['success' => true, 'new_price' => number_format($newPrice, 2, '.', ''), 'extras' => $updatedExtras]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
