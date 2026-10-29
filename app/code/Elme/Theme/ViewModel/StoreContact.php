<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * FE-13 / FE-55 NAP: phone, email and showroom from Magento store config with Elme defaults.
 */
class StoreContact implements ArgumentInterface
{
    private const XML_PATH_PHONE = 'general/store_information/phone';

    private const XML_PATH_EMAIL = 'trans_email/ident_general/email';

    private const XML_PATH_STREET1 = 'general/store_information/street_line1';

    private const XML_PATH_STREET2 = 'general/store_information/street_line2';

    private const XML_PATH_CITY = 'general/store_information/city';

    private const XML_PATH_POSTCODE = 'general/store_information/postcode';

    private const XML_PATH_REGION_ID = 'general/store_information/region_id';

    private const DEFAULT_PHONE_DISPLAY = '(03) 7067 9725';

    private const DEFAULT_PHONE_E164 = '+61370679725';

    private const DEFAULT_EMAIL = 'hello@elmeliving.com';

    private const DEFAULT_STREET = '1 Apollo Court';

    private const DEFAULT_CITY = 'Blackburn';

    private const DEFAULT_REGION_CODE = 'VIC';

    private const DEFAULT_POSTCODE = '3130';

    /** Map pin of 1 Apollo Court, Blackburn (showroom and outlet). */
    public const LATITUDE = -37.819352;

    public const LONGITUDE = 145.1606185;

    private const DEFAULT_MAPS_URL = 'https://www.google.com/maps/place/1+Apollo+Ct,+Blackburn+VIC+3130'
        . '/@' . self::LATITUDE . ',' . self::LONGITUDE . ',17z';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RegionFactory $regionFactory
    ) {
    }

    public function getPhoneDisplay(): string
    {
        $phone = trim((string) $this->scopeConfig->getValue(self::XML_PATH_PHONE, ScopeInterface::SCOPE_STORE));
        return $phone !== '' ? $phone : self::DEFAULT_PHONE_DISPLAY;
    }

    public function getPhoneTelHref(): string
    {
        $digits = preg_replace('/\D+/', '', $this->getPhoneDisplay()) ?? '';
        if ($digits === '') {
            return 'tel:' . self::DEFAULT_PHONE_E164;
        }

        // AU local numbers stored as (03) … → E.164 +61…
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return 'tel:+61' . substr($digits, 1);
        }
        if (str_starts_with($digits, '61') && strlen($digits) >= 11) {
            return 'tel:+' . $digits;
        }

        return 'tel:' . self::DEFAULT_PHONE_E164;
    }

    /**
     * International number with spaces for schema.org `telephone`, e.g. +61 3 7067 9725.
     */
    public function getPhoneInternational(): string
    {
        $e164 = substr($this->getPhoneTelHref(), strlen('tel:'));

        if (preg_match('/^\+61([2378])(\d{4})(\d{4})$/', $e164, $match)) {
            return sprintf('+61 %s %s %s', $match[1], $match[2], $match[3]);
        }

        return $e164;
    }

    public function getEmail(): string
    {
        $email = trim((string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL, ScopeInterface::SCOPE_STORE));
        if ($email === '' || str_ends_with(strtolower($email), '@example.com')) {
            return self::DEFAULT_EMAIL;
        }

        return $email;
    }

    public function getEmailMailtoHref(): string
    {
        return 'mailto:' . $this->getEmail();
    }

    /**
     * Single-line showroom address for visible NAP text.
     */
    public function getShowroomAddressLine(): string
    {
        return trim(sprintf(
            '%s, %s %s %s',
            $this->streetLine(),
            $this->city(),
            $this->regionCode(),
            $this->postcode()
        ));
    }

    /**
     * Two-line label for the Maps link (street / suburb state postcode).
     *
     * @return array{0: string, 1: string}
     */
    public function getShowroomAddressLines(): array
    {
        return [
            $this->streetLine(),
            trim(sprintf('%s %s %s', $this->city(), $this->regionCode(), $this->postcode())),
        ];
    }

    public function getMapsUrl(): string
    {
        if ($this->usesDefaultAddress()) {
            return self::DEFAULT_MAPS_URL;
        }

        $query = rawurlencode($this->getShowroomAddressLine() . ', Australia');
        return 'https://www.google.com/maps/search/?api=1&query=' . $query;
    }

    /**
     * Google Maps route to the showroom address (the "Get directions" link and schema.org `hasMap`).
     */
    public function getDirectionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='
            . rawurlencode($this->getShowroomAddressLine() . ', Australia');
    }

    /**
     * Address parts for schema.org PostalAddress.
     *
     * @return array{street: string, city: string, region: string, postcode: string, country: string}
     */
    public function getAddressParts(): array
    {
        return [
            'street' => $this->streetLine(),
            'city' => $this->city(),
            'region' => $this->regionCode(),
            'postcode' => $this->postcode(),
            'country' => 'AU',
        ];
    }

    /**
     * Map pin for schema.org GeoCoordinates; null once the address in store config moves elsewhere.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function getGeo(): ?array
    {
        if (!$this->usesDefaultAddress()) {
            return null;
        }

        return ['latitude' => self::LATITUDE, 'longitude' => self::LONGITUDE];
    }

    private function streetLine(): string
    {
        $line1 = trim((string) $this->scopeConfig->getValue(self::XML_PATH_STREET1, ScopeInterface::SCOPE_STORE));
        $line2 = trim((string) $this->scopeConfig->getValue(self::XML_PATH_STREET2, ScopeInterface::SCOPE_STORE));
        $street = trim($line1 . ($line2 !== '' ? ' ' . $line2 : ''));

        return $street !== '' ? $street : self::DEFAULT_STREET;
    }

    private function city(): string
    {
        $city = trim((string) $this->scopeConfig->getValue(self::XML_PATH_CITY, ScopeInterface::SCOPE_STORE));
        return $city !== '' ? $city : self::DEFAULT_CITY;
    }

    private function postcode(): string
    {
        $postcode = trim((string) $this->scopeConfig->getValue(self::XML_PATH_POSTCODE, ScopeInterface::SCOPE_STORE));
        return $postcode !== '' ? $postcode : self::DEFAULT_POSTCODE;
    }

    private function regionCode(): string
    {
        $regionId = (int) $this->scopeConfig->getValue(self::XML_PATH_REGION_ID, ScopeInterface::SCOPE_STORE);
        if ($regionId > 0) {
            $region = $this->regionFactory->create()->load($regionId);
            $code = trim((string) $region->getCode());
            if ($code !== '') {
                return $code;
            }
        }

        return self::DEFAULT_REGION_CODE;
    }

    private function usesDefaultAddress(): bool
    {
        return $this->streetLine() === self::DEFAULT_STREET
            && $this->city() === self::DEFAULT_CITY
            && $this->regionCode() === self::DEFAULT_REGION_CODE
            && $this->postcode() === self::DEFAULT_POSTCODE;
    }
}
