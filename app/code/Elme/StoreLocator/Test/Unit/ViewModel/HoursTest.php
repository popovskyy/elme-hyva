<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Test\Unit\ViewModel;

use Elme\StoreLocator\ViewModel\Hours;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;

class HoursTest extends TestCase
{
    private Hours $hours;

    protected function setUp(): void
    {
        $timezone = $this->createMock(TimezoneInterface::class);
        $timezone->method('date')->willReturn(new \DateTimeImmutable('2026-11-04 10:00:00')); // Wednesday
        $this->hours = new Hours($timezone);
    }

    public function testOpeningHoursSpecificationGroupsWeekdaysAndOmitsClosed(): void
    {
        $json = json_encode([
            'monday' => ['from' => '09:00', 'to' => '17:00'],
            'tuesday' => ['from' => '09:00', 'to' => '17:00'],
            'wednesday' => ['from' => '09:00', 'to' => '17:00'],
            'thursday' => ['from' => '09:00', 'to' => '17:00'],
            'friday' => ['from' => '09:00', 'to' => '17:00'],
            'saturday' => ['from' => '', 'to' => ''],
            'sunday' => ['from' => '', 'to' => ''],
        ]);

        $specs = $this->hours->openingHoursSpecification($json);

        $this->assertCount(1, $specs);
        $this->assertSame('09:00', $specs[0]['opens']);
        $this->assertSame('17:00', $specs[0]['closes']);
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $specs[0]['days']
        );
    }

    public function testTodayLabelOnWednesday(): void
    {
        $json = json_encode([
            'wednesday' => ['from' => '09:00', 'to' => '17:00'],
            'saturday' => ['from' => '', 'to' => ''],
        ]);

        $this->assertSame('9am – 5pm', $this->hours->todayLabel($json));
    }

    public function testWeekRowsHighlightToday(): void
    {
        $json = json_encode([
            'monday' => ['from' => '07:00', 'to' => '14:30'],
            'wednesday' => ['from' => '07:00', 'to' => '14:30'],
            'sunday' => ['from' => '', 'to' => ''],
        ]);

        $week = $this->hours->weekRows($json);
        $this->assertCount(7, $week);
        $this->assertTrue($week[2]['is_today']); // wednesday
        $this->assertFalse($week[2]['is_closed']);
        $this->assertTrue($week[6]['is_closed']);
    }
}
