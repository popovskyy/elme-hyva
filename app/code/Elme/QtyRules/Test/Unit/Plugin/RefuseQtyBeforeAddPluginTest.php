<?php

declare(strict_types=1);

namespace Elme\QtyRules\Test\Unit\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Elme\QtyRules\Plugin\RefuseQtyBeforeAddPlugin;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * @see RefuseQtyBeforeAddPlugin
 */
class RefuseQtyBeforeAddPluginTest extends TestCase
{
    private const SIMPLE_ID = 10;
    private const CHILD_ID = 11;
    private const PARENT_ID = 12;

    public function testRefusedQtyThrowsTheMessage(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('This product is sold in multiples of 6');

        $this->plugin([self::SIMPLE_ID])
            ->beforeAddProduct($this->quote(), $this->simple(), new DataObject(['qty' => '7.0000']));
    }

    public function testNumericRequestIsTheQty(): void
    {
        $this->expectException(LocalizedException::class);

        $this->plugin([self::SIMPLE_ID])->beforeAddProduct($this->quote(), $this->simple(), 7);
    }

    public function testAllowedQtyPasses(): void
    {
        $this->plugin([])->beforeAddProduct($this->quote(), $this->simple(), new DataObject(['qty' => 12]));

        $this->addToAssertionCount(1);
    }

    public function testRequestWithoutQtyIsLeftToCore(): void
    {
        $rules = $this->createMock(QtyRules::class);
        $rules->expects($this->never())->method('getRefusalMessage');

        (new RefuseQtyBeforeAddPlugin($rules))->beforeAddProduct($this->quote(), $this->simple(), new DataObject());
    }

    public function testAdminOrderIsLeftAlone(): void
    {
        $rules = $this->createMock(QtyRules::class);
        $rules->expects($this->never())->method('getRefusalMessage');

        (new RefuseQtyBeforeAddPlugin($rules))
            ->beforeAddProduct($this->quote(superMode: true), $this->simple(), new DataObject(['qty' => 7]));
    }

    public function testConfigurableUsesTheRulesOfTheChosenSimple(): void
    {
        $this->expectException(LocalizedException::class);

        $this->plugin([self::CHILD_ID])->beforeAddProduct(
            $this->quote(),
            $this->configurable(),
            new DataObject(['qty' => 7, 'super_attribute' => [93 => 5]])
        );
    }

    public function testConfigurableWithoutAChoiceUsesItsOwnRules(): void
    {
        $this->plugin([self::CHILD_ID])
            ->beforeAddProduct($this->quote(), $this->configurable(), new DataObject(['qty' => 7]));

        $this->addToAssertionCount(1);
    }

    /**
     * @param int[] $refusedProductIds
     */
    private function plugin(array $refusedProductIds): RefuseQtyBeforeAddPlugin
    {
        $rules = $this->createStub(QtyRules::class);
        $rules->method('getRefusalMessage')->willReturnCallback(
            static fn (int $productId) => in_array($productId, $refusedProductIds, true)
                ? __('This product is sold in multiples of %1', '6')
                : null
        );

        return new RefuseQtyBeforeAddPlugin($rules);
    }

    private function quote(bool $superMode = false): Quote
    {
        $quote = (new \ReflectionClass(Quote::class))->newInstanceWithoutConstructor();
        $quote->setData('is_super_mode', $superMode);

        return $quote;
    }

    private function simple(): Product
    {
        return $this->product(self::SIMPLE_ID, $this->createStub(Product\Type\Simple::class));
    }

    private function configurable(): Product
    {
        $type = $this->createStub(Configurable::class);
        $type->method('getProductByAttributes')->willReturn(
            $this->product(self::CHILD_ID, $this->createStub(Product\Type\Simple::class))
        );

        return $this->product(self::PARENT_ID, $type);
    }

    private function product(int $id, Product\Type\AbstractType $type): Product
    {
        $store = $this->createStub(\Magento\Store\Model\Store::class);
        $store->method('getWebsiteId')->willReturn(1);

        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getStore')->willReturn($store);
        $product->method('getTypeInstance')->willReturn($type);

        return $product;
    }
}
