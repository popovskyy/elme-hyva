<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-19 product card (Magento_Catalog::product/list/item.phtml): badge, stock label, qty rules.
 *
 * The tile HTML is block cached per customer group and login state (Hyvä ProductListItem +
 * Elme\LoginOnly\Plugin\ProductListItemCacheKeyPlugin), so nothing here may depend on the customer
 * beyond the group. The customer's own price loads after render (FE-53).
 */
class ProductTile implements ArgumentInterface
{
    /** "In Stock" from this salable qty, "Low Stock" below it, nothing at 0 (elme-business: Stock). */
    public const IN_STOCK_FROM = 10;

    public const BADGE_SALE = 'sale';
    public const BADGE_NEW = 'new';

    public const STOCK_IN = 'in';
    public const STOCK_LOW = 'low';

    public const XML_PATH_QUICK_ORDER = 'elme_catalog/plp/quick_order';

    /** @var array<string, int> stock id per website code */
    private array $stockIds = [];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isQtyManagedType
    ) {
    }

    /**
     * "Quick order on PLP": logged-in customers get a qty stepper next to Add to Cart.
     */
    public function isQuickOrderEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_QUICK_ORDER, ScopeInterface::SCOPE_STORE);
    }

    /**
     * One badge per tile, Sale before New (Sale says more to a buyer).
     */
    public function getBadge(Product $product): ?string
    {
        if ($this->isOnSale($product)) {
            return self::BADGE_SALE;
        }

        return $this->isNew($product) ? self::BADGE_NEW : null;
    }

    public function isOnSale(Product $product): bool
    {
        $special = $product->getData('special_price');
        if ($special === null || $special === '' || $product->getTypeId() === 'bundle') {
            return false;
        }

        $regular = (float) $product->getData('price');
        if ($regular > 0 && (float) $special >= $regular) {
            return false;
        }

        return $this->timezone->isScopeDateInInterval(
            $product->getStore(),
            $product->getData('special_from_date'),
            $product->getData('special_to_date')
        );
    }

    public function isNew(Product $product): bool
    {
        $from = $product->getData('news_from_date');
        $to = $product->getData('news_to_date');
        if (!$from && !$to) {
            return false;
        }

        return $this->timezone->isScopeDateInInterval($product->getStore(), $from, $to);
    }

    /**
     * STOCK_IN, STOCK_LOW or null (no label: out of stock, a type without its own qty, or unknown).
     */
    public function getStockLevel(Product $product): ?string
    {
        if (!$product->isSaleable() || !$this->isQtyManagedType->execute((string) $product->getTypeId())) {
            return null;
        }

        try {
            $qty = $this->getProductSalableQty->execute((string) $product->getSku(), $this->getStockId());
        } catch (\Throwable) {
            return null;
        }

        return self::stockLevelForQty($qty);
    }

    public static function stockLevelForQty(float $qty): ?string
    {
        if ($qty <= 0) {
            return null;
        }

        return $qty >= self::IN_STOCK_FROM ? self::STOCK_IN : self::STOCK_LOW;
    }

    /**
     * Qty the stepper starts at and jumps by: MOQ (min sale qty) and its multiples, or the
     * "qty increments" of the stock item when those are switched on.
     *
     * @return array{min: float, step: float}
     */
    public function getQtyRules(Product $product): array
    {
        try {
            $item = $this->stockRegistry->getStockItem(
                (int) $product->getId(),
                (int) $this->storeManager->getWebsite()->getId()
            );
        } catch (\Throwable) {
            return self::qtyRules(1.0, false, 1.0);
        }

        return self::qtyRules(
            (float) $item->getMinSaleQty(),
            (bool) $item->getEnableQtyIncrements(),
            (float) $item->getQtyIncrements()
        );
    }

    /**
     * @return array{min: float, step: float}
     */
    public static function qtyRules(float $minSaleQty, bool $useIncrements, float $increments): array
    {
        $min = max(1.0, $minSaleQty);
        $step = $useIncrements && $increments > 0 ? $increments : $min;

        // The first qty has to be a valid step as well (e.g. MOQ 5, increments of 2 starts at 6).
        if (fmod($min, $step) > 0) {
            $min = ceil($min / $step) * $step;
        }

        return ['min' => $min, 'step' => $step];
    }

    private function getStockId(): int
    {
        $websiteCode = (string) $this->storeManager->getWebsite()->getCode();

        return $this->stockIds[$websiteCode] ??= (int) $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)
            ->getStockId();
    }
}
