<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * FE-30: the dimension drawing as an image role (`image_diagram`, "Dimension Diagram" among the admin Images and
 * Videos roles). The "View Dimensions" link in the PDP Dimensions section opens it; a product with no drawing has no
 * link. In the target model the PIM sends it as the gallery role `image_diagram` (elme-product-data, `diagram_image`).
 */
class AddProductImageDiagramAttribute implements DataPatchInterface
{
    public const CODE = 'image_diagram';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        if (!$eavSetup->getAttributeId(Product::ENTITY, self::CODE)) {
            $eavSetup->addAttribute(Product::ENTITY, self::CODE, [
                'type' => 'varchar',
                'input' => 'media_image',
                'frontend' => \Magento\Catalog\Model\Product\Attribute\Frontend\Image::class,
                'label' => 'Dimension Diagram',
                'required' => false,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'image-management',
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
