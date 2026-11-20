<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Observer;

use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

/**
 * sales_quote_collect_totals_before (every area): every line of a customer's active cart gets the customer price as
 * its custom price, so the cart costs what the price endpoint shows. It runs on each totals collection, so add to
 * cart, qty changes, reorder, Quick Order and checkout all reprice from the current Pronto mappings, and so does a cart
 * flagged by Model\QuoteRecollect wherever it is loaded next (storefront, admin, cron).
 *
 * A line without a customer price drops the custom price and falls back to Magento's price. Inactive quotes are left
 * alone: admin order creation works on one, so an admin custom price is kept there.
 */
class ApplyCustomerPrices implements ObserverInterface
{
    private const PRICED_TYPES = [Type::TYPE_SIMPLE, Type::TYPE_VIRTUAL, Configurable::TYPE_CODE];

    public function __construct(
        private readonly CustomerPriceProviderInterface $priceProvider
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getEvent()->getData('quote');
        $customerId = (int) $quote->getCustomerId();
        if (!$customerId || !$quote->getIsActive()) {
            return;
        }

        $lines = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            if (!$item->isDeleted() && in_array($item->getProductType(), self::PRICED_TYPES, true)) {
                $lines[] = [$item, $this->getPricedProductId($item)];
            }
        }
        if (!$lines) {
            return;
        }

        $prices = $this->priceProvider->getPrices(
            array_column($lines, 1),
            $customerId,
            (int) $quote->getCustomerGroupId()
        );

        foreach ($lines as [$item, $productId]) {
            $this->setPrice($item, $prices[$productId]['final'] ?? null);
        }
    }

    /**
     * A configurable line is priced by the simple product chosen.
     */
    private function getPricedProductId(Item $item): int
    {
        $simple = $item->getOptionByCode('simple_product');

        return (int) ($simple?->getProduct()?->getId() ?: $simple?->getValue() ?: $item->getProduct()->getId());
    }

    private function setPrice(Item $item, ?float $price): void
    {
        $current = $item->getCustomPrice();
        $original = $item->getOriginalCustomPrice();
        if ($price === null
            ? $current === null && $original === null
            : $current !== null && (float) $current === $price && (float) $original === $price
        ) {
            return;
        }

        // Null, not unsetData(): hasCustomPrice() is isset(), and null clears the columns on save.
        $item->setCustomPrice($price)->setOriginalCustomPrice($price);
        $item->unsetData('calculation_price')->unsetData('base_calculation_price');
    }
}
