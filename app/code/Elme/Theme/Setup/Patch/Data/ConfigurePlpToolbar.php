<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-20: per-page 12/24/36/48 (default 24), sort attrs SKU / Date / Best Sellers, pager ellipsis jump.
 */
class ConfigurePlpToolbar implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly WriterInterface $configWriter,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        foreach ([
            'catalog/frontend/grid_per_page' => '24',
            'catalog/frontend/grid_per_page_values' => '12,24,36,48',
            'catalog/frontend/list_per_page' => '24',
            'catalog/frontend/list_per_page_values' => '12,24,36,48',
            'catalog/frontend/default_sort_by' => 'position',
            'design/pagination/pagination_frame' => '5',
            'design/pagination/pagination_frame_skip' => '5',
        ] as $path => $value) {
            $this->configWriter->save(
                $path,
                $value,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                Store::DEFAULT_STORE_ID
            );
        }

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (['sku', 'created_at'] as $code) {
            $eavSetup->updateAttribute(Product::ENTITY, $code, 'used_for_sort_by', 1);
        }
        $eavSetup->updateAttribute(Product::ENTITY, 'created_at', 'frontend_label', 'Date');

        if (!$eavSetup->getAttributeId(Product::ENTITY, 'bestsellers')) {
            $eavSetup->addAttribute(Product::ENTITY, 'bestsellers', [
                'type' => 'int',
                'label' => 'Best Sellers',
                'input' => 'text',
                'required' => false,
                'sort_order' => 100,
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'group' => 'General',
                'visible' => false,
                'user_defined' => true,
                'default' => '0',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'used_for_sort_by' => true,
                'unique' => false,
            ]);
        } else {
            $eavSetup->updateAttribute(Product::ENTITY, 'bestsellers', 'used_for_sort_by', 1);
            $eavSetup->updateAttribute(Product::ENTITY, 'bestsellers', 'frontend_label', 'Best Sellers');
        }

        $this->moduleDataSetup->getConnection()->endSetup();

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
