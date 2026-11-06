<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Service;

/**
 * Great-circle distance in kilometres between two WGS84 points.
 */
class Haversine
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function kilometres(float $latFrom, float $lngFrom, float $latTo, float $lngTo): float
    {
        $latFromRad = deg2rad($latFrom);
        $latToRad = deg2rad($latTo);
        $deltaLat = deg2rad($latTo - $latFrom);
        $deltaLng = deg2rad($lngTo - $lngFrom);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFromRad) * cos($latToRad) * sin($deltaLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
