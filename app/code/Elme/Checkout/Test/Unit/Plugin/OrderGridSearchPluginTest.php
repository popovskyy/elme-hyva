<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Plugin;

use Elme\Checkout\Plugin\OrderGridSearchPlugin;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;
use PHPUnit\Framework\TestCase;

class OrderGridSearchPluginTest extends TestCase
{
    private function apply(Collection $collection, string $term, ?callable $proceed = null): void
    {
        (new OrderGridSearchPlugin())->aroundApply(
            $this->createStub(FulltextFilter::class),
            $proceed ?? function (): void {
                $this->fail('The order grid must not reach core');
            },
            $collection,
            new Filter(['value' => $term])
        );
    }

    public function testTextLooksInTheReferenceAndCoreColumns(): void
    {
        $like = ['like' => '%PO-1%'];
        $collection = $this->createMock(OrderGridCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with(
            ['increment_id', 'billing_name', 'shipping_name', 'customer_email', 'customer_reference'],
            [$like, $like, $like, $like, $like]
        );

        $this->apply($collection, ' #PO-1 ');
    }

    public function testDigitsAreAnExactOrderNumberOrPartOfAReference(): void
    {
        $collection = $this->createMock(OrderGridCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with(
            ['increment_id', 'customer_reference'],
            [['eq' => '4500'], ['like' => '%4500%']]
        );

        $this->apply($collection, '4500');
    }

    public function testLikeWildcardsAreEscaped(): void
    {
        $collection = $this->createMock(OrderGridCollection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with(
            $this->anything(),
            $this->callback(fn (array $conditions): bool => $conditions[0] === ['like' => '%50\\%\\_a%'])
        );

        $this->apply($collection, '50%_a');
    }

    public function testEmptyTermAddsNothing(): void
    {
        $collection = $this->createMock(OrderGridCollection::class);
        $collection->expects($this->never())->method('addFieldToFilter');

        $this->apply($collection, ' {} ');
    }

    public function testOtherGridsGoToCore(): void
    {
        $called = false;
        $collection = $this->createStub(Collection::class);

        $this->apply($collection, 'x', function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }
}
