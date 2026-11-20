<?php

declare(strict_types=1);

namespace Elme\Theme\Plugin;

use Magento\Catalog\Block\Product\ProductList\Toolbar;

/**
 * FE-20: ticket order — Position, Product Name, SKU, Best Sellers, Date, then Price (expanded in sorter).
 */
class ReorderPlpAvailableOrdersPlugin
{
    private const ORDER = [
        'position',
        'name',
        'sku',
        'bestsellers',
        'created_at',
        'price',
    ];

    private const LABELS = [
        'position' => 'Position',
        'name' => 'Product Name',
        'sku' => 'SKU',
        'bestsellers' => 'Best Sellers',
        'created_at' => 'Date',
        'price' => 'Price',
    ];

    /**
     * @param array<string, string|\Magento\Framework\Phrase> $result
     * @return array<string, string|\Magento\Framework\Phrase>
     */
    public function afterGetAvailableOrders(Toolbar $subject, $result): array
    {
        if (!is_array($result) || $result === []) {
            return is_array($result) ? $result : [];
        }

        $ordered = [];
        foreach (self::ORDER as $code) {
            if (!array_key_exists($code, $result)) {
                continue;
            }
            $ordered[$code] = __(self::LABELS[$code] ?? (string) $result[$code]);
        }

        foreach ($result as $code => $label) {
            if (!array_key_exists($code, $ordered)) {
                $ordered[$code] = $label;
            }
        }

        return $ordered;
    }
}
