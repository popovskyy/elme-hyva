<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Opening-hours helpers shared by list cards and the location page.
 */
class Hours implements ArgumentInterface
{
    public const DAY_KEYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public const DAY_LABELS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array<string, array{from: string, to: string}>
     */
    public function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $hours = [];
        foreach (self::DAY_KEYS as $day) {
            $row = $data[$day] ?? null;
            $from = is_array($row) ? trim((string) ($row['from'] ?? '')) : '';
            $to = is_array($row) ? trim((string) ($row['to'] ?? '')) : '';
            $hours[$day] = ['from' => $from, 'to' => $to];
        }

        return $hours;
    }

    /**
     * Mon–Sun week for the location page, with today highlighted.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     short: string,
     *     is_today: bool,
     *     is_closed: bool,
     *     label_hours: string,
     *     from: string,
     *     to: string
     * }>
     */
    public function weekRows(?string $json): array
    {
        $hours = $this->decode($json);
        $todayKey = strtolower($this->timezone->date()->format('l'));
        $rows = [];

        foreach (self::DAY_KEYS as $day) {
            $from = $hours[$day]['from'] ?? '';
            $to = $hours[$day]['to'] ?? '';
            $closed = $from === '' || $to === '';
            $rows[] = [
                'key' => $day,
                'label' => self::DAY_LABELS[$day],
                'short' => substr(self::DAY_LABELS[$day], 0, 3),
                'is_today' => $day === $todayKey,
                'is_closed' => $closed,
                'label_hours' => $closed ? (string) __('Closed') : $this->clock($from) . ' – ' . $this->clock($to),
                'from' => $from,
                'to' => $to,
            ];
        }

        return $rows;
    }

    /**
     * Today's hours label for list cards ("9am – 5pm" or "Closed").
     */
    public function todayLabel(?string $json): string
    {
        $hours = $this->decode($json);
        $todayKey = strtolower($this->timezone->date()->format('l'));
        $from = $hours[$todayKey]['from'] ?? '';
        $to = $hours[$todayKey]['to'] ?? '';
        if ($from === '' || $to === '') {
            return (string) __('Closed');
        }

        return $this->clock($from) . ' – ' . $this->clock($to);
    }

    /**
     * Schema.org OpeningHoursSpecification rows (closed days omitted; days sharing times grouped).
     *
     * @return list<array{days: list<string>, opens: string, closes: string}>
     */
    public function openingHoursSpecification(?string $json): array
    {
        $grouped = [];
        foreach ($this->decode($json) as $day => $time) {
            if ($time['from'] === '' || $time['to'] === '') {
                continue;
            }
            $key = $time['from'] . '|' . $time['to'];
            $grouped[$key]['opens'] = $time['from'];
            $grouped[$key]['closes'] = $time['to'];
            $grouped[$key]['days'][] = self::DAY_LABELS[$day];
        }

        return array_values($grouped);
    }

    public function clock(string $time): string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $match)) {
            return $time;
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];
        $suffix = $hour >= 12 && $hour < 24 ? 'pm' : 'am';
        $hour12 = $hour % 12 ?: 12;

        return $hour12 . ($minute ? sprintf(':%02d', $minute) : '') . $suffix;
    }
}
