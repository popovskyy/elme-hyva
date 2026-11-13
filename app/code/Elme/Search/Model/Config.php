<?php

declare(strict_types=1);

namespace Elme\Search\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const ENGINE_MAGENTO = 'magento';
    public const ENGINE_OPENSEARCH = 'opensearch';

    private const XML_ENGINE = 'elme_search/general/engine';
    private const XML_HOSTNAME = 'elme_search/opensearch/hostname';
    private const XML_PORT = 'elme_search/opensearch/port';
    private const XML_TIMEOUT = 'elme_search/opensearch/timeout';
    private const XML_INDEX_PREFIX = 'elme_search/opensearch/index_prefix';

    /** Magento's own connection, used when ours is empty (the same elme_opensearch container). */
    private const XML_MAGENTO_HOSTNAME = 'catalog/search/opensearch_server_hostname';
    private const XML_MAGENTO_PORT = 'catalog/search/opensearch_server_port';
    private const XML_MAGENTO_AUTH = 'catalog/search/opensearch_enable_auth';
    private const XML_MAGENTO_USERNAME = 'catalog/search/opensearch_username';
    private const XML_MAGENTO_PASSWORD = 'catalog/search/opensearch_password';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * True when the storefront takes listings and search from ProductSearchInterface.
     */
    public function isProductSearchEnabled(?int $storeId = null): bool
    {
        return $this->getEngine($storeId) === self::ENGINE_OPENSEARCH;
    }

    public function getEngine(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(self::XML_ENGINE, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === self::ENGINE_OPENSEARCH ? self::ENGINE_OPENSEARCH : self::ENGINE_MAGENTO;
    }

    public function getIndexPrefix(): string
    {
        $prefix = trim((string)$this->scopeConfig->getValue(self::XML_INDEX_PREFIX));
        return $prefix !== '' ? $prefix : 'elme_products';
    }

    /**
     * Connection options for OpenSearch\ClientBuilder::fromConfig().
     */
    public function getClientOptions(): array
    {
        $hostname = trim((string)$this->scopeConfig->getValue(self::XML_HOSTNAME));
        $port = trim((string)$this->scopeConfig->getValue(self::XML_PORT));
        if ($hostname === '') {
            $hostname = (string)$this->scopeConfig->getValue(self::XML_MAGENTO_HOSTNAME) ?: 'localhost';
            if ($port === '') {
                $port = (string)$this->scopeConfig->getValue(self::XML_MAGENTO_PORT);
            }
        }
        $protocol = parse_url($hostname, PHP_URL_SCHEME) ?: 'http';
        $hostname = (string)preg_replace('#^https?://#i', '', $hostname);
        $auth = '';
        if ((int)$this->scopeConfig->getValue(self::XML_MAGENTO_AUTH) === 1) {
            $auth = rawurlencode((string)$this->scopeConfig->getValue(self::XML_MAGENTO_USERNAME)) . ':'
                . rawurlencode((string)$this->scopeConfig->getValue(self::XML_MAGENTO_PASSWORD)) . '@';
        }
        $timeout = (float)$this->scopeConfig->getValue(self::XML_TIMEOUT) ?: 5.0;

        return [
            'hosts' => [$protocol . '://' . $auth . $hostname . ($port !== '' ? ':' . $port : '')],
            'retries' => 0,
            'connectionParams' => [
                'client' => [
                    'timeout' => $timeout,
                    'connect_timeout' => min(2.0, $timeout),
                ],
            ],
        ];
    }
}
