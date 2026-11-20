<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Test\Unit\Model\Service;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Model\LocationRepository;
use Elme\StoreLocator\Model\Service\Haversine;
use Elme\StoreLocator\Model\Service\LocationQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LocationQueryTest extends TestCase
{
    /** @var LocationRepository&MockObject */
    private LocationRepository $repository;

    private LocationQuery $query;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LocationRepository::class);
        $this->query = new LocationQuery($this->repository, new Haversine());
    }

    public function testStateFilterReturnsOnlyMatchingState(): void
    {
        $vic = $this->location('Blackburn', 'VIC', '3130', -37.819597, 145.162612);
        $this->repository->expects(self::once())
            ->method('getActiveList')
            ->with('VIC')
            ->willReturn([$vic]);

        $result = $this->query->list('VIC');
        self::assertCount(1, $result);
        self::assertSame('VIC', $result[0]->getState());
    }

    public function testNearestToPostcode3130ReturnsBlackburnFirst(): void
    {
        $blackburn = $this->location('Blackburn', 'VIC', '3130', -37.819597, 145.162612);
        $tarneit = $this->location('Tarneit', 'VIC', '3029', -37.806501, 144.703700);
        $stMarys = $this->location('St Marys', 'NSW', '2760', -33.75, 150.77);

        $this->repository->method('getActiveList')->willReturn([$tarneit, $stMarys, $blackburn]);

        $result = $this->query->nearestToPostcode('3130');
        self::assertSame('Blackburn', $result[0]->getName());
        self::assertSame('Tarneit', $result[1]->getName());
    }

    private function location(
        string $name,
        string $state,
        string $postcode,
        float $lat,
        float $lng
    ): LocationInterface {
        $location = $this->createMock(LocationInterface::class);
        $location->method('getName')->willReturn($name);
        $location->method('getState')->willReturn($state);
        $location->method('getPostcode')->willReturn($postcode);
        $location->method('getLatitude')->willReturn($lat);
        $location->method('getLongitude')->willReturn($lng);

        return $location;
    }
}
