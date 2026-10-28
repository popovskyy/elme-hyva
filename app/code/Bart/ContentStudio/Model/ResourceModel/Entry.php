<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ResourceModel;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Phrase;
use Magento\Store\Model\Store;

/**
 * Entry resource model.
 *
 * Store scoping deliberately skips EntityManager/MetadataPool: the entity has
 * a single-column primary key (no link-field indirection needed), so a plain
 * AbstractDb resource with a hand-rolled store pivot is simpler to read and
 * to maintain than wiring the EAV-style entity metadata plumbing for it.
 */
class Entry extends AbstractDb
{
    public const TABLE_NAME = 'bart_content_studio_entry';
    public const STORE_TABLE_NAME = 'bart_content_studio_entry_store';

    /**
     * Raised when the row moved on between load and save. Distinct from a
     * generic LocalizedException so the Save controller can tell a genuine
     * concurrent edit apart from a validation failure.
     */
    public const ERROR_STALE_VERSION = 'bart_content_studio_stale_version';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, EntryInterface::ENTRY_ID);
    }

    /**
     * @inheritDoc
     */
    protected function _beforeSave(AbstractModel $object)
    {
        if (!$object->getData(EntryInterface::STORE_IDS)) {
            $object->setData(EntryInterface::STORE_IDS, [Store::DEFAULT_STORE_ID]);
        }

        if (!$this->getIsUniqueToStores($object)) {
            throw new LocalizedException(
                __('An entry with this identifier already exists for the selected store view(s).')
            );
        }

        $object->setData(EntryInterface::VERSION, $this->nextVersion($object));

        return parent::_beforeSave($object);
    }

    /**
     * @inheritDoc
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->saveStoreRelation($object);

        return parent::_afterSave($object);
    }

    /**
     * Optimistic locking, enforced by the database rather than by a prior read.
     *
     * The controller also compares versions before it gets here, but that check
     * alone is a read-then-write: two editors saving at the same moment both
     * see the same version and both write, and the first one's changes vanish
     * silently - exactly what the feature exists to prevent. Pinning the UPDATE
     * to the version the object was loaded at, and treating "no rows changed"
     * as a conflict, closes that window for real.
     *
     * @param AbstractModel $object
     * @return void
     * @throws LocalizedException
     */
    protected function updateObject(AbstractModel $object)
    {
        $connection = $this->getConnection();
        $data = $this->prepareDataForUpdate($object);

        if (empty($data)) {
            return;
        }

        $loadedVersion = (int)$object->getOrigData(EntryInterface::VERSION);
        $condition = [
            $connection->quoteInto($this->getIdFieldName() . ' = ?', (int)$object->getId()),
            $connection->quoteInto(EntryInterface::VERSION . ' = ?', $loadedVersion),
        ];

        $affected = $connection->update($this->getMainTable(), $data, $condition);

        if ($affected === 0) {
            throw new LocalizedException($this->staleVersionMessage());
        }
    }

    /**
     * @return Phrase
     */
    private function staleVersionMessage(): Phrase
    {
        return __('Someone else saved this entry since you opened it. Please reload and reapply your changes.');
    }

    /**
     * @inheritDoc
     */
    protected function _afterLoad(AbstractModel $object)
    {
        if ($object->getId()) {
            $object->setData(EntryInterface::STORE_IDS, $this->lookupStoreIds((int)$object->getId()));
        }

        return parent::_afterLoad($object);
    }

    /**
     * @param int $entryId
     * @return int[]
     */
    public function lookupStoreIds(int $entryId): array
    {
        $select = $this->getConnection()->select()
            ->from($this->getTable(self::STORE_TABLE_NAME), 'store_id')
            ->where('entry_id = ?', $entryId);

        return array_map('intval', $this->getConnection()->fetchCol($select));
    }

    /**
     * Whether the object's identifier is free across its target store view(s).
     *
     * An entry targeting All Store Views (0) conflicts with any other entry
     * sharing the identifier, regardless of that entry's stores. An entry
     * targeting specific stores conflicts with those stores or with an
     * existing All Store Views entry for the same identifier.
     *
     * Known limitation: this is a check-then-write, so two entries created at
     * the same instant for the same identifier and overlapping stores can both
     * pass. A UNIQUE index cannot express the rule - the whole point of the
     * design is that one identifier may legitimately exist once per brand
     * (plan section 2) - and the scope column lives in a separate table, so the
     * database has nothing to enforce here. Two people creating the same
     * identifier in the same second is not a workflow this admin has, and the
     * cost of closing it (a named lock around every save) is not worth paying
     * for that. Saving an existing entry is a different matter and *is* fully
     * guarded - see updateObject().
     *
     * @param AbstractModel $object
     * @return bool
     */
    public function getIsUniqueToStores(AbstractModel $object): bool
    {
        $storeIds = $this->normalizeStoreIds((array)$object->getData(EntryInterface::STORE_IDS));

        $select = $this->getConnection()->select()
            ->from(['e' => $this->getMainTable()], ['e.entry_id'])
            ->join(['es' => $this->getTable(self::STORE_TABLE_NAME)], 'e.entry_id = es.entry_id', [])
            ->where('e.identifier = ?', (string)$object->getData(EntryInterface::IDENTIFIER));

        if (!in_array(Store::DEFAULT_STORE_ID, $storeIds, true)) {
            $select->where('es.store_id IN (?)', array_merge($storeIds, [Store::DEFAULT_STORE_ID]));
        }

        if ($object->getId()) {
            $select->where('e.entry_id <> ?', $object->getId());
        }

        return !$this->getConnection()->fetchOne($select);
    }

    /**
     * @param AbstractModel $object
     * @return void
     */
    private function saveStoreRelation(AbstractModel $object): void
    {
        $storeIds = $this->normalizeStoreIds((array)$object->getData(EntryInterface::STORE_IDS));
        $connection = $this->getConnection();
        $entryId = (int)$object->getId();

        $connection->delete($this->getTable(self::STORE_TABLE_NAME), ['entry_id = ?' => $entryId]);

        $rows = array_map(
            static fn (int $storeId): array => ['entry_id' => $entryId, 'store_id' => $storeId],
            $storeIds
        );

        if ($rows !== []) {
            $connection->insertMultiple($this->getTable(self::STORE_TABLE_NAME), $rows);
        }
    }

    /**
     * How many entries exist per content type, across every store and active
     * state - the grid's type chips read this once per page load to show a
     * count next to each one. One cheap grouped query rather than a per-type
     * COUNT, and no join, since `type` lives on the main table itself.
     *
     * @return array<string, int> Type code => count.
     */
    public function getCountsByType(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['type', 'count' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('type');

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * @param AbstractModel $object
     * @return int
     */
    private function nextVersion(AbstractModel $object): int
    {
        return ((int)$object->getOrigData(EntryInterface::VERSION)) + 1;
    }

    /**
     * @param int[] $storeIds
     * @return int[]
     */
    private function normalizeStoreIds(array $storeIds): array
    {
        $storeIds = array_unique(array_map('intval', $storeIds));

        return $storeIds !== [] ? array_values($storeIds) : [Store::DEFAULT_STORE_ID];
    }
}
