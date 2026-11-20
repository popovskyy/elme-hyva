<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;

/**
 * The customer's cart: the Quick Order list cart_list, sku => qty and nothing else. Name, image, price, stock and
 * MOQ are read live (CartLines), so a catalogue change never leaves stale data here.
 */
class CartList
{
    public const NAME = 'cart_list';

    public function __construct(
        private readonly QuickOrderList $resource
    ) {
    }

    /**
     * @return array<string, float> sku => qty, in the order they were added
     */
    public function get(int $customerId, string $name = self::NAME): array
    {
        $rows = [];
        foreach ($this->resource->loadItems($customerId, $name) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string) ($item['sku'] ?? ''));
            $qty = (float) ($item['qty'] ?? 0);
            if ($sku !== '' && $qty > 0) {
                $rows[$sku] = ($rows[$sku] ?? 0.0) + $qty;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, float> $rows sku => qty; a qty of 0 or less drops the row
     */
    public function replace(int $customerId, array $rows, string $name = self::NAME): void
    {
        $items = [];
        foreach ($rows as $sku => $qty) {
            $sku = trim((string) $sku);
            if ($sku !== '' && $qty > 0) {
                $items[] = ['sku' => $sku, 'qty' => (float) $qty];
            }
        }

        $this->resource->saveItems($customerId, $name, $items);
    }

    public function clear(int $customerId, string $name = self::NAME): void
    {
        $this->resource->saveItems($customerId, $name, []);
    }
}
