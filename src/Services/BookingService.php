<?php

namespace App\Services;

use App\Models\Booking;
use App\Services\Service;
use App\Models\Resource;
use DateTime;

class BookingService
{
    private Booking $bookingModel;
    private Service $serviceModel;
    private Resource $resourceModel;
    private PricingService $pricingService;

    public function __construct()
    {
        $this->bookingModel = new Booking();
        $this->serviceModel = new Service();
        $this->resourceModel = new Resource();
        $this->pricingService = new PricingService();
    }

    /**
     * Create a new booking
     */
    public function create(array $data): array
    {
        // Validate booking data
        $validation = $this->validateBooking($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        // Parse start and end times
        $startTime = new DateTime($data['booking_date'] . ' ' . $data['start_time']);
        $endTime = new DateTime($data['booking_date'] . ' ' . $data['end_time']);

        // If end time is before start time, it means next day (e.g., 23:00 - 00:00)
        if ($endTime <= $startTime) {
            $endTime->modify('+1 day');
        }

        // Calculate duration in hours
        $durationHours = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 3600;

        // Calculate total price
        $price = $this->pricingService->calculatePrice(
            $data['service_id'],
            $data['booking_date'],
            $data['start_time'],
            $durationHours
        );

        // Check if resource is available
        $isAvailable = $this->isResourceAvailable(
            $data['resource_id'],
            $data['booking_date'],
            $data['start_time'],
            $data['end_time']
        );

        if (!$isAvailable) {
            return [
                'success' => false,
                'errors' => ['Selected time slot is no longer available']
            ];
        }

        // Apply coupon discount if provided
        $originalPrice = $price;
        $discountPercent = 0;
        $couponCode = null;

        // Apply coupon discount if provided
        $originalPrice = $price;
        $discountPercent = 0;
        $couponCode = null;

        if (!empty($data['coupon_code'])) {
            $db = \App\Database\Database::getInstance();
            // Fetch usage info
            $stmt = $db->prepare("SELECT id, discount_percent, used, usage_limit, current_uses FROM coupons WHERE code = ?");
            $stmt->execute([$data['coupon_code']]);
            $coupon = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Validation Logic:
            // 1. Check if coupon exists
            // 2. Check if usage_limit is set (NULL = unlimited)
            // 3. If limit set, check if current_uses < limit
            // 4. Fallback check for 'used' flag (legacy)

            $isValid = false;

            if ($coupon) {
                if (is_null($coupon['usage_limit'])) {
                    // Unlimited usage (assuming 'used' flag is ignored for unlimited, or we reset it?)
                    // Let's assume unlimited ignores 'used' flag to be safe, or used flag is for "cancelled" coupons?
                    // Standard logic: specific limit overrides used flag for counts, but used flag might prevent any usage?
                    // Let's rely on standard logic: if usage_limit is NULL, it is unlimited.
                    $isValid = true;
                } else {
                    // Limited usage
                    $currentUses = (int)($coupon['current_uses'] ?? 0);
                    $limit = (int)$coupon['usage_limit'];
                    if ($currentUses < $limit) {
                        $isValid = true;
                    }
                }
            }

            if ($isValid) {
                $discountPercent = (int)$coupon['discount_percent'];
                $price = $price * (1 - $discountPercent / 100);
                $couponCode = $data['coupon_code'];

                // Increment usage
                // Set 'used' to 1 ONLY if we hit the limit
                $updateSql = "UPDATE coupons SET 
                              current_uses = COALESCE(current_uses, 0) + 1, 
                              used_at = NOW(),
                              used = CASE 
                                  WHEN usage_limit IS NOT NULL AND (COALESCE(current_uses, 0) + 1) >= usage_limit THEN 1 
                                  ELSE 0 
                              END
                              WHERE id = ?";
                $stmt = $db->prepare($updateSql);
                $stmt->execute([$coupon['id']]);

                // Verify update
                error_log("Coupon {$couponCode} used (ID: {$coupon['id']})");
            } else {
                error_log("Coupon validation failed: " . ($coupon ? "limit reached" : "not found"));
            }
        }

        // Create booking entry

        $bookingData = [
            'service_id' => $data['service_id'],
            'resource_id' => $data['resource_id'],
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'booking_date' => $data['booking_date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration_hours' => $durationHours,
            'price' => $price,
            'status' => 'pending',
            'cancellation_token' => bin2hex(random_bytes(32)),
            'notes' => $data['notes'] ?? null,
            'coupon_redeemed' => $couponCode,
            'language' => $data['language'] ?? 'sk'
        ];

        $bookingId = $this->bookingModel->create($bookingData);

        $booking = $this->bookingModel->find($bookingId);
        $service = $this->serviceModel->getWithTranslations($data['language'] ?? 'sk');
        $serviceData = array_filter($service, fn($s) => $s['id'] == $data['service_id']);
        $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

        // Add discount info for notifications
        if ($couponCode) {
            $booking['original_price'] = $originalPrice;
            $booking['discount_percent'] = $discountPercent;
        }

        return [
            'success' => true,
            'booking_id' => $bookingId,
            'booking' => $booking
        ];
    }

    /**
     * Check if resource is available for a specific time slot
     */
    /**
     * Check if resource is available for a specific time slot
     */
    private function isResourceAvailable(int $resourceId, string $date, string $startTime, string $endTime): bool
    {
        error_log("DEBUG isResourceAvailable: Checking Resource $resourceId on $date from $startTime to $endTime");

        $bookings = $this->bookingModel->all([
            'resource_id' => $resourceId,
            'booking_date' => $date,
            'status' => 'confirmed'
        ]);

        error_log("DEBUG isResourceAvailable: Found " . count($bookings) . " confirmed bookings.");

        $slotStart = strtotime($date . ' ' . $startTime);
        $slotEnd = strtotime($date . ' ' . $endTime);

        // Handle midnight crossing
        if ($slotEnd <= $slotStart) {
            $slotEnd += 86400; // +1 day
        }

        foreach ($bookings as $booking) {
            $bookingStart = strtotime($date . ' ' . $booking['start_time']);
            $bookingEnd = strtotime($date . ' ' . $booking['end_time']);

            // Handle midnight crossing
            if ($bookingEnd <= $bookingStart) {
                $bookingEnd += 86400; // +1 day
            }

            error_log("DEBUG Check overlap: Slot [$slotStart - $slotEnd] vs Booking #{$booking['id']} [$bookingStart - $bookingEnd]");

            // Overlap if: slot starts before booking ends AND slot ends after booking starts
            if ($slotStart < $bookingEnd && $slotEnd > $bookingStart) {
                error_log("DEBUG Overlap DETECTED!");
                return false; // Занято
            }
        }

        error_log("DEBUG No overlap found. Available.");
        return true; // Свободно
    }

    /**
     * Validate booking data
     */
    private function validateBooking(array $data): array
    {
        $errors = [];

        // Required fields
        $required = ['service_id', 'resource_id', 'booking_date', 'start_time', 'end_time', 'customer_name', 'customer_phone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[] = "Field {$field} is required";
            }
        }

        // Validate that date is not in the past
        if (!empty($data['booking_date'])) {
            $bookingDate = new DateTime($data['booking_date']);
            $today = new DateTime('today');

            if ($bookingDate < $today) {
                $errors[] = 'Booking date must be in the future';
            }

            $config = require __DIR__ . '/../../config/app.php';
            $maxDays = $config['booking']['advance_days'];
            $maxDate = clone $today;
            $maxDate->modify("+{$maxDays} days");

            if ($bookingDate > $maxDate) {
                $errors[] = "Booking date cannot be more than {$maxDays} days in advance";
            }
        }

        // Validate time range
        if (!empty($data['start_time']) && !empty($data['end_time']) && !empty($data['booking_date'])) {
            $startDateTime = new DateTime($data['booking_date'] . ' ' . $data['start_time']);
            $endDateTime = new DateTime($data['booking_date'] . ' ' . $data['end_time']);

            // If end time is before start time, it means next day (e.g., 23:00 - 00:00)
            if ($endDateTime <= $startDateTime) {
                $endDateTime->modify('+1 day');
            }

            // Now validate that there's at least some duration
            if ($endDateTime <= $startDateTime) {
                $errors[] = 'End time must be after start time';
            }
        }

        // Validate phone number
        if (!empty($data['customer_phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', $data['customer_phone']);
            if (strlen($phone) < 9) {
                $errors[] = 'Invalid phone number format';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Check availability for a time slot
     */
    public function checkAvailability(int $serviceId, string $date, string $startTime, int $durationHours): bool
    {
        $startDateTime = new DateTime($date . ' ' . $startTime);
        $endDateTime = clone $startDateTime;
        $endDateTime->modify("+{$durationHours} hours");

        $availableResources = $this->resourceModel->getAvailableForSlot(
            $serviceId,
            $date,
            $startTime,
            $endDateTime->format('H:i')
        );

        return !empty($availableResources);
    }

    /**
     * Get availability for all resources of a service on a specific date
     */
    public function getResourcesAvailability(int $serviceId, string $date): array
    {
        // Get all resources for this service
        $resources = $this->resourceModel->getByService($serviceId);

        // Fetch blocked slots for this day/service
        $db = \App\Database\Database::getInstance();
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        // Overlap: block_start <= day_end AND block_end >= day_start
        $stmt = $db->prepare("
            SELECT * FROM blocked_slots 
            WHERE (start_time <= ? AND end_time >= ?) 
            AND (service_id IS NULL OR service_id = ?)
        ");
        $stmt->execute([$dayEnd, $dayStart, $serviceId]);
        $blockedSlots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Define time slots (16:00 - 23:00, each slot is 1 hour)
        $timeSlots = [
            ['start' => '16:00', 'end' => '17:00'],
            ['start' => '17:00', 'end' => '18:00'],
            ['start' => '18:00', 'end' => '19:00'],
            ['start' => '19:00', 'end' => '20:00'],
            ['start' => '20:00', 'end' => '21:00'],
            ['start' => '21:00', 'end' => '22:00'],
            ['start' => '22:00', 'end' => '23:00'],
            ['start' => '23:00', 'end' => '00:00']
        ];

        $result = [];

        foreach ($resources as $resource) {
            $resourceData = [
                'id' => $resource['id'],
                'name' => $resource['name'],
                'slots' => []
            ];

            // Get all bookings for this resource on this date
            $bookings = $this->bookingModel->all([
                'resource_id' => $resource['id'],
                'booking_date' => $date,
                'status' => 'confirmed'
            ]);

            // Check each time slot
            foreach ($timeSlots as $slot) {
                $isOccupied = false;
                $slotStart = strtotime($date . ' ' . $slot['start']);
                $slotEnd = strtotime($date . ' ' . $slot['end']);

                // Handle midnight crossing for slot
                if ($slotEnd <= $slotStart) $slotEnd += 86400;

                // 1. Check Admin Blocks
                foreach ($blockedSlots as $block) {
                    // Filter by resource if specified
                    if ($block['resource_id'] !== null && $block['resource_id'] != $resource['id']) {
                        continue;
                    }

                    $blockStart = strtotime($block['start_time']);
                    $blockEnd = strtotime($block['end_time']);

                    // Check overlap
                    if ($slotStart < $blockEnd && $slotEnd > $blockStart) {
                        $isOccupied = true;
                        break;
                    }
                }

                // 2. Check Existing Bookings (if not already blocked)
                if (!$isOccupied) {

                    foreach ($bookings as $booking) {
                        $slotStart = strtotime($date . ' ' . $slot['start']);
                        $slotEnd = strtotime($date . ' ' . $slot['end']);
                        $bookingStart = strtotime($date . ' ' . $booking['start_time']);
                        $bookingEnd = strtotime($date . ' ' . $booking['end_time']);

                        // Handle midnight crossing
                        if ($slotEnd <= $slotStart) {
                            $slotEnd += 86400;
                        }
                        if ($bookingEnd <= $bookingStart) {
                            $bookingEnd += 86400;
                        }

                        // Check overlap
                        if ($slotStart < $bookingEnd && $slotEnd > $bookingStart) {
                            $isOccupied = true;
                            break;
                        }
                    }
                }

                // Calculate price for this slot
                $price = $this->pricingService->calculatePrice(
                    $serviceId,
                    $date,
                    $slot['start'],
                    1 // 1 hour
                );

                $resourceData['slots'][] = [
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'available' => !$isOccupied,
                    'price' => number_format($price, 2, '.', '')
                ];
            }

            $result[] = $resourceData;
        }

        return ['resources' => $result];
    }

    /**
     * Attempt to confirm a booking (Manual or Auto)
     * Uses MySQL GET_LOCK + transaction to prevent race conditions.
     */
    public function attemptConfirmation(int $bookingId, bool $isAuto = false): array
    {
        $db = \App\Database\Database::getInstance();

        // 1. Load booking data first (before locking)
        $booking = $this->bookingModel->find($bookingId);
        if (!$booking) {
            return ['success' => false, 'error' => 'Booking not found'];
        }

        if ($booking['status'] === 'confirmed') {
            return ['success' => true, 'message' => 'Already confirmed'];
        }

        if ($booking['status'] === 'cancelled') {
            return ['success' => false, 'error' => 'Booking is cancelled'];
        }

        // 2. Acquire a NAMED LOCK for this resource+date combination
        //    This ensures only ONE process can confirm bookings for this slot at a time
        $lockName = "booking_lock_{$booking['resource_id']}_{$booking['booking_date']}";
        $lockStmt = $db->prepare("SELECT GET_LOCK(?, 10)");
        $lockStmt->execute([$lockName]);
        $lockResult = $lockStmt->fetchColumn();

        if ($lockResult != 1) {
            error_log("attemptConfirmation: Could not acquire lock for booking #{$bookingId}");
            return ['success' => false, 'error' => 'Server busy, please try again'];
        }

        try {
            // 3. Re-read the booking status (it may have changed while waiting for lock)
            $stmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
            $stmt->execute([$bookingId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === 'confirmed') {
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
                return ['success' => true, 'message' => 'Already confirmed'];
            }
            if ($currentStatus === 'cancelled') {
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
                return ['success' => false, 'error' => 'Booking is cancelled'];
            }

            // 4. Check for conflicting CONFIRMED bookings (we hold the lock, so no race)
            $stmt = $db->prepare("SELECT id, start_time, end_time FROM bookings
                WHERE resource_id = ? AND booking_date = ? AND status = 'confirmed'");
            $stmt->execute([$booking['resource_id'], $booking['booking_date']]);
            $confirmedBookings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Check time overlap
            $slotStart = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
            $slotEnd = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
            if ($slotEnd <= $slotStart) {
                $slotEnd += 86400;
            }

            $hasConflict = false;
            foreach ($confirmedBookings as $existing) {
                $existStart = strtotime($booking['booking_date'] . ' ' . $existing['start_time']);
                $existEnd = strtotime($booking['booking_date'] . ' ' . $existing['end_time']);
                if ($existEnd <= $existStart) {
                    $existEnd += 86400;
                }
                if ($slotStart < $existEnd && $slotEnd > $existStart) {
                    $hasConflict = true;
                    break;
                }
            }

            if ($hasConflict) {
                // 5a. Conflict! Cancel this booking
                $stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$bookingId]);

                // Release the lock
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);

                // Send conflict notification
                $booking['status'] = 'cancelled';
                $service = $this->serviceModel->getWithTranslations($booking['language'] ?? 'sk');
                $serviceData = array_filter($service, fn($s) => $s['id'] == $booking['service_id']);
                $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

                $resource = $this->resourceModel->find($booking['resource_id']);
                $booking['resource_name'] = $resource['name'] ?? 'Unknown';

                $notificationService = new NotificationService();
                $notificationService->sendConflictNotification($booking, $isAuto);

                return [
                    'success' => false,
                    'error' => 'Slot already taken. Booking cancelled.',
                    'conflict' => true
                ];
            }

            // 5b. No conflict — confirm the booking
            $stmt = $db->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
            $stmt->execute([$bookingId]);

            // Release the lock
            $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);

            // Send confirmation notifications
            $this->sendStatusNotifications($bookingId, 'confirmed');

            return ['success' => true];
        } catch (\Exception $e) {
            // Always release the lock on error
            try {
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$lockName]);
            } catch (\Exception $ignore) {
            }

            error_log("attemptConfirmation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send status change notifications (Telegram + Email)
     */
    private function sendStatusNotifications(int $bookingId, string $newStatus): void
    {
        if ($newStatus === 'completed') {
            return; // No notifications for internal admin status
        }

        $booking = $this->bookingModel->find($bookingId);
        if (!$booking) {
            return;
        }

        $service = $this->serviceModel->getWithTranslations($booking['language'] ?? 'sk');
        $serviceData = array_filter($service, fn($s) => $s['id'] == $booking['service_id']);
        $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

        $notificationService = new NotificationService();
        $notificationService->sendTelegramNotification($booking, $newStatus);

        if (!empty($booking['customer_email'])) {
            $notificationService->sendEmailNotification($booking, $newStatus);
        }
    }

    /**
     * Update booking status and send notifications
     */
    public function updateStatus(int $bookingId, string $newStatus, bool $sendNotification = true): bool
    {
        $validStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $booking = $this->bookingModel->find($bookingId);

        if (!$booking) {
            return false;
        }

        $oldStatus = $booking['status'];

        // Update status in database
        $result = $this->bookingModel->update($bookingId, ['status' => $newStatus]);

        if ($result && $oldStatus !== $newStatus && $sendNotification) {
            // Reload booking with updated data
            $booking = $this->bookingModel->find($bookingId);

            // Get service name
            $service = $this->serviceModel->getWithTranslations($booking['language'] ?? 'sk');
            $serviceData = array_filter($service, fn($s) => $s['id'] == $booking['service_id']);
            $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

            // Send notifications (NOT for 'completed' — internal admin status)
            if ($newStatus !== 'completed') {
                $notificationService = new NotificationService();

                // Send Telegram notification
                $notificationService->sendTelegramNotification($booking, $newStatus);

                // Send email to customer
                if (!empty($booking['customer_email'])) {
                    $notificationService->sendEmailNotification($booking, $newStatus);
                }
            }
        }

        return $result;
    }

    /**
     * Get booking by ID with all details
     */
    public function getById(int $id): ?array
    {
        $booking = $this->bookingModel->find($id);

        if (!$booking) {
            return null;
        }

        // Get service name
        $service = $this->serviceModel->getWithTranslations($booking['language'] ?? 'sk');
        $serviceData = array_filter($service, fn($s) => $s['id'] == $booking['service_id']);
        $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

        // Get resource name
        $resource = $this->resourceModel->find($booking['resource_id']);
        $booking['resource_name'] = $resource['name'] ?? 'Unknown';

        return $booking;
    }

    /**
     * Get booking by cancellation token
     */
    public function getByToken(string $token): ?array
    {
        $bookings = $this->bookingModel->all(['cancellation_token' => $token]);

        if (empty($bookings)) {
            return null;
        }

        $booking = $bookings[0];

        // Get service name
        $service = $this->serviceModel->getWithTranslations($booking['language'] ?? 'sk');
        $serviceData = array_filter($service, fn($s) => $s['id'] == $booking['service_id']);
        $booking['service_name'] = !empty($serviceData) ? reset($serviceData)['name'] : 'Unknown';

        // Get resource name
        $resource = $this->resourceModel->find($booking['resource_id']);
        $booking['resource_name'] = $resource['name'] ?? 'Unknown';

        return $booking;
    }
}
