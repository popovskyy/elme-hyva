<?php

declare(strict_types=1);

namespace Elme\QtyRules\Test\Unit\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Elme\QtyRules\Plugin\EnforceQtyRulesPlugin;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;

/**
 * @see EnforceQtyRulesPlugin
 */
class EnforceQtyRulesPluginTest extends TestCase
{
    public function testAllowedQtyKeepsTheAnswerOfMagento(): void
    {
        $qtyRules = $this->createMock(QtyRules::class);
        $qtyRules->expects($this->once())->method('getRefusalMessage')->with(10, 12.0, 1)->willReturn(null);
        $answer = new DataObject(['has_error' => true, 'message' => 'Not enough items for sale']);

        $result = (new EnforceQtyRulesPlugin($qtyRules))
            ->afterCheckQuoteItemQty($this->createStub(StockStateInterface::class), $answer, '10', 12, 12, 12, '1');

        $this->assertSame('Not enough items for sale', $result->getMessage());
    }

    public function testRefusalReplacesTheAnswerOfMagento(): void
    {
        $qtyRules = $this->createStub(QtyRules::class);
        $qtyRules->method('getRefusalMessage')->willReturn(__('This product is sold in multiples of %1', '6'));
        $answer = new DataObject(['has_error' => true, 'message' => 'The fewest you may purchase is 6.']);

        $result = (new EnforceQtyRulesPlugin($qtyRules))
            ->afterCheckQuoteItemQty($this->createStub(StockStateInterface::class), $answer, 10, 4, 4, 4);

        $this->assertTrue($result->getHasError());
        $this->assertSame('This product is sold in multiples of 6', (string) $result->getMessage());
        $this->assertSame('qty', $result->getQuoteMessageIndex());
        $this->assertSame('Please correct the quantity for some products.', (string) $result->getQuoteMessage());
    }

    public function testChecksTheRowQtyNotTheTotalOfTheCart(): void
    {
        $qtyRules = $this->createMock(QtyRules::class);
        $qtyRules->expects($this->once())->method('getRefusalMessage')->with(10, 6.0, null)->willReturn(null);

        (new EnforceQtyRulesPlugin($qtyRules))
            ->afterCheckQuoteItemQty($this->createStub(StockStateInterface::class), new DataObject(), 10, 6, 18, 6);
    }
}
