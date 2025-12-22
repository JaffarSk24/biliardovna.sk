<?php

namespace App\Services;

use App\Models\Pricing;
use App\Models\Holiday;
use DateTime;
use DateInterval;

class PricingService
{
    private Pricing $pricingModel;
    private Holiday $holidayModel;
    
    public function __construct()
    {
        $this->pricingModel = new Pricing();
        $this->holidayModel = new Holiday();
    }
    
    /**
     * Calculate price for a booking (sum each hour separately)
     */
    public function calculatePrice(int $serviceId, string $date, string $startTime, int $durationHours): float
    {
        $dateTime = new DateTime($date . ' ' . $startTime);
        $dayOfWeek = (int)$dateTime->format('N');
        $isHoliday = $this->holidayModel->isHoliday($date);
        
        $totalPrice = 0;
        
        // Calculate price for each hour separately
        for ($i = 0; $i < $durationHours; $i++) {
            $currentTime = clone $dateTime;
            $currentTime->add(new DateInterval('PT' . $i . 'H'));
            $timeString = $currentTime->format('H:i:s');
            
            $pricePerHour = $this->pricingModel->getPriceForSlot(
                $serviceId,
                $dayOfWeek,
                $timeString,
                $isHoliday
            );
            
            if ($pricePerHour === null) {
                throw new \Exception('Price not found for time slot: ' . $timeString);
            }
            
            $totalPrice += $pricePerHour;
        }
        
        return round($totalPrice, 2);
    }
    
    /**
     * Get available time slots for a service on a specific date
     */
    public function getAvailableSlots(int $serviceId, string $date): array
    {
        $config = require __DIR__ . '/../../config/app.php';
        $slotInterval = $config['booking']['slots_interval'];
        
        $slots = [];
        $dateTime = new DateTime($date);
        $dayOfWeek = (int)$dateTime->format('N');
        $isHoliday = $this->holidayModel->isHoliday($date);
        
        // Generate slots from 16:00 to 23:00
        $startHour = 16;
        $endHour = 24;
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            $time = sprintf('%02d:00:00', $hour);
            $price = $this->pricingModel->getPriceForSlot(
                $serviceId,
                $dayOfWeek,
                $time,
                $isHoliday
            );
            
            if ($price !== null) {
                $slots[] = [
                    'time' => $time,
                    'display_time' => sprintf('%02d:00', $hour),
                    'price_per_hour' => $price,
                    'available' => true
                ];
            }
        }
        
        return $slots;
    }
    
    /**
     * Get price breakdown for display (sum each hour)
     */
    public function getPriceBreakdown(int $serviceId, string $date, string $startTime, int $durationHours): array
    {
        $dateTime = new DateTime($date . ' ' . $startTime);
        $dayOfWeek = (int)$dateTime->format('N');
        $isHoliday = $this->holidayModel->isHoliday($date);
        
        $totalPrice = 0;
        $hourlyBreakdown = [];
        
        // Calculate price for each hour
        for ($i = 0; $i < $durationHours; $i++) {
            $currentTime = clone $dateTime;
            $currentTime->add(new DateInterval('PT' . $i . 'H'));
            $timeString = $currentTime->format('H:i:s');
            
            $pricePerHour = $this->pricingModel->getPriceForSlot(
                $serviceId,
                $dayOfWeek,
                $timeString,
                $isHoliday
            );
            
            if ($pricePerHour === null) {
                throw new \Exception('Price not found for time slot: ' . $timeString);
            }
            
            $totalPrice += $pricePerHour;
            $hourlyBreakdown[] = [
                'hour' => $currentTime->format('H:i'),
                'price' => $pricePerHour
            ];
        }
        
        return [
            'hourly_breakdown' => $hourlyBreakdown,
            'duration_hours' => $durationHours,
            'total_price' => round($totalPrice, 2),
            'is_holiday' => $isHoliday,
            'day_of_week' => $dayOfWeek
        ];
    }
}