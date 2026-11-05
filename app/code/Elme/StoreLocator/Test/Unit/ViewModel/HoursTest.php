<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Test\Unit\ViewModel;

use Elme\StoreLocator\ViewModel\Hours;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;

class HoursTest extends TestCase
{
    private const WEEKDAYS_9_TO_5 = [
        'monday' => ['from' => '09:00', 'to' => '17:00'],
        'tuesday' => ['from' => '09:00', 'to' => '17:00'],
        'wednesday' => ['from' => '09:00', 'to' => '17:00'],
        'thursday' => ['from' => '09:00', 'to' => '17:00'],
        'friday' => ['from' => '09:00', 'to' => '17:00'],
        'saturday' => ['from' => '', 'to' => ''],
        'sunday' => ['from' => '', 'to' => ''],
    ];

    private Hours $hours;

    protected function setUp(): void
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        // Wednesday 4 Nov 2026; the week starts on Monday 2 Nov.
        $timezone->method('date')->willReturn(new \DateTime('2026-11-04 10:00:00'));
        $this->hours = new Hours($timezone);
    }

    public function testOpeningHoursSpecificationGroupsWeekdaysAndOmitsClosed(): void
    {
        $specs = $this->hours->openingHoursSpecification(json_encode(self::WEEKDAYS_9_TO_5));

        $this->assertCount(1, $specs);
        $this->assertSame('09:00', $specs[0]['opens']);
        $this->assertSame('17:00', $specs[0]['closes']);
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $specs[0]['days']
        );
    }

    public function testTodayLabelUsesWeekdayHours(): void
    {
        $this->assertSame("9am \u{2013} 5pm", $this->hours->todayLabel(json_encode(self::WEEKDAYS_9_TO_5)));
    }

    public function testTodayLabelHolidayOverridesWeekday(): void
    {
        $holidays = json_encode([['date' => '2026-11-04', 'from' => '', 'to' => '', 'name' => 'Stocktake']]);

        $this->assertSame('Closed', $this->hours->todayLabel(json_encode(self::WEEKDAYS_9_TO_5), $holidays));
    }

    public function testScheduleWeeksStartsThisMondayAndMarksToday(): void
    {
        $weeks = $this->hours->scheduleWeeks(json_encode(self::WEEKDAYS_9_TO_5), null);

        $this->assertCount(Hours::SCHEDULE_WEEKS, $weeks);
        $this->assertSame("2 \u{2013} 8 Nov", $weeks[0]['label']);
        $this->assertSame("30 Nov \u{2013} 6 Dec", $weeks[4]['label']);
        $this->assertSame('2026-11-02', $weeks[0]['days'][0]['iso']);
        $this->assertSame('Mon', $weeks[0]['days'][0]['day']);
        $this->assertTrue($weeks[0]['days'][2]['is_today']);
        $this->assertFalse($weeks[0]['days'][1]['is_today']);
        $this->assertTrue($weeks[0]['days'][5]['is_closed']);
        $this->assertSame("9am \u{2013} 5pm", $weeks[1]['days'][0]['hours']);
    }

    public function testScheduleWeeksAppliesHolidayRow(): void
    {
        $holidays = json_encode([
            ['date' => '2026-11-10', 'from' => '10:00', 'to' => '14:30', 'name' => 'Stocktake', 'label' => 'Short day'],
            ['date' => 'not-a-date', 'from' => '', 'to' => ''],
        ]);

        $weeks = $this->hours->scheduleWeeks(json_encode(self::WEEKDAYS_9_TO_5), $holidays);
        $tuesday = $weeks[1]['days'][1];

        $this->assertSame('2026-11-10', $tuesday['iso']);
        $this->assertSame("10am \u{2013} 2:30pm", $tuesday['hours']);
        $this->assertSame(['name' => 'Stocktake', 'label' => 'Short day'], $tuesday['holiday']);
        $this->assertNull($weeks[1]['days'][0]['holiday']);
    }

    public function testScheduleWeeksEmptyWithoutAnyHours(): void
    {
        $this->assertSame([], $this->hours->scheduleWeeks(null, null));
        $this->assertSame([], $this->hours->scheduleWeeks(null, json_encode([['date' => '2025-01-01']])));
    }

    public function testDayAndHolidayLabelsForBrowser(): void
    {
        $holidays = json_encode([
            ['date' => '2026-10-01', 'from' => '', 'to' => ''],
            ['date' => '2026-12-25', 'from' => '', 'to' => ''],
        ]);

        $days = $this->hours->dayLabels(json_encode(self::WEEKDAYS_9_TO_5));
        $this->assertSame("9am \u{2013} 5pm", $days['friday']);
        $this->assertSame('Closed', $days['sunday']);
        $this->assertSame(['2026-12-25' => 'Closed'], $this->hours->holidayLabels($holidays));
    }

    public function testValidation(): void
    {
        $this->assertNull($this->hours->validateOpeningHours(json_encode(self::WEEKDAYS_9_TO_5)));
        $this->assertNull($this->hours->validateOpeningHours(''));
        $this->assertNotNull($this->hours->validateOpeningHours('{bad'));
        $this->assertNotNull($this->hours->validateOpeningHours(json_encode(['mon' => ['from' => '09:00']])));
        $this->assertNotNull($this->hours->validateOpeningHours(json_encode(['monday' => ['from' => '9am']])));

        $this->assertNull($this->hours->validateHolidayHours(json_encode([['date' => '2026-12-25']])));
        $this->assertNotNull($this->hours->validateHolidayHours(json_encode(['date' => '2026-12-25'])));
        $this->assertNotNull($this->hours->validateHolidayHours(json_encode([['date' => '2026-02-30']])));
        $this->assertNotNull($this->hours->validateHolidayHours(json_encode([['date' => '2026-12-24', 'to' => '25:00']])));
    }
}
