<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * Fixed order of the members of a family: family_sort_order (members without one last), then SKU.
 * Pure logic, no Magento calls.
 */
class SiblingOrder
{
    /**
     * @param array<int, array{sku: string, sort: ?int}> $members keyed by product id
     * @return int[] product ids in display order
     */
    public function sort(array $members): array
    {
        $ids = array_keys($members);
        usort($ids, static function (int $a, int $b) use ($members): int {
            $bySort = ($members[$a]['sort'] ?? PHP_INT_MAX) <=> ($members[$b]['sort'] ?? PHP_INT_MAX);
            if ($bySort !== 0) {
                return $bySort;
            }
            return strnatcasecmp($members[$a]['sku'], $members[$b]['sku']) ?: $a <=> $b;
        });

        return $ids;
    }
}
