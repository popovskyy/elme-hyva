<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * FE-29: target attributes of the group "Dimensions & Packaging" that the PDP Dimensions and Delivery
 * sections read (replatform model of 12 Nov 2026). JSON lists are read-only text filled by the PIM:
 * measurements = [{name, value, unit}], cartons = [{length, width, height, weight}] (cm, kg).
 */
class AddProductDimensionsAttributes implements DataPatchInterface
{
    private const GROUP = 'Dimensions & Packaging';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            'measurements' => ['type' => 'text', 'input' => 'textarea', 'label' => 'Measurements', 'sort_order' => 50],
            'cartons' => ['type' => 'text', 'input' => 'textarea', 'label' => 'Cartons', 'sort_order' => 60],
            'units_per_ctn' => [
                'type' => 'int',
                'input' => 'text',
                'label' => 'Units per Carton',
                'sort_order' => 70,
                'frontend_class' => 'validate-digits',
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
                'used_in_product_listing' => false,
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
