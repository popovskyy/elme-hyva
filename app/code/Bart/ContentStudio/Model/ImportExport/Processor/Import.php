<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\Processor;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Bart\ContentStudio\Model\OptionSource\ImportMode;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Bart\ContentStudio\Model\Schema\SchemaValidator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Imports entry payloads produced by Processor\Export.
 *
 * A payload's identifier is matched against an *exact* store-id set, not
 * just the identifier - the whole point of dropping the unique(identifier)
 * constraint (see plan section 2) was letting the same identifier carry different
 * content per brand, and import has to respect that or two brands' exports
 * would collide into one entry.
 */
class Import
{
    /**
     * @param EntryFactory $entryFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param CollectionFactory $collectionFactory
     * @param SchemaValidator $validator
     * @param SchemaConfig $schemaConfig
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly SchemaValidator $validator,
        private readonly SchemaConfig $schemaConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Imports every payload in a single transaction. A batch with even one
     * invalid entry commits nothing at all - every row still gets a report
     * line, so the admin can see exactly what to fix, but the database is
     * left untouched until every entry in the batch is valid (plan section 9: "a
     * partially corrupted ZIP won't leave the catalog half-imported").
     *
     * @param array<int, array<string, mixed>> $payloads
     * @param string $mode
     * @param bool $dryRun
     * @return array<int, array<string, string>> One row per payload: {identifier, status, message}.
     * @throws CouldNotSaveException
     */
    public function importBatch(array $payloads, string $mode = ImportMode::MODE_UPDATE, bool $dryRun = false): array
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        $report = [];
        $hasErrors = false;

        try {
            foreach ($payloads as $payload) {
                $row = $this->importOne($payload, $mode, $dryRun || $hasErrors);

                $hasErrors = $hasErrors || $row['status'] === 'error';
                $report[] = $row;
            }
        } catch (\Exception $e) {
            $connection->rollBack();

            throw new CouldNotSaveException(__('Could not import: %1', $e->getMessage()), $e);
        }

        if ($dryRun || $hasErrors) {
            $connection->rollBack();
        } else {
            $connection->commit();
        }

        return $report;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string $mode
     * @param bool $skipWrite - True for a dry run, or once an earlier payload in the same batch has failed.
     * @return array<string, string>
     */
    private function importOne(array $payload, string $mode, bool $skipWrite): array
    {
        $identifier = trim((string)($payload['entry']['identifier'] ?? ''));
        $typeCode = (string)($payload['entry']['type'] ?? '');

        if ($identifier === '') {
            return $this->row('(missing identifier)', 'error', 'Entry identifier is required.');
        }

        if (!$this->schemaConfig->hasType($typeCode)) {
            return $this->row($identifier, 'error', "Content type \"{$typeCode}\" is not registered here.");
        }

        $warnings = [];
        $storeIds = $this->resolveStoreIds((array)($payload['stores'] ?? []), $warnings);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $normalized = $this->validator->normalize($typeCode, $data);
        $errors = $this->validator->validate($typeCode, $normalized);

        if ($errors !== []) {
            return $this->row($identifier, 'error', implode('; ', $errors));
        }

        $existing = $this->findExisting($identifier, $storeIds);

        if ($existing && $mode === ImportMode::MODE_SKIP) {
            return $this->row($identifier, 'skipped', 'Already exists.');
        }

        if (!$skipWrite) {
            $this->save($existing ?? $this->entryFactory->create(), $payload, $typeCode, $normalized, $storeIds);
        }

        $status = $existing ? 'updated' : 'created';
        $message = implode(' ', $warnings);

        return $this->row($identifier, $status, $message);
    }

    /**
     * @param EntryInterface $entry
     * @param array<string, mixed> $payload
     * @param string $typeCode
     * @param array<string, mixed> $normalizedData
     * @param int[] $storeIds
     * @return void
     */
    private function save(
        EntryInterface $entry,
        array $payload,
        string $typeCode,
        array $normalizedData,
        array $storeIds
    ): void {
        $entry->setType($typeCode);
        $entry->setIdentifier(trim((string)$payload['entry']['identifier']));
        $entry->setTitle($payload['entry']['title'] ?? null);
        $entry->setIsActive((bool)($payload['entry']['is_active'] ?? true));
        $entry->setStoreIds($storeIds);
        $entry->setEntryData($normalizedData);
        $this->entryRepository->save($entry);
    }

    /**
     * @param string $identifier
     * @param int[] $storeIds
     * @return EntryInterface|null
     */
    private function findExisting(string $identifier, array $storeIds): ?EntryInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addIdentifierFilter($identifier);

        $target = $this->normalizeStoreSet($storeIds);

        foreach ($collection as $candidate) {
            if ($this->normalizeStoreSet($candidate->getStoreIds()) === $target) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param int[] $storeIds
     * @return int[]
     */
    private function normalizeStoreSet(array $storeIds): array
    {
        $unique = array_values(array_unique(array_map('intval', $storeIds)));
        sort($unique);

        return $unique;
    }

    /**
     * @param string[] $codes
     * @param string[] $warnings
     * @return int[]
     */
    private function resolveStoreIds(array $codes, array &$warnings): array
    {
        if ($codes === []) {
            return [Store::DEFAULT_STORE_ID];
        }

        $ids = [];

        foreach ($codes as $code) {
            $id = $this->resolveStoreId((string)$code);

            if ($id !== null) {
                $ids[] = $id;
            } else {
                $warnings[] = "Unknown store code \"{$code}\", skipped.";
            }
        }

        if ($ids === []) {
            $warnings[] = 'No store codes resolved - assigned to All Store Views.';

            return [Store::DEFAULT_STORE_ID];
        }

        return $ids;
    }

    /**
     * @param string $code
     * @return int|null
     */
    private function resolveStoreId(string $code): ?int
    {
        try {
            return (int)$this->storeManager->getStore($code)->getId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @param string $identifier
     * @param string $status
     * @param string $message
     * @return array<string, string>
     */
    private function row(string $identifier, string $status, string $message): array
    {
        return ['identifier' => $identifier, 'status' => $status, 'message' => $message];
    }
}
