<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Magento\Framework\Phrase;

/**
 * Every change of the cart goes through here: add to cart and the cart save. A new or changed qty is checked by
 * CartLines first (MOQ, stock, price); a refused row keeps the qty it had. The quote is never touched.
 */
class CartEditor
{
    public const MODE_REPLACE = 'replace';
    public const MODE_MERGE = 'merge';

    private const EPSILON = 0.0001;

    public function __construct(
        private readonly CartList $cartList,
        private readonly CartLines $cartLines
    ) {
    }

    /**
     * Adds $qty to the row of $sku (a new row when there is none). The new total qty has to be valid.
     *
     * @return Phrase|null Why nothing was added, null when it was
     */
    public function add(int $customerId, int $customerGroupId, string $sku, float $qty): ?Phrase
    {
        if ($qty <= 0) {
            return __('Please enter a quantity greater than 0.');
        }

        $rows = $this->cartList->get($customerId);
        $total = ($rows[$sku] ?? 0.0) + $qty;
        $refusal = $this->cartLines->getRefusal($sku, $total, $customerId, $customerGroupId);
        if ($refusal !== null) {
            return $refusal;
        }

        $rows[$sku] = $total;
        $this->cartList->replace($customerId, $rows);

        return null;
    }

    /**
     * replace: $rows is the whole cart (the cart page and panel send it after each edit). merge: $rows are added to
     * the qty already in the cart (batch add). A qty of 0 removes the row. Rows that did not change are not
     * checked again, so a row that went out of stock stays until the customer changes or removes it.
     *
     * @param array<string, float> $rows sku => qty
     * @return array<string, string> sku => why that row was refused; the rest is saved
     */
    public function save(int $customerId, int $customerGroupId, array $rows, string $mode = self::MODE_REPLACE): array
    {
        $current = $this->cartList->get($customerId);
        $target = $mode === self::MODE_MERGE ? $current : [];
        foreach ($rows as $sku => $qty) {
            $sku = (string) $sku;
            $target[$sku] = $mode === self::MODE_MERGE ? ($current[$sku] ?? 0.0) + $qty : $qty;
        }

        $changed = array_filter(
            $target,
            // a numeric sku is an int key
            static fn (float $qty, int|string $sku): bool => $qty > 0
                && abs($qty - ($current[$sku] ?? 0.0)) > self::EPSILON,
            ARRAY_FILTER_USE_BOTH
        );

        $errors = [];
        foreach ($this->cartLines->getLines($changed, $customerId, $customerGroupId) as $sku => $line) {
            if ($line->refusal === null) {
                continue;
            }
            $errors[$sku] = (string) $line->refusal;
            if (isset($current[$sku])) {
                $target[$sku] = $current[$sku];
            } else {
                unset($target[$sku]);
            }
        }

        $this->cartList->replace($customerId, $target);

        return $errors;
    }
}
