<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Opening-hours helpers shared by list cards and the location page.
 *
 * opening_hours: {"monday": {"from": "09:00", "to": "17:00"}, ...}; empty from/to = closed.
 * holiday_hours: [{"date": "2026-12-25", "from": "", "to": "", "name": "Christmas Day", "label": "..."}];
 * a holiday row replaces the weekday hours for that exact date.
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

    public const DAY_SHORT = [
        'monday' => 'Mon',
        'tuesday' => 'Tue',
        'wednesday' => 'Wed',
        'thursday' => 'Thu',
        'friday' => 'Fri',
        'saturday' => 'Sat',
        'sunday' => 'Sun',
    ];

    public const SCHEDULE_WEEKS = 5;

    private const DASH = "\u{2013}";

    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Store timezone, so the browser can work out "today" on a page served from full-page cache.
     */
    public function getTimezoneCode(): string
    {
        return (string) $this->timezone->getConfigTimezone();
    }

    /**
     * @return array<string, array{from: string, to: string}>
     */
    public function decode(?string $json): array
    {
        $data = $this->decodeJson($json);
        if ($data === null) {
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
     * Holiday rows keyed by ISO date, sorted by date. Rows without a valid date are skipped.
     *
     * @return array<string, array{from: string, to: string, name: string, label: string}>
     */
    public function decodeHolidays(?string $json): array
    {
        $data = $this->decodeJson($json);
        if ($data === null) {
            return [];
        }

        $holidays = [];
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }
            $date = trim((string) ($row['date'] ?? ''));
            if (!$this->isIsoDate($date)) {
                continue;
            }
            $holidays[$date] = [
                'from' => trim((string) ($row['from'] ?? '')),
                'to' => trim((string) ($row['to'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'label' => trim((string) ($row['label'] ?? '')),
            ];
        }

        ksort($holidays);

        return $holidays;
    }

    /**
     * Error message for the admin form, or null when the value is empty or valid.
     */
    public function validateOpeningHours(?string $json): ?string
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $data = $this->decodeJson($json);
        if ($data === null) {
            return (string) __('Opening hours must be valid JSON.');
        }
        foreach ($data as $day => $row) {
            if (!in_array($day, self::DAY_KEYS, true) || !is_array($row)) {
                return (string) __('Opening hours: unknown day "%1". Use monday to sunday.', (string) $day);
            }
            foreach (['from', 'to'] as $field) {
                $time = trim((string) ($row[$field] ?? ''));
                if ($time !== '' && !$this->isTime($time)) {
                    return (string) __('Opening hours: %1 %2 must be HH:MM.', (string) $day, $field);
                }
            }
        }

        return null;
    }

    /**
     * Error message for the admin form, or null when the value is empty or valid.
     */
    public function validateHolidayHours(?string $json): ?string
    {
        if ($json === null || trim($json) === '') {
            return null;
        }
        $data = $this->decodeJson($json);
        if ($data === null || !array_is_list($data)) {
            return (string) __('Holiday hours must be a JSON list of rows.');
        }
        foreach ($data as $index => $row) {
            $date = is_array($row) ? trim((string) ($row['date'] ?? '')) : '';
            if (!$this->isIsoDate($date)) {
                return (string) __('Holiday hours row %1: date must be YYYY-MM-DD.', $index + 1);
            }
            foreach (['from', 'to'] as $field) {
                $time = trim((string) ($row[$field] ?? ''));
                if ($time !== '' && !$this->isTime($time)) {
                    return (string) __('Holiday hours %1: %2 must be HH:MM.', $date, $field);
                }
            }
        }

        return null;
    }

    /**
     * Five weeks of dated hours (weekday default + holiday overrides), starting this Monday.
     * Empty when the location has neither weekday hours nor a holiday row in the window.
     *
     * @return list<array{
     *     label: string,
     *     days: list<array{
     *         day: string,
     *         date: string,
     *         iso: string,
     *         hours: string,
     *         is_closed: bool,
     *         is_today: bool,
     *         holiday: array{name: string, label: string}|null
     *     }>
     * }>
     */
    public function scheduleWeeks(?string $openingHoursJson, ?string $holidayHoursJson): array
    {
        $weekday = $this->decode($openingHoursJson);
        $holidays = $this->decodeHolidays($holidayHoursJson);

        $today = $this->today();
        $todayIso = $today->format('Y-m-d');
        $monday = $today->modify('monday this week');
        $lastDay = $monday->modify(sprintf('+%d days', self::SCHEDULE_WEEKS * 7 - 1));

        $hasWeekdayHours = (bool) array_filter(
            $weekday,
            static fn (array $day): bool => $day['from'] !== '' && $day['to'] !== ''
        );
        $hasHolidayInWindow = (bool) array_filter(
            array_keys($holidays),
            static fn (string $date): bool => $date >= $monday->format('Y-m-d') && $date <= $lastDay->format('Y-m-d')
        );
        if (!$hasWeekdayHours && !$hasHolidayInWindow) {
            return [];
        }

        $weeks = [];
        for ($week = 0; $week < self::SCHEDULE_WEEKS; $week++) {
            $start = $monday->modify(sprintf('+%d days', $week * 7));
            $days = [];
            for ($offset = 0; $offset < 7; $offset++) {
                $days[] = $this->buildScheduleDay(
                    $start->modify(sprintf('+%d days', $offset)),
                    $weekday,
                    $holidays,
                    $todayIso
                );
            }
            $weeks[] = [
                'label' => $this->formatRange($start, $start->modify('+6 days')),
                'days' => $days,
            ];
        }

        return $weeks;
    }

    /**
     * Today's hours label for list cards, honouring a holiday row for today.
     */
    public function todayLabel(?string $openingHoursJson, ?string $holidayHoursJson = null): string
    {
        $today = $this->today();
        $holidays = $this->decodeHolidays($holidayHoursJson);
        $holiday = $holidays[$today->format('Y-m-d')] ?? null;
        if ($holiday !== null) {
            return $this->rangeLabel($holiday['from'], $holiday['to']);
        }

        $hours = $this->decode($openingHoursJson);
        $todayKey = strtolower($today->format('l'));

        return $this->rangeLabel($hours[$todayKey]['from'] ?? '', $hours[$todayKey]['to'] ?? '');
    }

    /**
     * Hours label per weekday key, for recomputing "Today" in the browser.
     *
     * @return array<string, string>
     */
    public function dayLabels(?string $openingHoursJson): array
    {
        $hours = $this->decode($openingHoursJson);
        $labels = [];
        foreach (self::DAY_KEYS as $day) {
            $labels[$day] = $this->rangeLabel($hours[$day]['from'] ?? '', $hours[$day]['to'] ?? '');
        }

        return $labels;
    }

    /**
     * Hours label per holiday date from this week on, for recomputing "Today" in the browser.
     *
     * @return array<string, string>
     */
    public function holidayLabels(?string $holidayHoursJson): array
    {
        $from = $this->today()->modify('monday this week')->format('Y-m-d');
        $labels = [];
        foreach ($this->decodeHolidays($holidayHoursJson) as $date => $holiday) {
            if ($date >= $from) {
                $labels[$date] = $this->rangeLabel($holiday['from'], $holiday['to']);
            }
        }

        return $labels;
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

    /**
     * Schema.org specialOpeningHoursSpecification from holiday rows (closed = 00:00 to 00:00).
     *
     * @return list<array{date: string, opens: string, closes: string, name: string}>
     */
    public function specialOpeningHoursSpecification(?string $holidayHoursJson): array
    {
        $special = [];
        foreach ($this->decodeHolidays($holidayHoursJson) as $date => $holiday) {
            $isClosed = $holiday['from'] === '' || $holiday['to'] === '';
            $special[] = [
                'date' => $date,
                'opens' => $isClosed ? '00:00' : $holiday['from'],
                'closes' => $isClosed ? '00:00' : $holiday['to'],
                'name' => $holiday['name'],
            ];
        }

        return $special;
    }

    public function rangeLabel(string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return (string) __('Closed');
        }

        return $this->clock($from) . ' ' . self::DASH . ' ' . $this->clock($to);
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

    /**
     * @return array<mixed>|null
     */
    private function decodeJson(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    private function today(): \DateTimeImmutable
    {
        $now = $this->timezone->date();

        return \DateTimeImmutable::createFromInterface($now)->setTime(0, 0);
    }

    private function isIsoDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year);
    }

    private function isTime(string $time): bool
    {
        return (bool) preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $time);
    }

    /**
     * @param array<string, array{from: string, to: string}> $weekday
     * @param array<string, array{from: string, to: string, name: string, label: string}> $holidays
     * @return array{
     *     day: string,
     *     date: string,
     *     iso: string,
     *     hours: string,
     *     is_closed: bool,
     *     is_today: bool,
     *     holiday: array{name: string, label: string}|null
     * }
     */
    private function buildScheduleDay(
        \DateTimeImmutable $date,
        array $weekday,
        array $holidays,
        string $todayIso
    ): array {
        $dayCode = strtolower($date->format('l'));
        $iso = $date->format('Y-m-d');
        $holiday = $holidays[$iso] ?? null;

        $times = $holiday !== null
            ? ['from' => $holiday['from'], 'to' => $holiday['to']]
            : ($weekday[$dayCode] ?? ['from' => '', 'to' => '']);

        return [
            'day' => self::DAY_SHORT[$dayCode],
            'date' => $date->format('j M'),
            'iso' => $iso,
            'hours' => $this->rangeLabel($times['from'], $times['to']),
            'is_closed' => $times['from'] === '' || $times['to'] === '',
            'is_today' => $iso === $todayIso,
            'holiday' => $holiday === null ? null : [
                'name' => $holiday['name'],
                'label' => $holiday['label'],
            ],
        ];
    }

    private function formatRange(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $from = $start->format('M') === $end->format('M')
            ? $start->format('j')
            : $start->format('j M');

        return $from . ' ' . self::DASH . ' ' . $end->format('j M');
    }
}
