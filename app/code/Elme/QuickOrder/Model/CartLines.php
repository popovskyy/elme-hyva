<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Elme\QtyRules\Model\QtyRules;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\Phrase;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The one place that reads the live data of cart rows: product, customer price (CustomerPriceProviderInterface),
 * stock, MOQ rules (Elme_QtyRules), and the reason a row cannot be ordered right now. Add to cart, cart save,
 * the cart answer and the checkout quote rebuild all ask here, so they always agree.
 */
class CartLines
{
    private const EPSILON = 0.0001;

    /** Types a row can hold: one product with its own qty. A configurable row holds the chosen simple product. */
    private const ROW_TYPES = [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL];

    private const ATTRIBUTES = ['name', 'small_image', 'thumbnail', 'url_key', 'visibility', 'status', 'tax_class_id'];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConfigurableResource $configurableResource,
        private readonly CustomerPriceProviderInterface $priceProvider,
        private readonly QtyRules $qtyRules,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly IsProductSalableInterface $isProductSalable,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteId,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array<string, float> $rows sku => qty
     * @return array<string, CartLine> keyed by sku, in the order of $rows
     */
    public function getLines(array $rows, int $customerId, int $customerGroupId): array
    {
        if (!$rows) {
            return [];
        }

        $products = $this->loadProducts(['sku' => ['in' => array_map('strval', array_keys($rows))]]);
        $bySku = [];
        foreach ($products as $product) {
            $bySku[(string) $product->getSku()] = $product;
        }
        $parents = $this->loadParents($bySku);
        $prices = $this->priceProvider->getPrices(
            array_map(static fn (Product $product): int => (int) $product->getId(), array_values($bySku)),
            $customerId,
            $customerGroupId
        );

        $lines = [];
        foreach ($rows as $sku => $qty) {
            $sku = (string) $sku;
            $product = $bySku[$sku] ?? null;
            $lines[$sku] = $product
                ? $this->buildLine($sku, (float) $qty, $product, $parents[(int) $product->getId()] ?? null, $prices)
                : new CartLine($sku, (float) $qty, refusal: __('This product is no longer available.'));
        }

        return $lines;
    }

    /**
     * Why this qty of the product cannot go into the cart, or null when it can.
     */
    public function getRefusal(string $sku, float $qty, int $customerId, int $customerGroupId): ?Phrase
    {
        return $this->getLines([$sku => $qty], $customerId, $customerGroupId)[$sku]->refusal;
    }

    /**
     * @param array<int, float> $prices
     */
    private function buildLine(string $sku, float $qty, Product $product, ?Product $parent, array $prices): CartLine
    {
        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            return new CartLine($sku, $qty, $product, $parent, refusal: __('This product is no longer available.'));
        }
        if (!in_array($product->getTypeId(), self::ROW_TYPES, true)) {
            return new CartLine($sku, $qty, $product, $parent, refusal: $product->getTypeId() === Configurable::TYPE_CODE
                ? __('Please choose the product options.')
                : __('This product cannot be ordered online.'));
        }

        $price = $prices[(int) $product->getId()] ?? null;
        if ($price === null) {
            return new CartLine($sku, $qty, $product, $parent, refusal: __('This product cannot be ordered online.'));
        }

        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();
        $stockId = (int) $this->stockByWebsiteId->execute($websiteId)->getStockId();
        // QtyRules reads isSaleable(); a collection product does not know its MSI state
        $product->setData('is_salable', $this->isProductSalable->execute($sku, $stockId));
        $rules = $this->qtyRules->getRules($product);
        $salableQty = $this->getSalableQty($product, $stockId, $websiteId);

        return new CartLine(
            $sku,
            $qty,
            $product,
            $parent,
            $price,
            $rules,
            $salableQty,
            $this->getQtyRefusal($product, $qty, $rules, $websiteId)
        );
    }

    /**
     * @param array{min: float, step: float, max: float|null, salable: bool} $rules
     */
    private function getQtyRefusal(Product $product, float $qty, array $rules, int $websiteId): ?Phrase
    {
        if (!$rules['salable']) {
            return __('This product is out of stock.');
        }

        $moq = $this->qtyRules->getRefusalMessage((int) $product->getId(), $qty, $websiteId);
        if ($moq !== null) {
            return $moq;
        }

        if ($rules['max'] !== null && $qty > $rules['max'] + self::EPSILON) {
            return __('Only %1 available. Please lower the quantity.', $this->qtyRules->formatQty($rules['max']));
        }

        return null;
    }

    /**
     * What "N in stock" shows, null when stock does not limit the product.
     */
    private function getSalableQty(Product $product, int $stockId, int $websiteId): ?float
    {
        $stockItem = $this->stockRegistry->getStockItem((int) $product->getId(), $websiteId);
        if (!$stockItem->getManageStock() || $stockItem->getBackorders()) {
            return null;
        }

        try {
            return max(0.0, $this->getProductSalableQty->execute((string) $product->getSku(), $stockId));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Configurable parents of rows that cannot be shown on their own: their page, name and image stand in.
     *
     * @param array<string, Product> $products
     * @return array<int, Product> parent per child id
     */
    private function loadParents(array $products): array
    {
        $parentIdByChild = [];
        foreach ($products as $product) {
            if ((int) $product->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE) {
                continue;
            }
            $parentIds = $this->configurableResource->getParentIdsByChild((int) $product->getId());
            if ($parentIds) {
                $parentIdByChild[(int) $product->getId()] = (int) reset($parentIds);
            }
        }
        if (!$parentIdByChild) {
            return [];
        }

        $parents = [];
        foreach ($this->loadProducts(['entity_id' => ['in' => array_unique($parentIdByChild)]]) as $parent) {
            $parents[(int) $parent->getId()] = $parent;
        }

        $result = [];
        foreach ($parentIdByChild as $childId => $parentId) {
            if (isset($parents[$parentId])) {
                $result[$childId] = $parents[$parentId];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $filter
     * @return Product[]
     */
    private function loadProducts(array $filter): array
    {
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect(self::ATTRIBUTES)
            ->addWebsiteFilter()
            ->addUrlRewrite();
        foreach ($filter as $field => $condition) {
            $collection->addAttributeToFilter($field, $condition);
        }

        return $collection->getItems();
    }
}
