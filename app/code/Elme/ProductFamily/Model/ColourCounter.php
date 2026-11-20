<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * "N colours available" for a product card: how many different colours the members of a family
 * have. Pure logic, no Magento calls.
 */
class ColourCounter
{
    /**
     * @param array<int|string, string> $colours colour value of every member (empty = none)
     */
    public function count(array $colours): int
    {
        return count(array_unique(array_filter(array_map('trim', $colours), static fn (string $value): bool => $value !== '')));
    }
}
