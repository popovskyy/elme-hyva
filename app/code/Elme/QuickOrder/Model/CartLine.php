<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\Phrase;

/**
 * One row of the cart with what CartLines read live for it. $refusal says why it cannot be ordered right now.
 */
class CartLine
{
    /**
     * @param array{final: float, regular: float}|null $price Ex GST, null when the customer has no price
     * @param array{min: float, step: float, max: float|null, salable: bool}|null $rules
     */
    public function __construct(
        public readonly string $sku,
        public readonly float $qty,
        public readonly ?Product $product = null,
        public readonly ?Product $parent = null,
        public readonly ?array $price = null,
        public readonly ?array $rules = null,
        public readonly ?float $salableQty = null,
        public readonly ?Phrase $refusal = null
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->refusal === null;
    }

    public function getRowTotal(): float
    {
        return round(($this->price['final'] ?? 0.0) * $this->qty, 2);
    }
}
