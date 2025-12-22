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
     * Track review click and redirect to Google
     */
    public function show(): void
    {
        $bookingId = $_GET['booking'] ?? null;
        $token = $_GET['token'] ?? null;
        
        if (!$bookingId || !$token) {
            $this->redirect('/');
            return;
        }
        
        // Get booking
        $booking = $this->bookingModel->find($bookingId);
        
        if (!$booking || $booking['status'] !== 'completed') {
            $this->redirect('/');
            return;
        }
        
        // Mark review link as clicked
        if (empty($booking['review_clicked_at'])) {
            $this->bookingModel->update($bookingId, [
                'review_clicked_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Google review URL
        $googleReviewUrl = 'https://g.page/r/CQQSBE5PHKpyEBM/review';
        
        $this->render('review.twig', [
            'google_review_url' => $googleReviewUrl
        ]);
    }
}