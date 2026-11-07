<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Test\Unit\Model;

use Elme\QuickOrder\Model\CartList;
use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;
use Elme\QuickOrder\Model\SavedLists;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\TestCase;

/**
 * @see SavedLists
 */
class SavedListsTest extends TestCase
{
    public function testGetPayloadSkipsCartListAndCountsItems(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('loadNamedLists')->with(7, CartList::NAME)->willReturn([
            [
                'id' => 3,
                'name' => 'Showroom',
                'items' => [
                    ['sku' => 'A', 'qty' => 6],
                    ['sku' => 'B', 'qty' => 1],
                ],
            ],
        ]);

        $collection = $this->createMock(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $payload = (new SavedLists(
            $resource,
            $this->createStub(CartList::class),
            $factory,
            $this->createStub(ImageHelper::class)
        ))->getPayload(7);

        $this->assertSame(2, $payload['total_item_count']);
        $this->assertCount(1, $payload['lists']);
        $this->assertSame('Showroom', $payload['lists'][0]['name']);
        $this->assertSame(2, $payload['lists'][0]['item_count']);
        $this->assertSame(['A', 'B'], $payload['saved_skus']);
    }

    public function testSaveRefusesCartListName(): void
    {
        $this->expectException(LocalizedException::class);

        (new SavedLists(
            $this->createStub(QuickOrderList::class),
            $this->createStub(CartList::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(ImageHelper::class)
        ))->save(7, CartList::NAME, ['A' => 1.0]);
    }

    public function testSaveMergesIntoNamedList(): void
    {
        $cartList = $this->createMock(CartList::class);
        $cartList->expects($this->once())->method('get')->with(7, 'Showroom')->willReturn(['A' => 6.0]);
        $cartList->expects($this->once())->method('replace')->with(7, ['A' => 12.0, 'B' => 1.0], 'Showroom');

        (new SavedLists(
            $this->createStub(QuickOrderList::class),
            $cartList,
            $this->createStub(CollectionFactory::class),
            $this->createStub(ImageHelper::class)
        ))->save(7, 'Showroom', ['A' => 6.0, 'B' => 1.0]);
    }

    public function testDeleteMissingListThrows(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('deleteOwned')->with(9, 7)->willReturn(false);

        $this->expectException(NoSuchEntityException::class);

        (new SavedLists(
            $resource,
            $this->createStub(CartList::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(ImageHelper::class)
        ))->delete(7, 9);
    }
}
