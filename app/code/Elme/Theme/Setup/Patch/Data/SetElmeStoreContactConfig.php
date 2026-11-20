<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * FE-13: seed Elme NAP in store config when empty so Need assistance + FE-55 share one source.
 */
class SetElmeStoreContactConfig implements DataPatchInterface
{
    /** Victoria (AU) in directory_country_region */
    private const REGION_ID_VIC = '607';

    public function __construct(
        private readonly WriterInterface $configWriter,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function apply(): self
    {
        $defaults = [
            'general/store_information/name' => 'Elme Living',
            'general/store_information/phone' => '(03) 7067 9725',
            'general/store_information/street_line1' => '1 Apollo Court',
            'general/store_information/city' => 'Blackburn',
            'general/store_information/postcode' => '3130',
            'general/store_information/country_id' => 'AU',
            'general/store_information/region_id' => self::REGION_ID_VIC,
            'trans_email/ident_general/email' => 'hello@elmeliving.com',
            'trans_email/ident_general/name' => 'Elme Living',
        ];

        foreach ($defaults as $path => $value) {
            $this->setIfEmpty($path, $value);
        }

        return $this;
    }

    private function setIfEmpty(string $path, string $value): void
    {
        $current = trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE));
        if ($current !== '' && !str_ends_with(strtolower($current), '@example.com')) {
            return;
        }

        $this->configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, Store::DEFAULT_STORE_ID);
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
