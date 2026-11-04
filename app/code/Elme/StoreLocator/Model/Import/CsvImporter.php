<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Import;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Api\Data\LocationInterfaceFactory;
use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\File\Csv;

/**
 * Upsert locations from a CSV whose header names the columns.
 *
 * Required columns: name, type, state, postcode, url_key
 * Optional: street, city, latitude, longitude, phone, opening_hours, description, is_active, sort_order
 *
 * Rows are matched on url_key (update existing, otherwise insert).
 */
class CsvImporter
{
    private const REQUIRED = ['name', 'type', 'state', 'postcode', 'url_key'];

    private const ALLOWED_TYPES = [
        LocationInterface::TYPE_SHOWROOM,
        LocationInterface::TYPE_DISTRIBUTION_PICKUP,
        LocationInterface::TYPE_TRADE_ONLY,
    ];

    public function __construct(
        private readonly Csv $csv,
        private readonly LocationInterfaceFactory $locationFactory,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * @return int Number of rows written
     * @throws LocalizedException
     */
    public function import(string $filePath, bool $replace = false): int
    {
        if (!is_readable($filePath)) {
            throw new LocalizedException(__('CSV file is not readable: %1', $filePath));
        }

        $rows = $this->csv->getData($filePath);
        if ($rows === []) {
            throw new LocalizedException(__('CSV file is empty.'));
        }

        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), array_shift($rows));
        foreach (self::REQUIRED as $column) {
            if (!in_array($column, $header, true)) {
                throw new LocalizedException(__('CSV is missing required column: %1', $column));
            }
        }

        if ($replace) {
            $existing = $this->collectionFactory->create();
            foreach ($existing as $location) {
                $this->locationRepository->delete($location);
            }
        }

        $byUrlKey = [];
        foreach ($this->collectionFactory->create() as $location) {
            $byUrlKey[$location->getUrlKey()] = $location;
        }

        $count = 0;
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }
            $data = [];
            foreach ($header as $i => $column) {
                $data[$column] = isset($row[$i]) ? trim((string) $row[$i]) : '';
            }

            $line = $index + 2;
            $this->assertRow($data, $line);

            $urlKey = $data['url_key'];
            $location = $byUrlKey[$urlKey] ?? $this->locationFactory->create();
            $this->hydrate($location, $data);
            $this->locationRepository->save($location);
            $byUrlKey[$urlKey] = $location;
            $count++;
        }

        return $count;
    }

    /**
     * @param array<string, string> $data
     */
    private function hydrate(LocationInterface $location, array $data): void
    {
        $location->setName($data['name']);
        $location->setType($data['type']);
        $location->setState(strtoupper($data['state']));
        $location->setPostcode($data['postcode']);
        $location->setUrlKey($data['url_key']);
        $location->setStreet($data['street'] !== '' ? $data['street'] : null);
        $location->setCity($data['city'] !== '' ? $data['city'] : null);
        $location->setPhone($data['phone'] !== '' ? $data['phone'] : null);
        $location->setOpeningHours($data['opening_hours'] !== '' ? $data['opening_hours'] : null);
        $location->setDescription($data['description'] !== '' ? $data['description'] : null);
        $location->setLatitude($data['latitude'] !== '' ? (float) $data['latitude'] : null);
        $location->setLongitude($data['longitude'] !== '' ? (float) $data['longitude'] : null);
        $location->setIsActive(($data['is_active'] ?? '1') === '' || (int) ($data['is_active'] ?? '1') === 1);
        $location->setSortOrder((int) ($data['sort_order'] ?? 0));
    }

    /**
     * @param array<string, string> $data
     * @throws LocalizedException
     */
    private function assertRow(array $data, int $line): void
    {
        foreach (self::REQUIRED as $column) {
            if (($data[$column] ?? '') === '') {
                throw new LocalizedException(__('CSV line %1: column %2 is required.', $line, $column));
            }
        }
        if (!in_array($data['type'], self::ALLOWED_TYPES, true)) {
            throw new LocalizedException(__(
                'CSV line %1: type must be one of %2.',
                $line,
                implode(', ', self::ALLOWED_TYPES)
            ));
        }
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
