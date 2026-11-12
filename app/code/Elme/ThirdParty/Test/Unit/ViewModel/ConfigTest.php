<?php

declare(strict_types=1);

namespace Elme\ThirdParty\Test\Unit\ViewModel;

use Elme\ThirdParty\ViewModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private ScopeConfigInterface $scopeConfig;

    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig, new Json());
    }

    public function testGetOutletEmbedFormIdReturnsConfiguredId(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('elme_third_party/klaviyo/outlet_embed_form_id', ScopeInterface::SCOPE_STORE)
            ->willReturn('WJGDrH');

        self::assertSame('WJGDrH', $this->config->getOutletEmbedFormId());
    }

    public function testGetOutletEmbedFormIdRejectsUnsafeCharacters(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('elme_third_party/klaviyo/outlet_embed_form_id', ScopeInterface::SCOPE_STORE)
            ->willReturn('WJ"><script>');

        self::assertSame('', $this->config->getOutletEmbedFormId());
    }

    public function testGetOutletEmbedFormIdTrimsEmpty(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('elme_third_party/klaviyo/outlet_embed_form_id', ScopeInterface::SCOPE_STORE)
            ->willReturn('   ');

        self::assertSame('', $this->config->getOutletEmbedFormId());
    }
}
