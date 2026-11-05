<?php

namespace App\Services;

use DateTime;

/**
 * Часы работы клуба из config/app.php (booking.opening_hours).
 * Часы целые: 14 = 14:00, 24 = полночь.
 */
class OpeningHours
{
    private static ?array $week = null;

    /**
     * @return array<int, array{0: int, 1: int}> день недели (1 = пн) => [час открытия, час закрытия]
     */
    public static function week(): array
    {
        if (self::$week === null) {
            $config = require __DIR__ . '/../../config/app.php';
            self::$week = [];
            foreach ($config['booking']['opening_hours'] as $day => [$open, $close]) {
                self::$week[(int)$day] = [self::toHour($open), self::toHour($close)];
            }
        }

        return self::$week;
    }

    /**
     * Часы работы на дату. Праздник идёт по своему дню недели:
     * цены в праздник субботние, а часы как в этот день недели.
     *
     * @return array{0: int, 1: int}
     */
    public static function forDate(string $date): array
    {
        $day = (int)(new DateTime($date))->format('N');

        return self::week()[$day];
    }

    public static function isOpenAt(int $dayOfWeek, int $hour): bool
    {
        [$open, $close] = self::week()[$dayOfWeek];

        return $hour >= $open && $hour < $close;
    }

    /**
     * Подряд идущие дни с одинаковыми часами, для вывода на сайте.
     *
     * @return array<int, array{from: int, to: int, open: string, close: string}>
     */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::week() as $day => [$open, $close]) {
            $i = count($groups) - 1;
            if ($i >= 0 && $groups[$i]['to'] === $day - 1
                && $groups[$i]['open_hour'] === $open && $groups[$i]['close_hour'] === $close) {
                $groups[$i]['to'] = $day;
                continue;
            }
            $groups[] = ['from' => $day, 'to' => $day, 'open_hour' => $open, 'close_hour' => $close];
        }

        return array_map(fn(array $g) => [
            'from' => $g['from'],
            'to' => $g['to'],
            'open' => sprintf('%02d:00', $g['open_hour']),
            'close' => sprintf('%02d:00', $g['close_hour'] % 24),
        ], $groups);
    }

    /**
     * Самый ранний час открытия и самый поздний час закрытия за неделю.
     *
     * @return array{0: int, 1: int}
     */
    public static function range(): array
    {
        $week = self::week();

        return [min(array_column($week, 0)), max(array_column($week, 1))];
    }

    private static function toHour(string $time): int
    {
        return (int)explode(':', $time)[0];
    }
}
