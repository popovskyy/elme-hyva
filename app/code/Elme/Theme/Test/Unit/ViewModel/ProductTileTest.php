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
}
