<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * BE-21: made_to_order and condition_code with the target codes (group "Fulfilment & Logistics").
 * The sibling data tells Quick Ship from Made To Order with them. Both are filled by the PIM / DWH
 * later; the condition_code options are the Pronto codes (A, C, D, I, T).
 */
class AddFulfilmentAttributes implements DataPatchInterface
{
    private const GROUP = 'Fulfilment & Logistics';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            'made_to_order' => [
                'type' => 'int',
                'label' => 'Made to Order',
                'input' => 'boolean',
                'source' => Boolean::class,
                'default' => '0',
                'sort_order' => 10,
            ],
            'condition_code' => [
                'type' => 'int',
                'label' => 'Condition Code',
                'input' => 'select',
                'source' => Table::class,
                'option' => ['values' => ['A', 'C', 'D', 'I', 'T']],
                'sort_order' => 20,
            ],
        ];

        foreach ($attributes as $code => $definition) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }
            $eavSetup->addAttribute(Product::ENTITY, $code, $definition + [
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => self::GROUP,
                'visible' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'is_used_in_grid' => true,
                'is_filterable_in_grid' => true,
                'apply_to' => '',
            ]);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
