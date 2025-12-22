<?php

namespace App\Controllers;

use App\Models\Booking;
use App\Models\Service;
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
        session_start();
        if (empty($_SESSION['admin_logged_in'])) {
            header('Location: /admin/login');
            exit;
        }
    }
    
    /**
     * Admin dashboard
     */
    public function dashboard(): void
    {
        $pendingBookings = $this->bookingModel->getPending();
        $upcomingBookings = $this->bookingModel->getUpcoming(20);
        
        $this->render('admin/dashboard.twig', [
            'pending_bookings' => $pendingBookings,
            'upcoming_bookings' => $upcomingBookings,
            'page_title' => 'Admin Dashboard'
        ]);
    }
    
    /**
     * List all bookings
     */
    public function listBookings(): void
    {
        $status = $_GET['status'] ?? '';
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d', strtotime('+30 days'));
        
        $bookings = $this->bookingModel->getByDateRange($dateFrom, $dateTo);
        
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
            $success = $this->bookingService->updateStatus($bookingId, $status);
            
            if ($success) {
                // Send notification
                $booking = $this->bookingModel->find($bookingId);
                $this->notificationService->sendTelegramNotification($booking, $status);
                
                $this->json(['success' => true]);
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
}
