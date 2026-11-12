<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Model;

use Elme\CustomerPricing\Model\PriceCalculator;
use Elme\CustomerPricing\Model\PricedSkus;
use Elme\CustomerPricing\Model\PriceSources;
use Elme\CustomerPricing\Model\ProntoCustomer;
use Elme\CustomerPricing\Model\ProntoCustomerResolver;
use Elme\CustomerPricing\Model\ProntoPriceProvider;
use Elme\CustomerPricing\Model\ResourceModel\ProntoPriceData;
use Elme\LoginOnly\Model\GroupPriceProvider;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ProntoPriceProvider
 */
#[AllowMockObjectsWithoutExpectations]
class ProntoPriceProviderTest extends TestCase
{
    private const CUSTOMER_ID = 7;
    private const GROUP_ID = 1;

    private PricedSkus&MockObject $pricedSkus;
    private ProntoPriceData&MockObject $priceData;
    private GroupPriceProvider&MockObject $magentoPrices;
    private ProntoPriceProvider $provider;

    protected function setUp(): void
    {
        $this->pricedSkus = $this->createMock(PricedSkus::class);
        $this->priceData = $this->createMock(ProntoPriceData::class);
        $this->magentoPrices = $this->createMock(GroupPriceProvider::class);
        $customerResolver = $this->createStub(ProntoCustomerResolver::class);
        $customerResolver->method('get')->willReturn(new ProntoCustomer('C100', 'MKA', 'VIC'));

        $this->provider = new ProntoPriceProvider(
            $this->pricedSkus,
            $customerResolver,
            $this->priceData,
            new PriceCalculator(),
            $this->magentoPrices
        );
    }

    public function testNoProductsGivesNoPricesAndLoadsNothing(): void
    {
        $this->pricedSkus->expects($this->never())->method('get');

        $this->assertSame([], $this->provider->getPrices([], self::CUSTOMER_ID, self::GROUP_ID));
    }

    public function testConfigurableCostsItsCheapestSimpleProduct(): void
    {
        $this->pricedSkus->method('get')->willReturn([5 => [3 => 'VASE-SAGE', 4 => 'VASE-SAND']]);
        $this->priceData->method('getSources')->willReturn([
            'VASE-SAGE' => new PriceSources(listPrice: 120.0, marketingPrice: 99.0),
            'VASE-SAND' => new PriceSources(listPrice: 135.0, listPromo: 110.0),
        ]);
        $this->magentoPrices->expects($this->once())->method('getPrices')->with([])->willReturn([]);

        $this->assertSame(
            [5 => ['final' => 99.0, 'regular' => 120.0]],
            $this->provider->getPrices([5], self::CUSTOMER_ID, self::GROUP_ID)
        );
    }

    public function testSkuWithoutPriceListRowUsesMagentosPriceAsListPrice(): void
    {
        $this->pricedSkus->method('get')->willReturn([13 => [13 => 'BE01-100']]);
        $this->priceData->method('getSources')->willReturn([
            'BE01-100' => new PriceSources(groupDiscounts: [10.0]),
        ]);
        $this->magentoPrices->method('getPrices')
            ->with([13], self::CUSTOMER_ID, self::GROUP_ID)
            ->willReturn([13 => ['final' => 95.0, 'regular' => 100.0]]);

        $this->assertSame(
            [13 => ['final' => 90.0, 'regular' => 100.0]],
            $this->provider->getPrices([13], self::CUSTOMER_ID, self::GROUP_ID)
        );
    }

    public function testProductWithoutAnyPriceIsLeftOut(): void
    {
        $this->pricedSkus->method('get')->willReturn([8 => [8 => 'NO-PRICE']]);
        $this->priceData->method('getSources')->willReturn(['NO-PRICE' => new PriceSources()]);
        $this->magentoPrices->method('getPrices')->willReturn([]);

        $this->assertSame([], $this->provider->getPrices([8, 999], self::CUSTOMER_ID, self::GROUP_ID));
    }

    public function testPricesAreLoadedOncePerRequest(): void
    {
        $this->pricedSkus->expects($this->once())->method('get')->with([2])->willReturn([2 => [2 => 'LAMP']]);
        $this->priceData->expects($this->once())->method('getSources')
            ->willReturn(['LAMP' => new PriceSources(listPrice: 450.0, customerPrice: 380.0)]);
        $this->magentoPrices->method('getPrices')->willReturn([]);

        $this->provider->getPrices([2], self::CUSTOMER_ID, self::GROUP_ID);
        $this->assertSame(
            [2 => ['final' => 380.0, 'regular' => 450.0]],
            $this->provider->getPrices([2], self::CUSTOMER_ID, self::GROUP_ID)
        );
    }
}
