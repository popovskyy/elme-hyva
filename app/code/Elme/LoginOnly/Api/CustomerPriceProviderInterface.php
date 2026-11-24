<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Api;

/**
 * Prices of products for one logged-in customer.
 *
 * Elme prices are per customer (Pronto price lists), so they never go into full page cache HTML. The storefront
 * renders a placeholder and asks /customer-price/prices for the ids on the page. Swap the implementation with a
 * di.xml preference when the Pronto price service lands; the cart must use the same prices.
 */
interface CustomerPriceProviderInterface
{
    /**
     * @param int[] $productIds
     * @return array<int, array{final: float, regular: float, promo: bool, list: ?float, trade: ?float, was: ?float}>
     *         Ex GST. Products without a price are left out. Every key is always present, null where empty.
     *         - `final` is what the customer pays (the cart uses it).
     *         - `list` is the price list price (RRP); null hides the "List price" line.
     *         - `trade` is the customer's agreed everyday price (contract, A1, marketing, group, region), only when it
     *           is under `list`; null when the customer has none.
     *         - `was` is the price struck through next to `final`: `trade`, else `list`, and only when `final` is
     *           below it; null when nothing is on sale.
     *         - `regular` and `promo` are the older pair: `promo` is true only when a list/region (or Magento
     *           special) promo wins, so contract prices do not show was/now for every A1 customer.
     */
    public function getPrices(array $productIds, int $customerId, int $customerGroupId): array;
}
