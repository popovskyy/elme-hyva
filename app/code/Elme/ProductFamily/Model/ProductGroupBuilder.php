<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * Shapes a family as schema.org ProductGroup data (hasVariant). FE-55 prints it as JSON-LD; this
 * class only builds the array, with no "@context" and no price (prices are per customer, B2B).
 * Pure logic, no Magento calls.
 */
class ProductGroupBuilder
{
    /** axis code => schema.org property that can describe a variant */
    private const SCHEMA_PROPERTIES = [
        'color' => 'color',
        'size' => 'size',
    ];

    /**
     * @param array<int, array{sku: string, name: string, url: string, image: string, values: array<string, string>}> $variants
     * @param array<string, string> $axisLabels axis code => label, in family_axes order
     * @return array<string, mixed>|null null when there are fewer than two variants
     */
    public function build(string $groupId, string $name, array $variants, array $axisLabels): ?array
    {
        if (count($variants) < 2) {
            return null;
        }

        $variesBy = [];
        foreach (array_keys($axisLabels) as $code) {
            $distinct = array_unique(array_column(array_column($variants, 'values'), $code));
            if (isset(self::SCHEMA_PROPERTIES[$code]) && count(array_filter($distinct, 'strlen')) > 1) {
                $variesBy[] = 'https://schema.org/' . self::SCHEMA_PROPERTIES[$code];
            }
        }

        $hasVariant = [];
        foreach ($variants as $variant) {
            $item = [
                '@type' => 'Product',
                'sku' => $variant['sku'],
                'name' => $variant['name'],
                'url' => $variant['url'],
            ];
            if ($variant['image'] !== '') {
                $item['image'] = $variant['image'];
            }
            $extra = [];
            foreach ($axisLabels as $code => $label) {
                $value = $variant['values'][$code] ?? '';
                if ($value === '') {
                    continue;
                }
                if (isset(self::SCHEMA_PROPERTIES[$code])) {
                    $item[self::SCHEMA_PROPERTIES[$code]] = $value;
                } else {
                    $extra[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $value];
                }
            }
            if ($extra) {
                $item['additionalProperty'] = $extra;
            }
            $hasVariant[] = $item;
        }

        return [
            '@type' => 'ProductGroup',
            'productGroupID' => $groupId,
            'name' => $name,
            'variesBy' => $variesBy,
            'hasVariant' => $hasVariant,
        ];
    }
}
