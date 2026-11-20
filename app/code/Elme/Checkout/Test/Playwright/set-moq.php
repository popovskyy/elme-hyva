<?php

declare(strict_types=1);

// qty-rules.spec.mjs: gives a test product another MOQ, as a new value from a feed or the admin would.
// Usage, in the PHP container: php set-moq.php <sku> <moq>

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;

require '/var/www/html/app/bootstrap.php';

$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');

[, $sku, $moq] = $argv;
$stockRegistry = $om->get(StockRegistryInterface::class);
$stockItem = $stockRegistry->getStockItemBySku($sku);
$stockItem->setUseConfigMinSaleQty(false)->setMinSaleQty((float) $moq);
$stockRegistry->updateStockItemBySku($sku, $stockItem);
