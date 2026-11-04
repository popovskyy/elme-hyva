<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Api\Data;

interface LocationInterface
{
    public const LOCATION_ID = 'location_id';
    public const NAME = 'name';
    public const TYPE = 'type';
    public const STREET = 'street';
    public const CITY = 'city';
    public const STATE = 'state';
    public const POSTCODE = 'postcode';
    public const LATITUDE = 'latitude';
    public const LONGITUDE = 'longitude';
    public const PHONE = 'phone';
    public const OPENING_HOURS = 'opening_hours';
    public const URL_KEY = 'url_key';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const SORT_ORDER = 'sort_order';

    public const TYPE_SHOWROOM = 'showroom';
    public const TYPE_DISTRIBUTION_PICKUP = 'distribution_pickup';
    public const TYPE_TRADE_ONLY = 'trade_only';

    public function getLocationId(): ?int;

    public function setLocationId(int $locationId): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getType(): string;

    public function setType(string $type): self;

    public function getStreet(): ?string;

    public function setStreet(?string $street): self;

    public function getCity(): ?string;

    public function setCity(?string $city): self;

    public function getState(): string;

    public function setState(string $state): self;

    public function getPostcode(): string;

    public function setPostcode(string $postcode): self;

    public function getLatitude(): ?float;

    public function setLatitude(?float $latitude): self;

    public function getLongitude(): ?float;

    public function setLongitude(?float $longitude): self;

    public function getPhone(): ?string;

    public function setPhone(?string $phone): self;

    public function getOpeningHours(): ?string;

    public function setOpeningHours(?string $openingHours): self;

    public function getUrlKey(): string;

    public function setUrlKey(string $urlKey): self;

    public function getDescription(): ?string;

    public function setDescription(?string $description): self;

    public function getIsActive(): bool;

    public function setIsActive(bool $isActive): self;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): self;
}
