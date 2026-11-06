<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Elme\Theme\ViewModel\CategoryBanner;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Attribute\Backend\Image as BackendImage;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * FE-18: category mobile_image + background_colour (parity with Overdose_CategoryBanner on the old site).
 */
class AddCategoryBannerAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if (!$eavSetup->getAttributeId(Category::ENTITY, CategoryBanner::ATTR_MOBILE_IMAGE)) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                CategoryBanner::ATTR_MOBILE_IMAGE,
                [
                    'type' => 'varchar',
                    'backend' => BackendImage::class,
                    'label' => 'Mobile Image',
                    'input' => 'image',
                    'required' => false,
                    'sort_order' => 50,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'group' => 'Content',
                    'visible' => true,
                ]
            );
        }

        if (!$eavSetup->getAttributeId(Category::ENTITY, CategoryBanner::ATTR_BACKGROUND_COLOUR)) {
            $eavSetup->addAttribute(
                Category::ENTITY,
                CategoryBanner::ATTR_BACKGROUND_COLOUR,
                [
                    'type' => 'varchar',
                    'label' => 'Background Colour',
                    'input' => 'text',
                    'required' => false,
                    'sort_order' => 300,
                    'global' => ScopedAttributeInterface::SCOPE_WEBSITE,
                    'group' => 'Display Settings',
                    'visible' => true,
                ]
            );
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
