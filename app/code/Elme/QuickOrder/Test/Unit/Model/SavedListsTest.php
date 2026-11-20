<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Test\Unit\Model;

use Elme\QuickOrder\Model\CartList;
use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;
use Elme\QuickOrder\Model\SavedLists;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\DB\Adapter\DuplicateException;
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

        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $payload = (new SavedLists(
            $resource,
            $this->createStub(CartList::class),
            $factory,
            $this->createStub(ImageHelper::class),
            $this->createStub(ConfigurableResource::class)
        ))->getPayload(7);

        $this->assertSame(2, $payload['total_item_count']);
        $this->assertCount(1, $payload['lists']);
        $this->assertSame('Showroom', $payload['lists'][0]['name']);
        $this->assertSame(2, $payload['lists'][0]['item_count']);
        $this->assertSame(['A', 'B'], $payload['saved_skus']);
        $this->assertSame(['A', 'B'], $payload['lists'][0]['skus']);
    }

    public function testGetSummaryListsSkusWithoutLoadingProducts(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('loadNamedLists')->willReturn([
            ['id' => 3, 'name' => 'Showroom', 'items' => [['sku' => 'A', 'qty' => 6], ['sku' => ' ', 'qty' => 1]]],
            ['id' => 4, 'name' => 'Warehouse', 'items' => [['sku' => 'A', 'qty' => 1], ['sku' => 'C', 'qty' => 2]]],
        ]);
        $catalogue = $this->createMock(CollectionFactory::class);
        $catalogue->expects($this->never())->method('create');

        $summary = $this->savedLists($resource, null, $catalogue)->getSummary(7);

        $this->assertSame([
            ['id' => 3, 'name' => 'Showroom', 'item_count' => 1, 'skus' => ['A']],
            ['id' => 4, 'name' => 'Warehouse', 'item_count' => 2, 'skus' => ['A', 'C']],
        ], $summary['lists']);
        $this->assertSame(['A', 'C'], $summary['saved_skus']);
        $this->assertSame(3, $summary['total_item_count']);
    }

    public function testRemoveTakesTheSkuOffOneListIgnoringCase(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('exists')->willReturn(true);
        $cartList = $this->createMock(CartList::class);
        $cartList->method('get')->with(7, 'Showroom')->willReturn(['FS.114.WH' => 2.0, 'B' => 1.0]);
        $cartList->expects($this->once())->method('replace')->with(7, ['B' => 1.0], 'Showroom');

        $this->savedLists($resource, $cartList)->remove(7, 'Showroom', ['fs.114.wh', 'NOT-THERE']);
    }

    public function testRemoveRefusesAMissingList(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('exists')->willReturn(false);
        $cartList = $this->createMock(CartList::class);
        $cartList->expects($this->never())->method('replace');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('List not found.');

        $this->savedLists($resource, $cartList)->remove(7, 'Showroom', ['A']);
    }

    public function testSaveRefusesCartListName(): void
    {
        $this->expectException(LocalizedException::class);

        (new SavedLists(
            $this->createStub(QuickOrderList::class),
            $this->createStub(CartList::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(ImageHelper::class),
            $this->createStub(ConfigurableResource::class)
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
            $this->createStub(ImageHelper::class),
            $this->createStub(ConfigurableResource::class)
        ))->save(7, 'Showroom', ['A' => 6.0, 'B' => 1.0]);
    }

    public function testCreateInsertsAnEmptyList(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->expects($this->once())->method('insertEmpty')->with(7, 'Showroom');
        $cartList = $this->createMock(CartList::class);
        $cartList->expects($this->never())->method('replace');

        $this->assertSame('Showroom', $this->savedLists($resource, $cartList)->create(7, '  Showroom '));
    }

    public function testCreateRefusesTakenNameAndLeavesTheListAlone(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('insertEmpty')->willThrowException(new DuplicateException('Duplicate entry'));
        $cartList = $this->createMock(CartList::class);
        $cartList->expects($this->never())->method('replace');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A list with this name already exists.');

        $this->savedLists($resource, $cartList)->create(7, 'Showroom');
    }

    public function testCreateRefusesCartListName(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->expects($this->never())->method('insertEmpty');

        $this->expectException(LocalizedException::class);

        $this->savedLists($resource)->create(7, CartList::NAME);
    }

    public function testRenameMovesTheList(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('getId')->willReturnMap([[7, 'Showroom', 3], [7, 'Shop floor', null]]);
        $resource->expects($this->once())->method('renameOwned')->with(3, 7, 'Shop floor');

        $this->assertSame('Shop floor', $this->savedLists($resource)->rename(7, 'Showroom', ' Shop floor '));
    }

    public function testRenameMayChangeOnlyTheCase(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        // the collation ignores case: the new name finds the same list
        $resource->method('getId')->willReturn(3);
        $resource->expects($this->once())->method('renameOwned')->with(3, 7, 'SHOWROOM');

        $this->savedLists($resource)->rename(7, 'Showroom', 'SHOWROOM');
    }

    public function testRenameRefusesNameOfAnotherList(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('getId')->willReturnMap([[7, 'Showroom', 3], [7, 'Warehouse', 4]]);
        $resource->expects($this->never())->method('renameOwned');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A list with this name already exists.');

        $this->savedLists($resource)->rename(7, 'Showroom', 'Warehouse');
    }

    public function testRenameMissingListThrows(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('getId')->willReturn(null);
        $resource->expects($this->never())->method('renameOwned');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('List not found.');

        $this->savedLists($resource)->rename(7, 'Gone', 'Showroom');
    }

    public function testAddRefusesUnknownSkuAndStoresNothing(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('exists')->willReturn(true);
        $cartList = $this->createMock(CartList::class);
        $cartList->expects($this->never())->method('replace');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('SKU not found.');

        $this->savedLists($resource, $cartList, $this->catalogue([]))->add(7, 'Showroom', ['NO-SUCH-SKU-123' => 1.0]);
    }

    public function testAddStoresTheCatalogueSku(): void
    {
        $resource = $this->createMock(QuickOrderList::class);
        $resource->method('exists')->willReturn(true);
        $cartList = $this->createMock(CartList::class);
        $cartList->method('get')->willReturn(['A' => 1.0]);
        $cartList->expects($this->once())->method('replace')->with(7, ['A' => 1.0, 'ABC-1' => 2.0], 'Showroom');

        $this->savedLists($resource, $cartList, $this->catalogue(['ABC-1' => Visibility::VISIBILITY_BOTH]))
            ->add(7, 'Showroom', ['abc-1' => 2.0]);
    }

    public function testAddRefusesProductThatCannotBeShown(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('exists')->willReturn(true);
        $configurable = $this->createStub(ConfigurableResource::class);
        $configurable->method('getParentIdsByChild')->willReturn([]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('SKU not found.');

        (new SavedLists(
            $resource,
            $this->createStub(CartList::class),
            $this->catalogue(['HIDDEN' => Visibility::VISIBILITY_NOT_VISIBLE]),
            $this->createStub(ImageHelper::class),
            $configurable
        ))->add(7, 'Showroom', ['HIDDEN' => 1.0]);
    }

    public function testAddToMissingListThrows(): void
    {
        $resource = $this->createStub(QuickOrderList::class);
        $resource->method('exists')->willReturn(false);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('List not found.');

        $this->savedLists($resource)->add(7, 'Gone', ['A' => 1.0]);
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
            $this->createStub(ImageHelper::class),
            $this->createStub(ConfigurableResource::class)
        ))->delete(7, 9);
    }

    private function savedLists(
        QuickOrderList $resource,
        ?CartList $cartList = null,
        ?CollectionFactory $catalogue = null
    ): SavedLists {
        return new SavedLists(
            $resource,
            $cartList ?? $this->createStub(CartList::class),
            $catalogue ?? $this->createStub(CollectionFactory::class),
            $this->createStub(ImageHelper::class),
            $this->createStub(ConfigurableResource::class)
        );
    }

    /**
     * A product collection that holds these enabled products of the website.
     *
     * @param array<string, int> $products sku => visibility
     */
    private function catalogue(array $products): CollectionFactory
    {
        $items = [];
        $id = 1;
        foreach ($products as $sku => $visibility) {
            $product = $this->createStub(Product::class);
            $product->method('getSku')->willReturn($sku);
            $product->method('getId')->willReturn($id++);
            $product->method('getVisibility')->willReturn($visibility);
            $items[] = $product;
        }

        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addAttributeToFilter')->willReturnSelf();
        $collection->method('addWebsiteFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));

        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }
}
