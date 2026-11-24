<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Model;

use Elme\CustomerPricing\Model\PriceCalculator;
use Elme\CustomerPricing\Model\PriceSources;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testPlainListPriceHasNoTradePriceAndNothingStruckThrough(): void
    {
        $this->assertBreakdown(100.0, null, null, new PriceSources(listPrice: 100.0));
    }

    public function testListPromoStrikesTheListPriceWhenThereIsNoTradePrice(): void
    {
        $this->assertBreakdown(100.0, null, 100.0, new PriceSources(listPrice: 100.0, listPromo: 80.0));
    }

    #[DataProvider('tradeSourceProvider')]
    public function testEveryAgreedEverydayPriceIsTheTradePrice(PriceSources $sources, float $trade): void
    {
        // the trade price is the price payable: nothing is struck through
        $this->assertBreakdown(100.0, $trade, null, $sources);
    }

    public static function tradeSourceProvider(): array
    {
        return [
            'region price' => [new PriceSources(listPrice: 100.0, regionPrice: 90.0), 90.0],
            'A1 customer price' => [new PriceSources(listPrice: 100.0, customerPrice: 55.5), 55.5],
            'type 20 fixed price' => [new PriceSources(listPrice: 100.0, marketingPrice: 60.0), 60.0],
            'type 20 percent off list' => [new PriceSources(listPrice: 100.0, marketingDiscount: 15.0), 85.0],
            'type 25 percent off list' => [new PriceSources(listPrice: 100.0, groupDiscounts: [25.0]), 75.0],
        ];
    }

    public function testTradePriceIsTheLowestAgreedEverydayPrice(): void
    {
        $sources = new PriceSources(
            listPrice: 100.0,
            regionPrice: 92.0,
            customerPrice: 88.0,
            marketingPrice: 91.0,
            marketingDiscount: 5.0,
            groupDiscounts: [20.0]
        );

        $this->assertBreakdown(100.0, 80.0, null, $sources);
    }

    public function testPromoUnderTheTradePriceStrikesTheTradePriceNotTheListPrice(): void
    {
        // old site: "List price: 13.65", "Trade price: 11.60 -> 2.50"
        $sources = new PriceSources(listPrice: 13.65, listPromo: 2.5, customerPrice: 11.6);

        $this->assertSame(
            [
                'final' => 2.5,
                'regular' => 13.65,
                'promo' => true,
                'list' => 13.65,
                'trade' => 11.6,
                'was' => 11.6,
            ],
            $this->calculator->calculate($sources)
        );
    }

    public function testPromoAboveTheTradePriceChangesNothing(): void
    {
        $sources = new PriceSources(listPrice: 100.0, listPromo: 95.0, customerPrice: 88.0);

        $this->assertBreakdown(100.0, 88.0, null, $sources);
    }

    public function testPromoEqualToTheTradePriceStrikesNothing(): void
    {
        $sources = new PriceSources(listPrice: 100.0, listPromo: 88.0, customerPrice: 88.0);

        $this->assertBreakdown(100.0, 88.0, null, $sources);
    }

    public function testRegionPromoUnderTheRegionPriceStrikesTheRegionPrice(): void
    {
        $sources = new PriceSources(listPrice: 100.0, regionPrice: 90.0, regionPromo: 70.0);

        $this->assertBreakdown(100.0, 90.0, 90.0, $sources);
    }

    public function testAMappingAtOrAboveTheListPriceIsNoTradePrice(): void
    {
        $this->assertBreakdown(100.0, null, null, new PriceSources(listPrice: 100.0, customerPrice: 120.0));
        $this->assertBreakdown(100.0, null, null, new PriceSources(listPrice: 100.0, customerPrice: 100.0));
    }

    public function testFixedPriceWithoutAListPriceIsATradePriceAndTheListPriceIsEmpty(): void
    {
        $this->assertSame(
            [
                'final' => 42.0,
                'regular' => 42.0,
                'promo' => false,
                'list' => null,
                'trade' => 42.0,
                'was' => null,
            ],
            $this->calculator->calculate(new PriceSources(customerPrice: 42.0))
        );
    }

    public function testPromoWithoutAListPriceStrikesNothing(): void
    {
        $this->assertSame(
            [
                'final' => 30.0,
                'regular' => 30.0,
                'promo' => false,
                'list' => null,
                'trade' => null,
                'was' => null,
            ],
            $this->calculator->calculate(new PriceSources(listPromo: 30.0))
        );
    }

    public function testBreakdownIsRoundedToCents(): void
    {
        $sources = new PriceSources(listPrice: 19.994, customerPrice: 16.664);

        $this->assertBreakdown(19.99, 16.66, null, $sources);
    }

    /**
     * The three keys every caller (cart, Quick Order, price endpoint) already relies on.
     */
    private function assertPrice(float $final, float $regular, bool $promo, PriceSources $sources): void
    {
        $price = $this->calculator->calculate($sources);

        $this->assertSame(
            ['final' => $final, 'regular' => $regular, 'promo' => $promo],
            array_intersect_key($price, ['final' => 1, 'regular' => 1, 'promo' => 1])
        );
    }

    private function assertBreakdown(float $list, ?float $trade, ?float $was, PriceSources $sources): void
    {
        $price = $this->calculator->calculate($sources);

        $this->assertSame(
            ['list' => $list, 'trade' => $trade, 'was' => $was],
            array_intersect_key($price, ['list' => 1, 'trade' => 1, 'was' => 1])
        );
    }
}
