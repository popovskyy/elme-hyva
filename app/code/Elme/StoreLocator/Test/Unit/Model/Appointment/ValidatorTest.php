<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Test\Unit\Model\Appointment;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Model\Appointment\Schedule;
use Elme\StoreLocator\Model\Appointment\Validator;
use Elme\StoreLocator\ViewModel\Hours;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private const WEEKDAYS_9_TO_5 = [
        'monday' => ['from' => '09:00', 'to' => '17:00'],
        'tuesday' => ['from' => '09:00', 'to' => '17:00'],
        'wednesday' => ['from' => '09:00', 'to' => '17:00'],
        'thursday' => ['from' => '09:00', 'to' => '17:00'],
        'friday' => ['from' => '09:00', 'to' => '16:30'],
        'saturday' => ['from' => '', 'to' => ''],
        'sunday' => ['from' => '', 'to' => ''],
    ];

    private Schedule $schedule;

    private Validator $validator;

    /**
     * @var array<string, LocationInterface>
     */
    private array $showrooms;

    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->schedule = new Schedule(new Hours($this->createStub(TimezoneInterface::class)));
        $this->validator = new Validator($this->schedule);

        $showroom = $this->createStub(LocationInterface::class);
        $showroom->method('getUrlKey')->willReturn('melbourne');
        $showroom->method('getName')->willReturn('Melbourne Showroom');
        $showroom->method('getStreet')->willReturn('1 Apollo Court');
        $showroom->method('getCity')->willReturn('Blackburn');
        $showroom->method('getState')->willReturn('VIC');
        $showroom->method('getPostcode')->willReturn('3130');
        $showroom->method('getOpeningHours')->willReturn(json_encode(self::WEEKDAYS_9_TO_5));
        $showroom->method('getHolidayHours')->willReturn(json_encode([
            ['date' => '2026-11-18', 'from' => '', 'to' => '', 'name' => 'Stocktake'],
        ]));
        $this->showrooms = ['melbourne' => $showroom];

        // Thursday 12 Nov 2026
        $this->today = new \DateTimeImmutable('2026-11-12 00:00:00');
    }

    public function testWeekdaySlotsFitInsideOpeningHours(): void
    {
        $slots = $this->schedule->weekdaySlots($this->showrooms['melbourne']);

        $this->assertSame(['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'], $slots['monday']);
        $this->assertSame('15:00', end($slots['friday']));
        $this->assertSame([], $slots['saturday']);
    }

    public function testValidRequestIsNormalised(): void
    {
        $result = $this->validator->validate($this->params([
            'interests' => ['flora', 'nope', 'furniture'],
        ]), $this->showrooms, $this->today);

        $this->assertSame('Melbourne Showroom', $result['showroom_name']);
        $this->assertSame('Monday 16 November 2026', $result['date_label']);
        $this->assertSame('10am', $result['time_label']);
        $this->assertSame(2, $result['visitors']);
        $this->assertSame(['flora', 'furniture'], $result['interests']);
        $this->assertSame('1 Apollo Court, Blackburn VIC 3130', $result['showroom_address']);
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('invalidProvider')]
    public function testInvalidRequestIsRejected(array $override, string $message): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches($message);

        $this->validator->validate($this->params($override), $this->showrooms, $this->today);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'unknown showroom' => [['showroom' => 'sydney'], '/showroom/'],
            'today is too soon' => [['date' => '2026-11-12'], '/between 13 Nov 2026/'],
            'beyond 90 days' => [['date' => '2027-02-11'], '/between/'],
            'not a date' => [['date' => '16/11/2026'], '/date/'],
            'saturday closed' => [['date' => '2026-11-14'], '/closed on Saturday/'],
            'holiday closed' => [['date' => '2026-11-18'], '/closed on Wednesday/'],
            'slot after closing' => [['date' => '2026-11-20', 'time' => '16:00'], '/time/'],
            'too many visitors' => [['visitors' => '7'], '/how many/'],
            'bad email' => [['email' => 'not-an-email'], '/email/'],
            'missing company' => [['company' => ' '], '/company/'],
            'long notes' => [['notes' => str_repeat('a', 501)], '/500/'],
        ];
    }

    public function testHoneypotIsBlockedWithoutAFriendlyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->validator->validate($this->params(['hideit' => 'x']), $this->showrooms, $this->today);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function params(array $override = []): array
    {
        return array_merge([
            'showroom' => 'melbourne',
            'date' => '2026-11-16',
            'time' => '10:00',
            'visitors' => '2',
            'name' => 'Sam Retailer',
            'email' => 'sam@example.com',
            'telephone' => '0400 000 000',
            'company' => 'Sam Homewares',
            'notes' => '',
            'hideit' => '',
        ], $override);
    }
}
