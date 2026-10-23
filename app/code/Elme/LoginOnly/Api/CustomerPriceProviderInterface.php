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
     * @return array<int, array{final: float, regular: float}> Ex GST. Products without a price are left out.
     */
    public function getPrices(array $productIds, int $customerId, int $customerGroupId): array;
}
