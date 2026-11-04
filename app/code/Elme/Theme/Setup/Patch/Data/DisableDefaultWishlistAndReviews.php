<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Turn off Magento's own wishlist and product reviews.
 * Elme replaces both with its own features; the storefront layout no longer renders the core UI.
 */
class DisableDefaultWishlistAndReviews implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        foreach (['wishlist/general/active', 'catalog/review/active'] as $path) {
            $this->configWriter->save($path, '0', ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
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
