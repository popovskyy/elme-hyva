<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_MAPS_API_KEY = 'elme_storelocator/maps/api_key';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getMapsApiKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_MAPS_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }
}
