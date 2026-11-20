<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Model;

use Elme\Search\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testMagentoCredentialsAreSentOnlyWhenMagentoHostIsUsed(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(static function (string $path) {
            return match ($path) {
                'elme_search/opensearch/hostname' => '',
                'elme_search/opensearch/port' => '',
                'elme_search/opensearch/timeout' => '5',
                'catalog/search/opensearch_server_hostname' => 'elme_opensearch',
                'catalog/search/opensearch_server_port' => '9200',
                'catalog/search/opensearch_enable_auth' => '1',
                'catalog/search/opensearch_username' => 'magento',
                'catalog/search/opensearch_password' => 'secret',
                default => null,
            };
        });

        $hosts = (new Config($this->scopeConfig))->getClientOptions()['hosts'];
        $this->assertSame(['http://magento:secret@elme_opensearch:9200'], $hosts);
    }

    public function testOwnHostnameDoesNotCarryMagentoCredentials(): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(static function (string $path) {
            return match ($path) {
                'elme_search/opensearch/hostname' => 'search.example',
                'elme_search/opensearch/port' => '9200',
                'elme_search/opensearch/timeout' => '5',
                'catalog/search/opensearch_enable_auth' => '1',
                'catalog/search/opensearch_username' => 'magento',
                'catalog/search/opensearch_password' => 'secret',
                default => null,
            };
        });

        $hosts = (new Config($this->scopeConfig))->getClientOptions()['hosts'];
        $this->assertSame(['http://search.example:9200'], $hosts);
        $this->assertStringNotContainsString('magento', $hosts[0]);
        $this->assertStringNotContainsString('secret', $hosts[0]);
    }
}
