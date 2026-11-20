<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * Picks the other configurations of a design (modules, armchair, ottoman, 2 to 6 seaters). They
 * share `range`, not `family_code`: every family_code in the range is one configuration, a product
 * without a family_code is a configuration of its own. Per configuration the product in the current
 * colour is chosen, else the first one in family order. Pure logic, no Magento calls.
 *
 * Input: [productId => ['sku' => string, 'family' => string, 'colour' => string, 'sort' => ?int]],
 * the products of the range (the current product may be among them).
 */
class ConfigurationPicker
{
    public function __construct(private readonly SiblingOrder $siblingOrder)
    {
    }

    /**
     * @param array<int, array{sku: string, family: string, colour: string, sort: ?int}> $candidates
     * @return int[] one product id per other configuration, in a fixed order (family code, then SKU)
     */
    public function pick(array $candidates, int $currentId, string $currentFamily, string $currentColour): array
    {
        $currentFamily = trim($currentFamily);
        $groups = [];
        foreach ($candidates as $id => $candidate) {
            $family = trim($candidate['family']);
            if ($id === $currentId || ($currentFamily !== '' && $family === $currentFamily)) {
                continue;
            }
            $groups[$family !== '' ? 'f:' . $family : 'p:' . $candidate['sku'] . ':' . $id][$id] = $candidate;
        }

        $picked = [];
        foreach ($groups as $key => $members) {
            $ordered = $this->siblingOrder->sort($members);
            $chosen = $ordered[0];
            if ($currentColour !== '') {
                foreach ($ordered as $id) {
                    if ($members[$id]['colour'] === $currentColour) {
                        $chosen = $id;
                        break;
                    }
                }
            }
            $picked[$key] = $chosen;
        }

        uksort($picked, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return array_values($picked);
    }
}
