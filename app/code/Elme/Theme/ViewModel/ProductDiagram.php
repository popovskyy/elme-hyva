<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Model\Product;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-30: the dimension drawing of a product (image role `image_diagram`,
 * Setup\Patch\Data\AddProductImageDiagramAttribute). Empty string when the product has none, so the
 * "View Dimensions" link is simply not printed.
 */
class ProductDiagram implements ArgumentInterface
{
    public const ATTRIBUTE_CODE = 'image_diagram';

    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getUrl(Product $product): string
    {
        $file = trim((string) $product->getData(self::ATTRIBUTE_CODE));
        if ($file === '' || $file === 'no_selection') {
            return '';
        }

        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . 'catalog/product/' . ltrim($file, '/');
    }
}
