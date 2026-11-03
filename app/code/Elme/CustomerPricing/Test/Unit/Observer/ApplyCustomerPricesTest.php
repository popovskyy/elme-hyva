<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Observer;

use Elme\CustomerPricing\Observer\ApplyCustomerPrices;
use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ApplyCustomerPrices
 */
#[AllowMockObjectsWithoutExpectations]
class ApplyCustomerPricesTest extends TestCase
{
    private CustomerPriceProviderInterface&MockObject $priceProvider;
    private ApplyCustomerPrices $observer;

    protected function setUp(): void
    {
        $this->priceProvider = $this->createMock(CustomerPriceProviderInterface::class);
        $this->observer = new ApplyCustomerPrices($this->priceProvider);
    }

    public function testLineGetsTheCustomerPriceAsCustomPrice(): void
    {
        $item = $this->item('simple', 2);
        $this->priceProvider->expects($this->once())->method('getPrices')->with([2], 7, 1)
            ->willReturn([2 => ['final' => 380.0, 'regular' => 450.0]]);

        $this->observer->execute($this->event($this->quote(7, [$item])));

        $this->assertSame(380.0, $item->getCustomPrice());
        $this->assertSame(380.0, $item->getOriginalCustomPrice());
    }

    public function testConfigurableLineIsPricedByTheSimpleProductChosen(): void
    {
        $item = $this->item('configurable', 5, 3);
        $this->priceProvider->expects($this->once())->method('getPrices')->with([3])
            ->willReturn([3 => ['final' => 99.0, 'regular' => 120.0]]);

        $this->observer->execute($this->event($this->quote(7, [$item])));

        $this->assertSame(99.0, $item->getCustomPrice());
    }

    public function testLineWithoutCustomerPriceFallsBackToMagentosPrice(): void
    {
        $item = $this->item('simple', 2);
        $item->setCustomPrice(380.0)->setOriginalCustomPrice(380.0)->setData('calculation_price', 380.0);
        $this->priceProvider->method('getPrices')->willReturn([]);

        $this->observer->execute($this->event($this->quote(7, [$item])));

        $this->assertFalse($item->hasCustomPrice());
        $this->assertArrayHasKey('custom_price', $item->getData(), 'null is saved, so the column is cleared');
        $this->assertNull($item->getData('calculation_price'));
    }

    public function testPriceChangeRepricesTheLine(): void
    {
        $item = $this->item('simple', 2);
        $item->setCustomPrice(405.0)->setOriginalCustomPrice(405.0)->setData('calculation_price', 405.0);
        $this->priceProvider->method('getPrices')->willReturn([2 => ['final' => 380.0, 'regular' => 450.0]]);

        $this->observer->execute($this->event($this->quote(7, [$item])));

        $this->assertSame(380.0, $item->getCustomPrice());
        $this->assertNull($item->getData('calculation_price'));
    }

    public function testGuestQuoteIsLeftAlone(): void
    {
        $this->priceProvider->expects($this->never())->method('getPrices');

        $this->observer->execute($this->event($this->quote(0, [$this->item('simple', 2)])));
    }

    public function testBundleLineIsLeftAlone(): void
    {
        $this->priceProvider->expects($this->never())->method('getPrices');

        $this->observer->execute($this->event($this->quote(7, [$this->item('bundle', 2)])));
    }

    private function item(string $type, int $productId, ?int $simpleId = null): Item&MockObject
    {
        $item = $this->createPartialMock(Item::class, ['getProductType', 'getProduct', 'getOptionByCode', 'isDeleted']);
        $item->method('getProductType')->willReturn($type);
        $item->method('isDeleted')->willReturn(false);
        $item->method('getProduct')->willReturn($this->product($productId));

        $option = null;
        if ($simpleId !== null) {
            $option = $this->createPartialMock(Option::class, ['getProduct']);
            $option->method('getProduct')->willReturn($this->product($simpleId));
        }
        $item->method('getOptionByCode')->willReturnMap([['simple_product', $option]]);

        return $item;
    }

    private function product(int $id): Product
    {
        $product = $this->createPartialMock(Product::class, []);
        $product->setId($id);

        return $product;
    }

    /**
     * @param Item[] $items
     */
    private function quote(int $customerId, array $items): Quote
    {
        $quote = $this->createPartialMock(Quote::class, ['getAllVisibleItems']);
        $quote->method('getAllVisibleItems')->willReturn($items);
        $quote->setData('customer_id', $customerId)->setData('customer_group_id', 1);

        return $quote;
    }

    private function event(Quote $quote): Observer
    {
        return new Observer(['event' => new Event(['quote' => $quote])]);
    }
}
