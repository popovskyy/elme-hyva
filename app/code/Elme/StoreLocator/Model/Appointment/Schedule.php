<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Appointment;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\ViewModel\Hours;

/**
 * FE-90: bookable hourly start times of a showroom, from its opening hours and holiday overrides.
 * A slot is an hour that fits completely inside the opening hours (9am-5pm gives 9:00 ... 16:00).
 */
class Schedule
{
    public const SLOT_MINUTES = 60;

    public function __construct(
        private readonly Hours $hours
    ) {
    }

    /**
     * Start times per weekday key (monday ... sunday); an empty list means closed.
     *
     * @return array<string, list<string>>
     */
    public function weekdaySlots(LocationInterface $location): array
    {
        $slots = [];
        foreach ($this->hours->decode($location->getOpeningHours()) as $day => $range) {
            $slots[$day] = $this->slotsBetween($range['from'], $range['to']);
        }

        return $slots;
    }

    /**
     * Start times per holiday date (Y-m-d) that override the weekday; an empty list means closed.
     *
     * @return array<string, list<string>>
     */
    public function holidaySlots(LocationInterface $location): array
    {
        $slots = [];
        foreach ($this->hours->decodeHolidays($location->getHolidayHours()) as $date => $row) {
            $slots[$date] = $this->slotsBetween($row['from'], $row['to']);
        }

        return $slots;
    }

    /**
     * @return list<string>
     */
    public function slotsForDate(LocationInterface $location, \DateTimeImmutable $date): array
    {
        $holidays = $this->holidaySlots($location);
        $key = $date->format('Y-m-d');
        if (array_key_exists($key, $holidays)) {
            return $holidays[$key];
        }

        return $this->weekdaySlots($location)[strtolower($date->format('l'))] ?? [];
    }

    /**
     * Every start time the showroom ever offers, sorted, for the time select.
     *
     * @param LocationInterface[] $locations
     * @return list<string>
     */
    public function allSlots(array $locations): array
    {
        $all = [];
        foreach ($locations as $location) {
            foreach ($this->weekdaySlots($location) as $slots) {
                $all = array_merge($all, $slots);
            }
            foreach ($this->holidaySlots($location) as $slots) {
                $all = array_merge($all, $slots);
            }
        }
        $all = array_values(array_unique($all));
        sort($all);

        return $all;
    }

    /**
     * "9am" / "9:30am", same clock format as the opening hours table.
     */
    public function slotLabel(string $slot): string
    {
        return $this->hours->clock($slot);
    }

    /**
     * @return list<string>
     */
    private function slotsBetween(string $from, string $to): array
    {
        $start = $this->toMinutes($from);
        $end = $this->toMinutes($to);
        if ($start === null || $end === null || $end <= $start) {
            return [];
        }

        $slots = [];
        for ($minute = $start; $minute + self::SLOT_MINUTES <= $end; $minute += self::SLOT_MINUTES) {
            $slots[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        }

        return $slots;
    }

    private function toMinutes(string $time): ?int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = (int) $m[2];
        if ($hour > 23 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }
}
