<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * FE-85: splits the options of a colour (fabric) axis into the "Quick Ship" and "Made To Order" groups
 * of the PDP "Select Color:" picker. Pure logic, no Magento calls.
 *
 * The picker is only for a fabric set that has at least one made-to-order option; a set that is all
 * Quick Ship keeps the plain FE-28 chips, so build() returns null for it. An empty group is dropped:
 * a set that is all made to order has just the one group and no "Quick Ship" heading.
 *
 * Option input: ['selected' => bool, 'madeToOrder' => bool, 'leadTime' => ['weeks' => int, 'label' => string]|null, ...]
 * (any other key is passed through untouched).
 */
class FabricGroups
{
    public const QUICK_SHIP = 'quick_ship';
    public const MADE_TO_ORDER = 'made_to_order';

    /**
     * @param array<int, array<string, mixed>> $options in display order
     * @return array{
     *     selected: array<string, mixed>|null,
     *     groups: array<int, array{code: string, leadTime: string, options: array<int, array<string, mixed>>}>
     * }|null null when no option is made to order
     */
    public function build(array $options): ?array
    {
        $quickShip = [];
        $madeToOrder = [];
        $selected = null;
        foreach ($options as $option) {
            if (!empty($option['selected'])) {
                $selected = $option;
            }
            if (!empty($option['madeToOrder'])) {
                $madeToOrder[] = $option;
            } else {
                $quickShip[] = $option;
            }
        }
        if (!$madeToOrder) {
            return null;
        }

        $groups = [];
        if ($quickShip) {
            $groups[] = ['code' => self::QUICK_SHIP, 'leadTime' => '', 'options' => $quickShip];
        }
        $groups[] = [
            'code' => self::MADE_TO_ORDER,
            'leadTime' => $this->longestLeadTime($madeToOrder),
            'options' => $madeToOrder,
        ];

        return ['selected' => $selected, 'groups' => $groups];
    }

    /**
     * The wording of the longest lead time in the group: the group header promises the worst case.
     *
     * @param array<int, array<string, mixed>> $options
     */
    private function longestLeadTime(array $options): string
    {
        $weeks = 0;
        $label = '';
        foreach ($options as $option) {
            $leadTime = $option['leadTime'] ?? null;
            if (is_array($leadTime) && (int) ($leadTime['weeks'] ?? 0) > $weeks) {
                $weeks = (int) $leadTime['weeks'];
                $label = (string) ($leadTime['label'] ?? '');
            }
        }

        return $label;
    }
}
