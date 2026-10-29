<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\StoreContact;
use Elme\Theme\ViewModel\StoreVisit;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see StoreVisit
 */
class StoreVisitTest extends TestCase
{
    private StoreVisit $visit;

    protected function setUp(): void
    {
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn(null);

        $this->visit = new StoreVisit(new StoreContact($config, $this->createMock(RegionFactory::class)));
    }

    public function testHoursRunOfDaysAndSingleDay(): void
    {
        $hours = $this->visit->getHours(['hours' => [
            ['day_from' => 'Monday', 'day_to' => 'Saturday', 'opens' => '09:00', 'closes' => '17:00'],
            ['day_from' => 'Sunday', 'day_to' => '', 'opens' => '10:00', 'closes' => '17:30'],
        ]]);

        self::assertCount(2, $hours);
        self::assertSame('Mon - Sat', $hours[0]['label']);
        self::assertSame('Monday to Saturday', $hours[0]['label_full']);
        self::assertCount(6, $hours[0]['days']);
        self::assertSame('9am - 5pm', $hours[0]['time_label']);
        self::assertSame(['Sunday'], $hours[1]['days']);
        self::assertSame('Sun', $hours[1]['label']);
        self::assertSame('10am - 5:30pm', $hours[1]['time_label']);
    }

    public function testHoursSkipBadRows(): void
    {
        $hours = $this->visit->getHours(['hours' => [
            ['day_from' => 'Saturday', 'day_to' => 'Monday', 'opens' => '09:00', 'closes' => '17:00'],
            ['day_from' => 'Funday', 'opens' => '09:00', 'closes' => '17:00'],
            ['day_from' => 'Monday', 'opens' => 'nine', 'closes' => '17:00'],
            ['day_from' => 'Tuesday', 'opens' => '9', 'closes' => '25:00'],
            ['day_from' => 'Friday', 'opens' => '9', 'closes' => '17'],
        ]]);

        self::assertCount(1, $hours);
        self::assertSame(['Friday'], $hours[0]['days']);
        self::assertSame('09:00', $hours[0]['opens']);
    }

    public function testSchemaNodeUsesStoreContactAndHours(): void
    {
        $node = $this->visit->getSchemaNode(
            [
                'store_name' => 'Elme Living Outlet & Clearance Store',
                'intro' => 'Up to 80% off RRP.',
                'public_note' => 'Open to the public',
                'hours' => [
                    ['day_from' => 'Monday', 'day_to' => 'Saturday', 'opens' => '09:00', 'closes' => '17:00'],
                    ['day_from' => 'Sunday', 'opens' => '10:00', 'closes' => '17:00'],
                ],
            ],
            'https://elme.test/outlet-and-clearance-store',
            'https://elme.test',
            ['src' => 'https://elme.test/media/hero.jpg', 'width' => 2880, 'height' => 800]
        );

        self::assertNotNull($node);
        self::assertSame('OutletStore', $node['@type']);
        self::assertSame('https://elme.test/outlet-and-clearance-store#store', $node['@id']);
        self::assertSame('+61 3 7067 9725', $node['telephone']);
        self::assertSame('1 Apollo Court', $node['address']['streetAddress']);
        self::assertSame('3130', $node['address']['postalCode']);
        self::assertSame(StoreContact::LATITUDE, $node['geo']['latitude']);
        self::assertSame('Sunday', $node['openingHoursSpecification'][1]['dayOfWeek']);
        self::assertCount(6, $node['openingHoursSpecification'][0]['dayOfWeek']);
        self::assertTrue($node['publicAccess']);
        self::assertArrayNotHasKey('priceRange', $node);
    }

    public function testNoSchemaNodeWithoutName(): void
    {
        self::assertNull($this->visit->getSchemaNode([], 'https://elme.test/x', 'https://elme.test', null));
    }
}
