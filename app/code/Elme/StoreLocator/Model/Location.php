<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Model\ResourceModel\Location as LocationResource;
use Magento\Framework\Model\AbstractModel;

class Location extends AbstractModel implements LocationInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'elme_store_locator_location';

    protected function _construct(): void
    {
        $this->_init(LocationResource::class);
    }

    public function getLocationId(): ?int
    {
        $id = $this->getData(self::LOCATION_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setLocationId(int $locationId): LocationInterface
    {
        return $this->setData(self::LOCATION_ID, $locationId);
    }

    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    public function setName(string $name): LocationInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getType(): string
    {
        return (string) $this->getData(self::TYPE);
    }

    public function setType(string $type): LocationInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    public function getStreet(): ?string
    {
        $value = $this->getData(self::STREET);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setStreet(?string $street): LocationInterface
    {
        return $this->setData(self::STREET, $street);
    }

    public function getCity(): ?string
    {
        $value = $this->getData(self::CITY);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setCity(?string $city): LocationInterface
    {
        return $this->setData(self::CITY, $city);
    }

    public function getState(): string
    {
        return (string) $this->getData(self::STATE);
    }

    public function setState(string $state): LocationInterface
    {
        return $this->setData(self::STATE, $state);
    }

    public function getPostcode(): string
    {
        return (string) $this->getData(self::POSTCODE);
    }

    public function setPostcode(string $postcode): LocationInterface
    {
        return $this->setData(self::POSTCODE, $postcode);
    }

    public function getLatitude(): ?float
    {
        $value = $this->getData(self::LATITUDE);
        return $value !== null && $value !== '' ? (float) $value : null;
    }

    public function setLatitude(?float $latitude): LocationInterface
    {
        return $this->setData(self::LATITUDE, $latitude);
    }

    public function getLongitude(): ?float
    {
        $value = $this->getData(self::LONGITUDE);
        return $value !== null && $value !== '' ? (float) $value : null;
    }

    public function setLongitude(?float $longitude): LocationInterface
    {
        return $this->setData(self::LONGITUDE, $longitude);
    }

    public function getPhone(): ?string
    {
        $value = $this->getData(self::PHONE);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setPhone(?string $phone): LocationInterface
    {
        return $this->setData(self::PHONE, $phone);
    }

    public function getOpeningHours(): ?string
    {
        $value = $this->getData(self::OPENING_HOURS);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setOpeningHours(?string $openingHours): LocationInterface
    {
        return $this->setData(self::OPENING_HOURS, $openingHours);
    }

    public function getHolidayHours(): ?string
    {
        $value = $this->getData(self::HOLIDAY_HOURS);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setHolidayHours(?string $holidayHours): LocationInterface
    {
        return $this->setData(self::HOLIDAY_HOURS, $holidayHours);
    }

    public function getUrlKey(): string
    {
        return (string) $this->getData(self::URL_KEY);
    }

    public function setUrlKey(string $urlKey): LocationInterface
    {
        return $this->setData(self::URL_KEY, $urlKey);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData(self::DESCRIPTION);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setDescription(?string $description): LocationInterface
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    public function getParkingInstructions(): ?string
    {
        $value = $this->getData(self::PARKING_INSTRUCTIONS);
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setParkingInstructions(?string $parkingInstructions): LocationInterface
    {
        return $this->setData(self::PARKING_INSTRUCTIONS, $parkingInstructions);
    }

    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    public function setIsActive(bool $isActive): LocationInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    public function setSortOrder(int $sortOrder): LocationInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
    }
}
