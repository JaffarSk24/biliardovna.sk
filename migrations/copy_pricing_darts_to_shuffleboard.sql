-- Copy pricing rules from Darts (id=3) to Shuffleboard (id=5)
INSERT INTO pricing (
        service_id,
        day_of_week,
        start_time,
        end_time,
        price_per_hour,
        is_holiday_pricing,
        created_at,
        updated_at
    )
SELECT 5,
    day_of_week,
    start_time,
    end_time,
    price_per_hour,
    is_holiday_pricing,
    NOW(),
    NOW()
FROM pricing
WHERE service_id = 3;