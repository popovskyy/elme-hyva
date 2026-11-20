<?php

declare(strict_types=1);

namespace Elme\Search\Setup\Patch\Data;

use Elme\Search\Model\Indexer\ProductIndexer;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * elme_search_products runs "Update by Schedule" on every instance. Only then do the mview triggers exist, and
 * only they see changes that are not an admin save: stock after an order, imports, price reindex.
 * "Update on Save" (Magento's default for a new indexer) would leave those out of the index.
 */
class SetIndexerScheduled implements DataPatchInterface
{
    public function __construct(private readonly IndexerRegistry $indexerRegistry)
    {
    }

    public function apply(): self
    {
        $indexer = $this->indexerRegistry->get(ProductIndexer::INDEXER_ID);
        if (!$indexer->isScheduled()) {
            $indexer->setScheduled(true);
        }
        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
