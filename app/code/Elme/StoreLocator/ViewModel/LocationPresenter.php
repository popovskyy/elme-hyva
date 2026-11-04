<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Controller\Router;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Shared formatting for a store locator location (type labels, URLs, maps, schema stubs).
 */
class LocationPresenter implements ArgumentInterface
{
    public const TYPE_LABEL_TRADE_SHOWROOM = 'Trade showroom';

    public const TYPE_LABEL_DISTRIBUTION = 'Distribution & pick-up';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly Hours $hours
    ) {
    }

    public function getHours(): Hours
    {
        return $this->hours;
    }

    public function isShowroom(LocationInterface $location): bool
    {
        $type = $location->getType();

        return $type === LocationInterface::TYPE_TRADE_ONLY
            || $type === LocationInterface::TYPE_SHOWROOM;
    }

    public function isTradeOnly(LocationInterface $location): bool
    {
        return $location->getType() === LocationInterface::TYPE_TRADE_ONLY;
    }

    public function getTypeLabel(LocationInterface $location): string
    {
        return $this->isShowroom($location)
            ? (string) __(self::TYPE_LABEL_TRADE_SHOWROOM)
            : (string) __(self::TYPE_LABEL_DISTRIBUTION);
    }

    public function getSchemaType(LocationInterface $location): string
    {
        return $this->isShowroom($location) ? 'FurnitureStore' : 'LocalBusiness';
    }

    public function getListUrl(): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/')
            . '/' . Router::LIST_PATH;
    }

    public function getLocationUrl(LocationInterface $location): string
    {
        return $this->getListUrl() . '/' . ltrim($location->getUrlKey(), '/');
    }

    public function getLocalBusinessId(LocationInterface $location): string
    {
        return $this->getLocationUrl($location) . '#localbusiness';
    }

    public function getAddressLine(LocationInterface $location): string
    {
        $parts = array_filter([
            $location->getStreet(),
            trim(implode(' ', array_filter([
                $location->getCity(),
                $location->getState(),
                $location->getPostcode(),
            ]))),
        ]);

        return implode(', ', $parts);
    }

    public function getTelHref(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return 'tel:' . preg_replace('/\s+/', '', $phone);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return 'tel:+61' . substr($digits, 1);
        }
        if (str_starts_with($digits, '1800') || str_starts_with($digits, '1300')) {
            return 'tel:+61' . substr($digits, 1);
        }
        if (str_starts_with($digits, '61')) {
            return 'tel:+' . $digits;
        }

        return 'tel:' . $digits;
    }

    public function getDirectionsUrl(LocationInterface $location): string
    {
        $lat = $location->getLatitude();
        $lng = $location->getLongitude();
        if ($lat !== null && $lng !== null) {
            return 'https://www.google.com/maps/dir/?api=1&destination='
                . rawurlencode(sprintf('%s,%s', $lat, $lng));
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='
            . rawurlencode($this->getAddressLine($location) . ', Australia');
    }

    public function getGoogleMapsUrl(LocationInterface $location): string
    {
        $lat = $location->getLatitude();
        $lng = $location->getLongitude();
        if ($lat !== null && $lng !== null) {
            return 'https://www.google.com/maps/search/?api=1&query='
                . rawurlencode(sprintf('%s,%s', $lat, $lng));
        }

        return 'https://www.google.com/maps/search/?api=1&query='
            . rawurlencode($this->getAddressLine($location) . ', Australia');
    }

    public function getAppleMapsUrl(LocationInterface $location): string
    {
        $lat = $location->getLatitude();
        $lng = $location->getLongitude();
        if ($lat !== null && $lng !== null) {
            return 'https://maps.apple.com/?daddr=' . rawurlencode(sprintf('%s,%s', $lat, $lng));
        }

        return 'https://maps.apple.com/?q=' . rawurlencode($this->getAddressLine($location) . ', Australia');
    }

    public function getMapEmbedUrl(LocationInterface $location): string
    {
        $lat = $location->getLatitude();
        $lng = $location->getLongitude();
        if ($lat !== null && $lng !== null) {
            return 'https://maps.google.com/maps?q=' . rawurlencode(sprintf('%s,%s', $lat, $lng))
                . '&z=15&output=embed';
        }

        return 'https://maps.google.com/maps?q='
            . rawurlencode($this->getAddressLine($location) . ', Australia')
            . '&z=15&output=embed';
    }

    public function getAppointmentUrl(): string
    {
        return $this->urlBuilder->getUrl('', ['_direct' => 'contact-us']);
    }

    public function getApplyUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/create');
    }

    /**
     * Card payload for Alpine (list page). Distances filled client-side.
     *
     * @return array<string, mixed>
     */
    public function toListCard(LocationInterface $location): array
    {
        return [
            'id' => (int) $location->getLocationId(),
            'name' => $location->getName(),
            'type' => $location->getType(),
            'type_label' => $this->getTypeLabel($location),
            'is_showroom' => $this->isShowroom($location),
            'is_trade_only' => $this->isTradeOnly($location),
            'street' => (string) $location->getStreet(),
            'city' => (string) $location->getCity(),
            'state' => $location->getState(),
            'postcode' => $location->getPostcode(),
            'address_line' => $this->getAddressLine($location),
            'phone' => (string) $location->getPhone(),
            'phone_tel' => $this->getTelHref($location->getPhone()),
            'description' => (string) $location->getDescription(),
            'url' => $this->getLocationUrl($location),
            'url_key' => $location->getUrlKey(),
            'lat' => $location->getLatitude(),
            'lng' => $location->getLongitude(),
            'today_hours' => $this->hours->todayLabel($location->getOpeningHours()),
            'directions_url' => $this->getDirectionsUrl($location),
            'map_embed_url' => $this->getMapEmbedUrl($location),
            'distance_km' => null,
        ];
    }

    /**
     * Full LocalBusiness / FurnitureStore node for JSON-LD.
     *
     * @return array<string, mixed>
     */
    public function toSchemaPlace(LocationInterface $location, bool $full = true): array
    {
        $node = [
            '@type' => $this->getSchemaType($location),
            '@id' => $this->getLocalBusinessId($location),
            'name' => $location->getName(),
            'url' => $this->getLocationUrl($location),
        ];

        if (!$full) {
            return $node;
        }

        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        $description = trim((string) $location->getDescription());
        if ($description === '' && $this->isTradeOnly($location)) {
            $description = (string) __(
                'Trade-only showroom for registered Elme Living wholesale customers. Book an appointment to visit.'
            );
        }
        if ($description !== '') {
            $node['description'] = $description;
        }

        if ($phone = trim((string) $location->getPhone())) {
            $node['telephone'] = $phone;
        }

        $node['parentOrganization'] = ['@id' => $baseUrl . '/#organization'];
        $node['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => (string) $location->getStreet(),
            'addressLocality' => (string) $location->getCity(),
            'addressRegion' => $location->getState(),
            'postalCode' => $location->getPostcode(),
            'addressCountry' => 'AU',
        ];

        $lat = $location->getLatitude();
        $lng = $location->getLongitude();
        if ($lat !== null && $lng !== null) {
            $node['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        $specs = [];
        foreach ($this->hours->openingHoursSpecification($location->getOpeningHours()) as $spec) {
            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => count($spec['days']) === 1 ? $spec['days'][0] : $spec['days'],
                'opens' => $spec['opens'],
                'closes' => $spec['closes'],
            ];
        }
        if ($specs) {
            $node['openingHoursSpecification'] = $specs;
        }

        $node['hasMap'] = $this->getDirectionsUrl($location);

        return $node;
    }
}
