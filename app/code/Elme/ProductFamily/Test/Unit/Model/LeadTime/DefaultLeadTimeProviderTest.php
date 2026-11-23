<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model\LeadTime;

use Elme\ProductFamily\Model\LeadTime\DefaultLeadTimeProvider;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class DefaultLeadTimeProviderTest extends TestCase
{
    private function product(bool $madeToOrder): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnMap([['made_to_order', null, $madeToOrder ? '1' : '0']]);
        return $product;
    }

    private function provider(string|int|null $weeks, ?string $label = null): DefaultLeadTimeProvider
    {
        $config = $this->createStub(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnMap([
            [DefaultLeadTimeProvider::XML_PATH_DEFAULT_WEEKS, 'store', null, $weeks],
            [DefaultLeadTimeProvider::XML_PATH_LABEL, 'store', null, $label],
        ]);
        return new DefaultLeadTimeProvider($config);
    }

    public function testMadeToOrderGetsTheConfiguredWeeks(): void
    {
        $leadTime = $this->provider('14')->getLeadTime($this->product(true));

        self::assertSame(14, $leadTime->weeks);
        self::assertSame('With you in 14 weeks', $leadTime->label);
        self::assertSame(['weeks' => 14, 'label' => 'With you in 14 weeks'], $leadTime->toArray());
    }

    public function testOneWeekIsSingular(): void
    {
        self::assertSame('With you in 1 week', $this->provider(1)->getLeadTime($this->product(true))->label);
    }

    public function testWordingComesFromTheConfiguredText(): void
    {
        $leadTime = $this->provider(12, 'Ships in about %1')->getLeadTime($this->product(true));

        self::assertSame('Ships in about 12 weeks', $leadTime->label);
    }

    public function testBlankWordingFallsBackToTheBuiltInText(): void
    {
        self::assertSame('With you in 8 weeks', $this->provider(8, '   ')->getLeadTime($this->product(true))->label);
    }

    public function testQuickShipHasNoLeadTime(): void
    {
        self::assertNull($this->provider('14')->getLeadTime($this->product(false)));
    }

    public function testNoConfiguredValueHasNoLeadTime(): void
    {
        self::assertNull($this->provider(null)->getLeadTime($this->product(true)));
        self::assertNull($this->provider('0')->getLeadTime($this->product(true)));
    }
}
