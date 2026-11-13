<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Setup\Patch\Data;

use Elme\ProductFamily\Model\FamilyLoader;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * FE-28: the target family attributes (replatform model of 12 Nov 2026, group "Family & Variants").
 * family_code groups the simple products of one design, family_axes lists the axis codes in order
 * (e.g. "color,size"), family_sort_order orders the members. Filled by the PIM later.
 */
class AddProductFamilyAttributes implements DataPatchInterface
{
    private const GROUP = 'Family & Variants';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            FamilyLoader::ATTR_CODE => ['type' => 'varchar', 'label' => 'Product Family', 'sort_order' => 10],
            FamilyLoader::ATTR_AXES => ['type' => 'varchar', 'label' => 'Family Axes', 'sort_order' => 20],
            FamilyLoader::ATTR_SORT => [
                'type' => 'int',
                'label' => 'Family Sort Order',
                'sort_order' => 90,
                'frontend_class' => 'validate-digits',
            ],
        ];

        foreach ($attributes as $code => $definition) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }
            $eavSetup->addAttribute(Product::ENTITY, $code, $definition + [
                'input' => 'text',
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

        // target label of the OOTB colour axis (S-08: color replaces color + colour)
        if ($eavSetup->getAttributeId(Product::ENTITY, 'color')) {
            $eavSetup->updateAttribute(Product::ENTITY, 'color', 'frontend_label', 'Colour');
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
