<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Model;

use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Default customer prices: Magento's own price for the customer's group (special price, catalog rules,
 * configurable = cheapest child), ex GST. Stands in until the Pronto per-customer prices are connected.
 */
class GroupPriceProvider implements CustomerPriceProviderInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getPrices(array $productIds, int $customerId, int $customerGroupId): array
    {
        if (!$productIds) {
            return [];
        }

        $collection = $this->collectionFactory->create()
            ->addIdFilter($productIds)
            ->addAttributeToSelect(['price', 'special_price', 'special_from_date', 'special_to_date', 'tax_class_id'])
            ->addPriceData($customerGroupId, (int) $this->storeManager->getWebsite()->getId());

        $prices = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $product->setCustomerGroupId($customerGroupId);
            $priceInfo = $product->getPriceInfo();
            $final = (float) $priceInfo->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getBaseAmount();
            if ($final <= 0) {
                continue;
            }
            $regular = (float) $priceInfo->getPrice(RegularPrice::PRICE_CODE)->getAmount()->getBaseAmount();
            $prices[(int) $product->getId()] = [
                'final' => round($final, 2),
                'regular' => round(max($regular, $final), 2),
            ];
        }

        return $prices;
    }
}
