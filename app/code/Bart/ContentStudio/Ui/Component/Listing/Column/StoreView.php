<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Ui\Component\Listing\Column;

use Bart\ContentStudio\Model\ResourceModel\Entry as EntryResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the store view(s) each entry is assigned to. Display-only: no
 * filter/sort, since store assignment lives in a pivot table rather than a
 * column on the main grid select.
 */
class StoreView extends Column
{
    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param array<mixed> $components
     * @param array<mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<mixed> $dataSource
     * @return array<mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $storeIdsByEntry = $this->fetchStoreIdsByEntry($this->collectEntryIds($dataSource['data']['items']));
        $name = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $item[$name] = $this->formatLabel($storeIdsByEntry[(int)($item['entry_id'] ?? 0)] ?? []);
        }

        return $dataSource;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return int[]
     */
    private function collectEntryIds(array $items): array
    {
        return array_map(static fn (array $item): int => (int)($item['entry_id'] ?? 0), $items);
    }

    /**
     * @param int[] $entryIds
     * @return array<int, int[]>
     */
    private function fetchStoreIdsByEntry(array $entryIds): array
    {
        $entryIds = array_filter($entryIds);

        if ($entryIds === []) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(EntryResource::STORE_TABLE_NAME))
            ->where('entry_id IN (?)', $entryIds);

        $map = [];

        foreach ($connection->fetchAll($select) as $row) {
            $map[(int)$row['entry_id']][] = (int)$row['store_id'];
        }

        return $map;
    }

    /**
     * @param int[] $storeIds
     * @return string
     */
    private function formatLabel(array $storeIds): string
    {
        if ($storeIds === [] || in_array(Store::DEFAULT_STORE_ID, $storeIds, true)) {
            return __('All Store Views')->render();
        }

        $names = array_map(
            fn (int $storeId): string => $this->resolveStoreName($storeId),
            $storeIds
        );

        return implode(', ', $names);
    }

    /**
     * @param int $storeId
     * @return string
     */
    private function resolveStoreName(int $storeId): string
    {
        try {
            return $this->storeManager->getStore($storeId)->getName();
        } catch (\Exception $e) {
            return (string)$storeId;
        }
    }
}
