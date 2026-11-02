<?php

declare(strict_types=1);

namespace Elme\QtyRules\Test\Unit\Model;

use Elme\QtyRules\Model\QtyRules;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Exception\InputException;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @see QtyRules
 */
class QtyRulesTest extends TestCase
{
    private StockRegistryInterface&Stub $stockRegistry;
    private GetProductSalableQtyInterface&Stub $salableQty;
    private bool $typeHasQty = true;
    private QtyRules $qtyRules;

    protected function setUp(): void
    {
        $this->stockRegistry = $this->createStub(StockRegistryInterface::class);
        $this->salableQty = $this->createStub(GetProductSalableQtyInterface::class);
        $isQtyManagedType = $this->createStub(IsSourceItemManagementAllowedForProductTypeInterface::class);
        $isQtyManagedType->method('execute')->willReturnCallback(fn (): bool => $this->typeHasQty);

        $stock = $this->createStub(StockInterface::class);
        $stock->method('getStockId')->willReturn(2);
        $stockResolver = $this->createStub(StockResolverInterface::class);
        $stockResolver->method('execute')->willReturn($stock);

        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getCode')->willReturn('base');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getWebsite')->willReturn($website);

        $this->qtyRules = new QtyRules(
            $this->stockRegistry,
            $this->salableQty,
            $stockResolver,
            $storeManager,
            $isQtyManagedType
        );
    }

    public function testProductWithMoqSixHasMinAndStepSix(): void
    {
        $this->stubStockItem(6);

        $this->assertSame(['min' => 6.0, 'step' => 6.0], $this->qtyRules->getMinAndStep($this->product()));
    }

    public function testProductWithoutMoqHasMinAndStepOne(): void
    {
        $this->stubStockItem(1);

        $this->assertSame(['min' => 1.0, 'step' => 1.0], $this->qtyRules->getMinAndStep($this->product()));
    }

    /**
     * @return array<string, array{float, bool, float, float, float}>
     */
    public static function stockItemValues(): array
    {
        return [
            'no value at all' => [0, false, 0, 1.0, 1.0],
            'increments value without the switch is ignored' => [6, false, 4, 6.0, 6.0],
            'increments win when switched on' => [4, true, 2, 4.0, 2.0],
            'first qty goes up to a valid step' => [5, true, 2, 6.0, 2.0],
            'increments without a MOQ' => [1, true, 12, 12.0, 12.0],
        ];
    }

    #[DataProvider('stockItemValues')]
    public function testMinAndStepFromStockItem(
        float $minSale,
        bool $useIncrements,
        float $increments,
        float $min,
        float $step
    ): void {
        $this->stubStockItem($minSale, $useIncrements, $increments);

        $this->assertSame(['min' => $min, 'step' => $step], $this->qtyRules->getMinAndStep($this->product()));
    }

    public function testMaxIsTheHighestMultipleInStock(): void
    {
        $this->stubStockItem(6);
        $this->salableQty->method('execute')->willReturn(31.0);

        $this->assertSame(
            ['min' => 6.0, 'step' => 6.0, 'max' => 30.0, 'salable' => true],
            $this->qtyRules->getRules($this->product())
        );
    }

    public function testMaxSaleQtyLimitsBelowStock(): void
    {
        $this->stubStockItem(6, maxSale: 20);
        $this->salableQty->method('execute')->willReturn(100.0);

        $this->assertSame(18.0, $this->qtyRules->getRules($this->product())['max']);
    }

    public function testStockBelowTheMoqIsNotSalable(): void
    {
        $this->stubStockItem(6);
        $this->salableQty->method('execute')->willReturn(4.0);

        $rules = $this->qtyRules->getRules($this->product());

        $this->assertSame(0.0, $rules['max']);
        $this->assertFalse($rules['salable']);
    }

    public function testOversoldStockGivesMaxZero(): void
    {
        $this->stubStockItem(1);
        $this->salableQty->method('execute')->willReturn(-3.0);

        $this->assertSame(0.0, $this->qtyRules->getRules($this->product())['max']);
    }

    public function testDisabledProductIsNotSalable(): void
    {
        $this->stubStockItem(1);
        $this->salableQty->method('execute')->willReturn(50.0);

        $this->assertFalse($this->qtyRules->getRules($this->product(saleable: false))['salable']);
    }

    /**
     * @return array<string, array{bool, int, bool}>
     */
    public static function stockDoesNotLimit(): array
    {
        return [
            'not managed' => [false, 0, true],
            'backorders' => [true, 1, true],
            'no qty of its own' => [true, 0, false],
        ];
    }

    #[DataProvider('stockDoesNotLimit')]
    public function testNoMaxWhenStockDoesNotLimit(bool $manageStock, int $backorders, bool $typeHasQty): void
    {
        $this->stubStockItem(6, manageStock: $manageStock, backorders: $backorders);
        $this->typeHasQty = $typeHasQty;
        $this->salableQty->method('execute')->willReturn(5.0);

        $this->assertNull($this->qtyRules->getRules($this->product())['max']);
    }

    public function testSkuWithoutStockGivesNoMax(): void
    {
        $this->stubStockItem(6);
        $this->salableQty->method('execute')->willThrowException(new InputException(__('No source items')));

        $this->assertNull($this->qtyRules->getRules($this->product())['max']);
    }

    /**
     * @return array<string, array{float, string|null}>
     */
    public static function qtysOfMoqSix(): array
    {
        return [
            'below' => [4, 'This product is sold in multiples of 6'],
            'not a multiple' => [7, 'This product is sold in multiples of 6'],
            'float noise on a multiple' => [12.00000001, null],
            'the MOQ' => [6, null],
            'a multiple' => [12, null],
            'a removed line' => [0, null],
        ];
    }

    #[DataProvider('qtysOfMoqSix')]
    public function testRefusalOfMoqSix(float $qty, ?string $message): void
    {
        $this->stubStockItem(6);

        $refusal = $this->qtyRules->getRefusalMessage(10, $qty, 1);

        $this->assertSame($message, $refusal === null ? null : (string) $refusal);
    }

    public function testNoRefusalWithoutMoqLeavesDecimalsToMagento(): void
    {
        $this->stubStockItem(1);

        $this->assertNull($this->qtyRules->getRefusalMessage(10, 1.5, 1));
        $this->assertNull($this->qtyRules->getRefusalMessage(10, 0.5, 1));
    }

    public function testRefusalWhenTheMoqIsNotTheStep(): void
    {
        $this->stubStockItem(6, true, 2);

        $this->assertSame(
            'The minimum order for this product is 6',
            (string) $this->qtyRules->getRefusalMessage(10, 4, 1)
        );
        $this->assertSame(
            'This product is sold in multiples of 2',
            (string) $this->qtyRules->getRefusalMessage(10, 7, 1)
        );
        $this->assertNull($this->qtyRules->getRefusalMessage(10, 8, 1));
    }

    public function testFormatQty(): void
    {
        $this->assertSame('6', $this->qtyRules->formatQty(6.0));
        $this->assertSame('60', $this->qtyRules->formatQty(60.0));
        $this->assertSame('1.5', $this->qtyRules->formatQty(1.5));
    }

    private function stubStockItem(
        float $minSale,
        bool $useIncrements = false,
        float $increments = 0,
        float $maxSale = 0,
        bool $manageStock = true,
        int $backorders = 0
    ): void {
        $item = $this->createStub(StockItemInterface::class);
        $item->method('getMinSaleQty')->willReturn($minSale);
        $item->method('getEnableQtyIncrements')->willReturn($useIncrements);
        $item->method('getQtyIncrements')->willReturn($increments);
        $item->method('getMaxSaleQty')->willReturn($maxSale);
        $item->method('getManageStock')->willReturn($manageStock);
        $item->method('getBackorders')->willReturn($backorders);
        $this->stockRegistry->method('getStockItem')->willReturn($item);
    }

    private function product(bool $saleable = true): Product&Stub
    {
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn(10);
        $product->method('getSku')->willReturn('MOQ-SKU');
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getStore')->willReturn($store);
        $product->method('isSaleable')->willReturn($saleable);

        return $product;
    }
}
