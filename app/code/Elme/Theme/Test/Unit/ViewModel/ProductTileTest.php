<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\ProductTile;
use PHPUnit\Framework\TestCase;

/**
 * @see ProductTile
 */
class ProductTileTest extends TestCase
{
    public function testStockLabelThresholds(): void
    {
        $this->assertNull(ProductTile::stockLevelForQty(0));
        $this->assertNull(ProductTile::stockLevelForQty(-3));
        $this->assertSame(ProductTile::STOCK_LOW, ProductTile::stockLevelForQty(1));
        $this->assertSame(ProductTile::STOCK_LOW, ProductTile::stockLevelForQty(5));
        $this->assertSame(ProductTile::STOCK_LOW, ProductTile::stockLevelForQty(9));
        $this->assertSame(ProductTile::STOCK_IN, ProductTile::stockLevelForQty(10));
        $this->assertSame(ProductTile::STOCK_IN, ProductTile::stockLevelForQty(50));
    }

    public function testQtyStepsInMoqMultiples(): void
    {
        $this->assertSame(['min' => 1.0, 'step' => 1.0], ProductTile::qtyRules(0, false, 0));
        $this->assertSame(['min' => 6.0, 'step' => 6.0], ProductTile::qtyRules(6, false, 0));
        $this->assertSame(['min' => 6.0, 'step' => 6.0], ProductTile::qtyRules(6, false, 4));
    }

    public function testQtyIncrementsWinWhenSwitchedOn(): void
    {
        $this->assertSame(['min' => 4.0, 'step' => 2.0], ProductTile::qtyRules(4, true, 2));
        $this->assertSame(['min' => 6.0, 'step' => 2.0], ProductTile::qtyRules(5, true, 2));
        $this->assertSame(['min' => 12.0, 'step' => 12.0], ProductTile::qtyRules(1, true, 12));
    }
}
