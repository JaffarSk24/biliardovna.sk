<?php

namespace App\Controllers;

use App\Models\Booking;

class ReviewController extends Controller
{
    private Booking $bookingModel;
    
    public function __construct(string $language = 'sk')
    {
        parent::__construct($language);
        $this->bookingModel = new Booking();
    }
    
    /**
     * Show review page (marks click) and redirects user to Google Reviews link in the view.
     * Coupon issuance is handled exclusively by cron/send_review_coupons.php
     */
    public function show(): void
    {
        $bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
        $token     = $_GET['token'] ?? '';

        if ($bookingId <= 0 || $token === '') {
            $this->redirect('/');
            return;
        }

        // Get booking and ensure it is completed
        $booking = $this->bookingModel->findById($bookingId);
        if (!$booking || ($booking['status'] ?? null) !== 'completed') {
            $this->redirect('/');
            return;
        }

        // Verify review token stored with the booking
        if (empty($booking['review_token']) || !hash_equals($booking['review_token'], $token)) {
            $this->redirect('/');
            return;
        }

        $email = trim(strtolower($booking['customer_email'] ?? ''));
        if ($email === '') {
            $this->redirect('/');
            return;
        }

        // Mark first valid click (cron will see review_clicked_at and send coupon)
        if (empty($booking['review_clicked_at'])) {
            $this->bookingModel->update($bookingId, [
                'review_clicked_at' => date('Y-m-d H:i:s')
            ]);
        }

        // No coupon generation here — handled by cron
        $googleReviewUrl = 'https://g.page/r/YOUR_GOOGLE_PLACE_ID/review';

        $this->render('review.twig', [
            'google_review_url'  => $googleReviewUrl,
            'coupon_code'        => null,   // optional for template compatibility
            'expiry_date'        => null,   // optional for template compatibility
            'already_generated'  => false,  // optional for template compatibility
        ]);
    }
}