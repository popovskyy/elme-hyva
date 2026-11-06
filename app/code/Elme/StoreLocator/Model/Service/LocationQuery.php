<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Service;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Model\LocationRepository;
use Magento\Framework\Exception\LocalizedException;

/**
 * Storefront-facing location queries: list, state filter, nearest by postcode or coordinates.
 */
class LocationQuery
{
    public function __construct(
        private readonly LocationRepository $locationRepository,
        private readonly Haversine $haversine
    ) {
    }

    /**
     * Active locations, optionally limited to one state code (VIC, NSW, ...).
     *
     * @return LocationInterface[]
     */
    public function list(?string $state = null): array
    {
        return $this->locationRepository->getActiveList($state);
    }

    /**
     * Active locations sorted nearest-first to a postcode.
     * Origin is the first active location that shares the postcode (so 3130 gives Blackburn),
     * otherwise any active location whose postcode equals the query still wins by distance 0
     * when present; if no location has that postcode, throws.
     *
     * @return LocationInterface[]
     * @throws LocalizedException
     */
    public function nearestToPostcode(string $postcode, ?string $state = null): array
    {
        $postcode = trim($postcode);
        if ($postcode === '') {
            throw new LocalizedException(__('Postcode is required.'));
        }

        $locations = $this->list($state);
        $origin = $this->resolveOriginFromPostcode($locations, $postcode);
        if ($origin === null) {
            // Fall back to unfiltered active list for origin resolution (postcode may be in another state).
            $all = $state !== null ? $this->list(null) : $locations;
            $origin = $this->resolveOriginFromPostcode($all, $postcode);
        }

        if ($origin === null) {
            throw new LocalizedException(__('No location coordinates found for postcode %1.', $postcode));
        }

        return $this->sortByDistance($locations, $origin['lat'], $origin['lng']);
    }

    /**
     * Active locations sorted nearest-first to a lat/lng pair.
     *
     * @return LocationInterface[]
     */
    public function nearestToCoordinates(float $latitude, float $longitude, ?string $state = null): array
    {
        return $this->sortByDistance($this->list($state), $latitude, $longitude);
    }

    /**
     * @param LocationInterface[] $locations
     * @return array{lat: float, lng: float}|null
     */
    private function resolveOriginFromPostcode(array $locations, string $postcode): ?array
    {
        foreach ($locations as $location) {
            if ($location->getPostcode() === $postcode
                && $location->getLatitude() !== null
                && $location->getLongitude() !== null
            ) {
                return [
                    'lat' => $location->getLatitude(),
                    'lng' => $location->getLongitude(),
                ];
            }
        }

        return null;
    }

    /**
     * @param LocationInterface[] $locations
     * @return LocationInterface[]
     */
    private function sortByDistance(array $locations, float $latitude, float $longitude): array
    {
        $scored = [];
        foreach ($locations as $location) {
            $lat = $location->getLatitude();
            $lng = $location->getLongitude();
            $distance = ($lat === null || $lng === null)
                ? PHP_FLOAT_MAX
                : $this->haversine->kilometres($latitude, $longitude, $lat, $lng);
            $scored[] = ['location' => $location, 'distance' => $distance];
        }

        usort(
            $scored,
            static function (array $a, array $b): int {
                return $a['distance'] <=> $b['distance'];
            }
        );

        return array_map(static fn (array $row): LocationInterface => $row['location'], $scored);
    }
}
