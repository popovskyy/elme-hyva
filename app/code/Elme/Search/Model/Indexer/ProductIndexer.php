<?php

declare(strict_types=1);

namespace Elme\Search\Model\Indexer;

use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Indexer elme_search_products: full, by ids (update on save) and mview (update by schedule).
 */
class ProductIndexer implements IndexerActionInterface, MviewActionInterface
{
    public const INDEXER_ID = 'elme_search_products';

    public function __construct(
        private readonly DocumentBuilder $documentBuilder,
        private readonly IndexWriter $indexWriter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function executeFull()
    {
        foreach ($this->getStoreIds() as $storeId) {
            $this->rebuildStore($storeId);
        }
    }

    public function rebuildStore(int $storeId): int
    {
        return $this->indexWriter->rebuild($storeId, $this->documentBuilder->build($storeId));
    }

    public function executeList(array $ids)
    {
        $this->reindexIds($ids);
    }

    public function executeRow($id)
    {
        $this->reindexIds([$id]);
    }

    /**
     * Mview
     */
    public function execute($ids)
    {
        $this->reindexIds((array)$ids);
    }

    /**
     * @param int[] $ids
     */
    public function reindexIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return;
        }
        foreach ($this->getStoreIds() as $storeId) {
            $documents = [];
            foreach ($this->documentBuilder->build($storeId, $ids) as $batch) {
                $documents += $batch;
            }
            $this->indexWriter->update($storeId, $documents, array_values(array_diff($ids, array_keys($documents))));
        }
    }

    /**
     * @return int[] active store views (one index each)
     */
    public function getStoreIds(): array
    {
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $ids[] = (int)$store->getId();
            }
        }
        return $ids;
    }
}
