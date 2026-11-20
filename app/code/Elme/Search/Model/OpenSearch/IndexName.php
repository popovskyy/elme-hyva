<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Model\Config;

/**
 * elme_products_<store> is an alias; a full reindex builds elme_products_<store>_v<time> and swaps the alias.
 */
class IndexName
{
    public function __construct(private readonly Config $config)
    {
    }

    public function getAlias(int $storeId): string
    {
        return $this->config->getIndexPrefix() . '_' . $storeId;
    }

    public function newVersion(int $storeId): string
    {
        return $this->getAlias($storeId) . '_v' . date('YmdHis') . substr((string)hrtime(true), -4);
    }
}
