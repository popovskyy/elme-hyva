<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Model\Indexer\ProductIndexer;
use Magento\Catalog\Model\Indexer\Category\Product as CategoryProductIndexer;
use Magento\Framework\Indexer\IndexerRegistry;
use Psr\Log\LoggerInterface;

/**
 * A category was moved, enabled / disabled or its anchor flag changed: Magento's "Category Products" indexer
 * rebuilds catalog_category_product_index for those categories, and with it the category ids and positions of
 * products that did not change themselves. Our mview only follows product tables, so it never hears of it.
 *
 * After Magento's indexer has run for categories (on save or from its mview), mark ours invalid: the cron
 * indexer_reindex_all_invalid rebuilds it (new index + alias swap), by then from the updated category index.
 * A full Magento reindex needs nothing here: Magento invalidates dependent indexers itself.
 */
class InvalidateOnCategoryReindex
{
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Mview (Update by Schedule)
     */
    public function afterExecute(CategoryProductIndexer $subject, $result)
    {
        $this->invalidate();
        return $result;
    }

    public function afterExecuteList(CategoryProductIndexer $subject, $result)
    {
        $this->invalidate();
        return $result;
    }

    public function afterExecuteRow(CategoryProductIndexer $subject, $result)
    {
        $this->invalidate();
        return $result;
    }

    private function invalidate(): void
    {
        try {
            $indexer = $this->indexerRegistry->get(ProductIndexer::INDEXER_ID);
            if (!$indexer->isInvalid()) {
                $indexer->invalidate();
            }
        } catch (\Throwable $e) {
            // saving a category must not fail because of the search index
            $this->logger->error(
                'Elme_Search: could not invalidate the search index after a category change: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
