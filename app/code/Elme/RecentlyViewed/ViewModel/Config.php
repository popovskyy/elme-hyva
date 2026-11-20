<?php

declare(strict_types=1);

namespace Elme\RecentlyViewed\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * FE-24: client-side recently viewed strips (browser storage + FE-19 product cards).
 */
class Config implements ArgumentInterface
{
    public const XML_PATH_ENABLED = 'elme_catalog/recently_viewed/enabled';
    public const XML_PATH_LIFETIME = 'elme_catalog/recently_viewed/lifetime';
    public const XML_PATH_CAPACITY = 'elme_catalog/recently_viewed/capacity';

    public const STORAGE_KEY_CATEGORIES = 'elme_rv_categories';
    public const STORAGE_KEY_PRODUCTS = 'elme_rv_products';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function getLifetimeSeconds(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_LIFETIME, ScopeInterface::SCOPE_STORE);

        return $value > 0 ? $value : 604800;
    }

    public function getCapacity(): int
    {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_CAPACITY, ScopeInterface::SCOPE_STORE);

        return $value > 0 ? $value : 10;
    }

    public function getCategoriesStorageKey(): string
    {
        return self::STORAGE_KEY_CATEGORIES;
    }

    public function getProductsStorageKey(): string
    {
        return self::STORAGE_KEY_PRODUCTS;
    }

    public function getProductCardsUrl(): string
    {
        return $this->urlBuilder->getUrl('recently_viewed/products/cards');
    }
}
