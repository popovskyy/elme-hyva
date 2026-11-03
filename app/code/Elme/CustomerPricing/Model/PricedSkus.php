<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;

/**
 * The SKUs that price a product: its own, or for a configurable the SKUs of its enabled simple products.
 */
class PricedSkus
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConfigurableResource $configurableResource
    ) {
    }

    /**
     * @param int[] $productIds
     * @return array<int, array<int, string>> product id => [simple product id => SKU]; unknown ids are left out
     */
    public function get(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $result = [];
        $childrenOf = [];
        /** @var Product $product */
        foreach ($this->collectionFactory->create()->addIdFilter($productIds) as $product) {
            $id = (int) $product->getId();
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                $childrenOf[$id] = array_map('intval', $this->configurableResource->getChildrenIds($id)[0] ?? []);
                $result[$id] = [];
                continue;
            }
            $result[$id] = [$id => (string) $product->getSku()];
        }

        $childIds = array_values(array_unique(array_merge([], ...array_values($childrenOf))));
        if ($childIds) {
            $childSkus = [];
            $children = $this->collectionFactory->create()
                ->addIdFilter($childIds)
                ->addAttributeToFilter('status', Status::STATUS_ENABLED);
            foreach ($children as $child) {
                $childSkus[(int) $child->getId()] = (string) $child->getSku();
            }
            foreach ($childrenOf as $parentId => $ids) {
                $result[$parentId] = array_intersect_key($childSkus, array_flip($ids));
            }
        }

        return $result;
    }
}
