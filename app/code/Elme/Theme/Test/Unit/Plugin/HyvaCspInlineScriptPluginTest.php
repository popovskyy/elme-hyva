<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\Plugin;

use Elme\Theme\Plugin\HyvaCspInlineScriptPlugin;
use Hyva\Theme\Model\HtmlPageContent;
use Hyva\Theme\ViewModel\HyvaCsp;
use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HyvaCspInlineScriptPluginTest extends TestCase
{
    /** @var DynamicCollector&MockObject */
    private DynamicCollector $dynamicCollector;

    /** @var HtmlPageContent&MockObject */
    private HtmlPageContent $htmlPageContent;

    private HyvaCspInlineScriptPlugin $plugin;

    protected function setUp(): void
    {
        $this->dynamicCollector = $this->createMock(DynamicCollector::class);
        $this->htmlPageContent = $this->createMock(HtmlPageContent::class);
        $this->plugin = new HyvaCspInlineScriptPlugin($this->dynamicCollector, $this->htmlPageContent);
    }

    public function testRecordsHashWhenInlineIsAllowed(): void
    {
        $policy = $this->createMock(FetchPolicy::class);
        $policy->method('isInlineAllowed')->willReturn(true);

        $subject = $this->createMock(HyvaCsp::class);
        $subject->method('getScriptSrcPolicy')->willReturn($policy);

        $this->htmlPageContent->method('extractLastElementContent')
            ->willReturn('function initElmeMobileMenu() {}');

        $this->dynamicCollector->expects($this->once())
            ->method('add')
            ->with($this->callback(static function ($policy): bool {
                return $policy instanceof FetchPolicy
                    && $policy->getId() === 'script-src'
                    && $policy->getHashes() !== [];
            }));

        $called = false;
        $this->plugin->aroundRegisterInlineScript($subject, static function () use (&$called): void {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function testDefersToHyvaWhenInlineIsNotAllowed(): void
    {
        $policy = $this->createMock(FetchPolicy::class);
        $policy->method('isInlineAllowed')->willReturn(false);

        $subject = $this->createMock(HyvaCsp::class);
        $subject->method('getScriptSrcPolicy')->willReturn($policy);

        $this->dynamicCollector->expects($this->never())->method('add');

        $called = false;
        $this->plugin->aroundRegisterInlineScript($subject, static function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }
}
