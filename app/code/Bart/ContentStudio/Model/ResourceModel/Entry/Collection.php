<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ResourceModel\Entry;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Model\Entry as EntryModel;
use Bart\ContentStudio\Model\ResourceModel\Entry as EntryResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\Store;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entry_id';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(EntryModel::class, EntryResource::class);
    }

    /**
     * Collection loading bypasses AbstractDb::load()/_afterLoad(), so unlike
     * a single entry fetched via EntryRepository::getById(), items here
     * would otherwise never get their store_ids populated. Batched into one
     * query instead of one per item.
     *
     * @inheritDoc
     */
    protected function _afterLoad()
    {
        $entryIds = array_map(static fn (EntryModel $item): int => (int)$item->getId(), $this->getItems());

        if ($entryIds !== []) {
            $storeIdsByEntry = $this->fetchStoreIdsByEntry($entryIds);

            foreach ($this->getItems() as $item) {
                $item->setData(EntryInterface::STORE_IDS, $storeIdsByEntry[(int)$item->getId()] ?? []);
            }
        }

        return parent::_afterLoad();
    }

    /**
     * @param int[] $entryIds
     * @return array<int, int[]>
     */
    private function fetchStoreIdsByEntry(array $entryIds): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(EntryResource::STORE_TABLE_NAME))
            ->where('entry_id IN (?)', $entryIds);

        $map = [];

        foreach ($connection->fetchAll($select) as $row) {
            $map[(int)$row['entry_id']][] = (int)$row['store_id'];
        }

        return $map;
    }

    /**
     * Store ids the current filter was built from, reused when ranking.
     *
     * @var int[]|null
     */
    private ?array $storeFilterIds = null;

    /**
     * Restricts to entries visible in the given store(s), All Store Views
     * included by default.
     *
     * EXISTS rather than a JOIN. The pivot holds one row per store, so joining
     * multiplies each entry and needs a GROUP BY to collapse again - and under
     * ONLY_FULL_GROUP_BY (off here, on by default on Adobe Commerce Cloud and
     * others) "SELECT main_table.* ... GROUP BY entry_id" is rejected outright on
     * MariaDB, which does not infer functional dependency from the primary key
     * the way MySQL does. A semi-join sidesteps the whole problem: no row
     * multiplication, so nothing to collapse. It also makes getSize() honest,
     * which a grouped select would not have been, and makes repeat calls safe
     * - each simply ANDs another condition instead of colliding on the alias.
     *
     * @param int|int[] $storeId
     * @param bool $withAdmin
     * @return $this
     */
    public function addStoreFilter($storeId, bool $withAdmin = true): self
    {
        $storeIds = is_array($storeId) ? $storeId : [$storeId];

        if ($withAdmin) {
            $storeIds[] = Store::DEFAULT_STORE_ID;
        }

        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        $this->storeFilterIds = $storeIds;

        $exists = $this->getConnection()->select()
            ->from(['cs_store_filter' => $this->getTable(EntryResource::STORE_TABLE_NAME)], [new \Zend_Db_Expr('1')])
            ->where('cs_store_filter.entry_id = main_table.entry_id')
            ->where('cs_store_filter.store_id IN (?)', $storeIds);

        $this->getSelect()->where('EXISTS (?)', new \Zend_Db_Expr($exists->assemble()));

        return $this;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function addIdentifierFilter(string $identifier): self
    {
        $this->addFieldToFilter('main_table.identifier', $identifier);

        return $this;
    }

    /**
     * Orders results so a store-specific match outranks an All Store Views
     * match for the same identifier (specific store ids are always > 0).
     *
     * A correlated MAX() keeps this independent of how the rows were filtered,
     * so the select stays free of GROUP BY - see addStoreFilter(). An entry
     * attached to both its own store and All Store Views ranks by its most
     * specific match, which is the behaviour callers expect.
     *
     * @return $this
     */
    public function orderByStoreSpecificity(): self
    {
        $rank = $this->getConnection()->select()
            ->from(
                ['cs_store_rank' => $this->getTable(EntryResource::STORE_TABLE_NAME)],
                [new \Zend_Db_Expr('MAX(cs_store_rank.store_id)')]
            )
            ->where('cs_store_rank.entry_id = main_table.entry_id');

        if ($this->storeFilterIds !== null) {
            $rank->where('cs_store_rank.store_id IN (?)', $this->storeFilterIds);
        }

        $this->getSelect()->order(new \Zend_Db_Expr('(' . $rank->assemble() . ') DESC'));

        return $this;
    }
}
