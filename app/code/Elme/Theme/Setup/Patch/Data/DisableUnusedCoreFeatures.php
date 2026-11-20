<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Features Elme does not sell, whose modules cannot be disabled because Braintree
 * or configurable products still load them.
 */
class DisableUnusedCoreFeatures implements DataPatchInterface
{
    public function __construct(
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        foreach ([
            'sales/instant_purchase/active' => '0',
            'sales/msrp/enabled' => '0',
            'multishipping/options/checkout_multiple' => '0',
            'rss/config/active' => '0',
            'catalog/productalert/allow_price' => '0',
            'catalog/productalert/allow_stock' => '0',
            'sales/gift_options/allow_order' => '0',
            'sales/gift_options/allow_items' => '0',
        ] as $path => $value) {
            $this->configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
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
