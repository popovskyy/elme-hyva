<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Test\Unit\Model\Service;

use Elme\StoreLocator\Model\Service\Haversine;
use PHPUnit\Framework\TestCase;

class HaversineTest extends TestCase
{
    public function testSamePointIsZero(): void
    {
        $km = (new Haversine())->kilometres(-37.819597, 145.162612, -37.819597, 145.162612);
        self::assertSame(0.0, $km);
    }

    public function testBlackburnToTarneitIsRoughlyThirtyKm(): void
    {
        // Blackburn showroom to Tarneit DC
        $km = (new Haversine())->kilometres(-37.819597, 145.162612, -37.806501, 144.703700);
        self::assertGreaterThan(35.0, $km);
        self::assertLessThan(50.0, $km);
    }
}
