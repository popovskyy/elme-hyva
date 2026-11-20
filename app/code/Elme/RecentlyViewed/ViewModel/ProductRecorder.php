<?php

declare(strict_types=1);

namespace Elme\RecentlyViewed\ViewModel;

use Hyva\Theme\ViewModel\ProductPage;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Product id written to browser storage on PDP. No prices (FE-53 / guests).
 */
class ProductRecorder implements ArgumentInterface
{
    public function __construct(
        private readonly ProductPage $productPage,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->getProductId() > 0;
    }

    public function getProductId(): int
    {
        $product = $this->productPage->getProduct();

        return $product ? (int) $product->getId() : 0;
    }

    public function getSku(): string
    {
        $product = $this->productPage->getProduct();

        return $product ? (string) $product->getSku() : '';
    }
}
