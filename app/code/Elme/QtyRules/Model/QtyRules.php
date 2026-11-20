<?php

declare(strict_types=1);

namespace Elme\QtyRules\Model;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The one place that knows which quantities of a product may be ordered, for templates, Alpine
 * steppers and the server-side check (Plugin\EnforceQtyRulesPlugin). See the README.
 */
class QtyRules implements ArgumentInterface
{
    /** A float qty this close to a whole number of steps counts as one (6.0000001 / 6). */
    private const EPSILON = 0.0001;

    /** @var array<int, int> stock id per website id */
    private array $stockIds = [];

    public function __construct(
        private readonly StockRegistryInterface $stockRegistry,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isQtyManagedType
    ) {
    }

    /**
     * Qty a stepper starts at and jumps by. Cheap and the same for every customer of a group, so it
     * is safe in block cached HTML, unlike getRules().
     *
     * @return array{min: float, step: float}
     */
    public function getMinAndStep(Product $product): array
    {
        return $this->fromStockItem(
            $this->stockRegistry->getStockItem((int) $product->getId(), (int) $product->getStore()->getWebsiteId())
        );
    }

    /**
     * min and step as in getMinAndStep(). max is the highest valid qty right now (a multiple of step), null when
     * nothing limits it. salable is false when no valid qty can be ordered: out of stock, or stock below the MOQ.
     *
     * @return array{min: float, step: float, max: float|null, salable: bool}
     */
    public function getRules(Product $product): array
    {
        $stockItem = $this->stockRegistry->getStockItem(
            (int) $product->getId(),
            (int) $product->getStore()->getWebsiteId()
        );
        ['min' => $min, 'step' => $step] = $this->fromStockItem($stockItem);
        $max = $this->getMax($product, $stockItem, $step);

        return [
            'min' => $min,
            'step' => $step,
            'max' => $max,
            'salable' => $product->isSaleable() && ($max === null || $max >= $min),
        ];
    }

    /**
     * Rules of a cart line: for a configurable, those of the simple product that was bought.
     *
     * @return array{min: float, step: float, max: float|null, salable: bool}
     */
    public function getRulesForQuoteItem(QuoteItem $item): array
    {
        return $this->getRules($this->getBoughtProduct($item));
    }

    /**
     * getRefusalMessage() for a new qty of a cart line.
     */
    public function getRefusalForQuoteItem(QuoteItem $item, float $qty): ?Phrase
    {
        $product = $this->getBoughtProduct($item);

        return $this->getRefusalMessage((int) $product->getId(), $qty, (int) $product->getStore()->getWebsiteId());
    }

    /**
     * Why this qty may not be ordered, or null when the MOQ rules allow it. Max and stock stay with Magento's own check.
     */
    public function getRefusalMessage(int $productId, float $qty, ?int $websiteId = null): ?Phrase
    {
        ['min' => $min, 'step' => $step] = $this->fromStockItem(
            $this->stockRegistry->getStockItem($productId, $websiteId)
        );

        // No MOQ, or a removed line: nothing to add to Magento's own check, which also knows decimal qty products
        if ($qty <= 0 || ($min <= 1.0 && $step <= 1.0)) {
            return null;
        }

        // Only a product with its own qty increments can have a MOQ that is not the step
        if ($qty < $min && $min !== $step) {
            return __('The minimum order for this product is %1', $this->formatQty($min));
        }

        return $qty < $min || !$this->isMultiple($qty, $step)
            ? __('This product is sold in multiples of %1', $this->formatQty($step))
            : null;
    }

    /**
     * MOQ 6 is shown as "6", not "6.0000".
     */
    public function formatQty(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    }

    private function getBoughtProduct(QuoteItem $item): Product
    {
        $simple = $item->getOptionByCode('simple_product');

        return $simple ? $simple->getProduct() : $item->getProduct();
    }

    /**
     * The MOQ is also the step, unless the product has its own "qty increments" switched on.
     *
     * @return array{min: float, step: float}
     */
    private function fromStockItem(StockItemInterface $stockItem): array
    {
        $min = max(1.0, (float) $stockItem->getMinSaleQty());
        $increments = $stockItem->getEnableQtyIncrements() ? (float) $stockItem->getQtyIncrements() : 0.0;
        $step = $increments > 0 ? $increments : $min;

        // The first qty has to be a valid step as well (e.g. MOQ 5, increments of 2 starts at 6).
        if (!$this->isMultiple($min, $step)) {
            $min = ceil($min / $step) * $step;
        }

        return ['min' => $min, 'step' => $step];
    }

    private function isMultiple(float $qty, float $step): bool
    {
        $steps = $qty / $step;

        return abs($steps - round($steps)) < self::EPSILON;
    }

    private function getMax(Product $product, StockItemInterface $stockItem, float $step): ?float
    {
        $limits = array_filter(
            [(float) $stockItem->getMaxSaleQty() ?: null, $this->getStockLimit($product, $stockItem)],
            static fn (?float $limit): bool => $limit !== null
        );
        if (!$limits) {
            return null;
        }

        return max(0.0, floor(min($limits) / $step + self::EPSILON) * $step);
    }

    /**
     * Salable qty, or null when stock does not limit this product (not managed, backorders, no qty of its own).
     */
    private function getStockLimit(Product $product, StockItemInterface $stockItem): ?float
    {
        if (!$stockItem->getManageStock()
            || $stockItem->getBackorders()
            || !$this->isQtyManagedType->execute((string) $product->getTypeId())
        ) {
            return null;
        }

        try {
            return $this->getProductSalableQty->execute((string) $product->getSku(), $this->getStockId($product));
        } catch (LocalizedException) {
            return null;
        }
    }

    private function getStockId(Product $product): int
    {
        $websiteId = (int) $product->getStore()->getWebsiteId();

        return $this->stockIds[$websiteId] ??= (int) $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, (string) $this->storeManager->getWebsite($websiteId)->getCode())
            ->getStockId();
    }
}
