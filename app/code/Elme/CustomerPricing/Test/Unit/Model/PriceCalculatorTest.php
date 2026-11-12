<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Model;

use Elme\CustomerPricing\Model\PriceCalculator;
use Elme\CustomerPricing\Model\PriceSources;
use PHPUnit\Framework\TestCase;

/**
 * @see PriceCalculator
 */
class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PriceCalculator();
    }

    public function testCustomerWithoutMappingPaysTheListPrice(): void
    {
        $this->assertPrice(100.0, 100.0, false, new PriceSources(listPrice: 100.0));
    }

    public function testActiveListPromoIsForEveryone(): void
    {
        $this->assertPrice(80.0, 100.0, true, new PriceSources(listPrice: 100.0, listPromo: 80.0));
    }

    public function testRegionPriceOfTheCustomersRegionGroup(): void
    {
        $this->assertPrice(90.0, 100.0, false, new PriceSources(listPrice: 100.0, regionPrice: 90.0));
    }

    public function testRegionPromoWinsOverTheRegionPrice(): void
    {
        $this->assertPrice(70.0, 100.0, true, new PriceSources(listPrice: 100.0, regionPrice: 90.0, regionPromo: 70.0));
    }

    public function testDiscountTypeA1IsAFixedPriceForTheProntoCustomer(): void
    {
        $this->assertPrice(55.5, 100.0, false, new PriceSources(listPrice: 100.0, customerPrice: 55.5));
    }

    public function testDiscountType20WithAPriceIsAFixedPriceForTheMarketingFlag(): void
    {
        $this->assertPrice(60.0, 100.0, false, new PriceSources(listPrice: 100.0, marketingPrice: 60.0));
    }

    public function testDiscountType20WithADiscountIsPercentOffTheListPrice(): void
    {
        $this->assertPrice(85.0, 100.0, false, new PriceSources(listPrice: 100.0, marketingDiscount: 15.0));
    }

    public function testDiscountType25IsPercentOffTheListPriceForTheItemGroup(): void
    {
        $this->assertPrice(75.0, 100.0, false, new PriceSources(listPrice: 100.0, groupDiscounts: [25.0]));
    }

    public function testBestOfSeveralItemGroupDiscounts(): void
    {
        $this->assertPrice(60.0, 100.0, false, new PriceSources(listPrice: 100.0, groupDiscounts: [10.0, 40.0]));
    }

    public function testTheLowestCandidateWins(): void
    {
        $sources = new PriceSources(
            listPrice: 100.0,
            listPromo: 95.0,
            regionPrice: 92.0,
            customerPrice: 88.0,
            marketingPrice: 91.0,
            marketingDiscount: 5.0,
            groupDiscounts: [20.0]
        );

        $this->assertPrice(80.0, 100.0, false, $sources);
    }

    public function testListPromoWinsWhenItIsTheLowest(): void
    {
        $this->assertPrice(
            70.0,
            100.0,
            true,
            new PriceSources(listPrice: 100.0, listPromo: 70.0, customerPrice: 88.0)
        );
    }

    public function testAMappingAboveTheListPriceNeverRaisesThePrice(): void
    {
        $this->assertPrice(100.0, 100.0, false, new PriceSources(listPrice: 100.0, customerPrice: 120.0));
    }

    public function testPercentDiscountsNeedAListPrice(): void
    {
        $this->assertNull($this->calculator->calculate(
            new PriceSources(marketingDiscount: 15.0, groupDiscounts: [25.0])
        ));
    }

    public function testFixedPriceWithoutAListPriceIsBothFinalAndRegular(): void
    {
        $this->assertPrice(42.0, 42.0, false, new PriceSources(customerPrice: 42.0));
    }

    public function testZeroAndNegativePricesAreIgnored(): void
    {
        $sources = new PriceSources(listPrice: 100.0, listPromo: 0.0, customerPrice: -5.0, groupDiscounts: [100.0]);

        $this->assertPrice(100.0, 100.0, false, $sources);
    }

    public function testNothingToPriceTheSkuWith(): void
    {
        $this->assertNull($this->calculator->calculate(new PriceSources(listPrice: 0.0)));
    }

    public function testPricesAreRoundedToCents(): void
    {
        $this->assertPrice(16.66, 19.99, false, new PriceSources(listPrice: 19.99, marketingDiscount: 16.66));
    }

    private function assertPrice(float $final, float $regular, bool $promo, PriceSources $sources): void
    {
        $this->assertSame(
            ['final' => $final, 'regular' => $regular, 'promo' => $promo],
            $this->calculator->calculate($sources)
        );
    }
}
