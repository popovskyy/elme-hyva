<?php

declare(strict_types=1);

namespace Elme\QtyRules\Test\Unit\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Elme\QtyRules\Plugin\RefuseCartUpdatePlugin;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\TestCase;

/**
 * @see RefuseCartUpdatePlugin
 */
class RefuseCartUpdatePluginTest extends TestCase
{
    public function testAllowedLinesKeepTheTypedQtyInsteadOfTheSuggestedOne(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->never())->method('addErrorMessage');
        $typed = [5 => ['qty' => '12']];

        $result = (new RefuseCartUpdatePlugin($this->rulesRefusing([]), $messages))
            ->afterSuggestItemsQty($this->cartWithItems([5]), [5 => ['qty' => 18.0, 'before_suggest_qty' => 12.0]], $typed);

        $this->assertSame($typed, $result);
    }

    public function testRefusedLineIsDroppedWithTheMessageOnce(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->once())->method('addErrorMessage')->with('This product is sold in multiples of 6');
        $typed = [5 => ['qty' => '7'], 6 => ['qty' => '12'], 7 => ['qty' => '8']];

        $result = (new RefuseCartUpdatePlugin($this->rulesRefusing([7.0, 8.0]), $messages))
            ->afterSuggestItemsQty($this->cartWithItems([5, 6, 7]), [], $typed);

        $this->assertSame([6 => ['qty' => '12']], $result);
    }

    public function testRemovedAndUnknownLinesAreLeftToCore(): void
    {
        $messages = $this->createStub(ManagerInterface::class);
        $typed = [5 => ['qty' => '7', 'remove' => '1'], 99 => ['qty' => '7']];

        $result = (new RefuseCartUpdatePlugin($this->rulesRefusing([7.0]), $messages))
            ->afterSuggestItemsQty($this->cartWithItems([5]), [], $typed);

        $this->assertSame($typed, $result);
    }

    /**
     * @param float[] $refused
     */
    private function rulesRefusing(array $refused): QtyRules
    {
        $rules = $this->createStub(QtyRules::class);
        $rules->method('getRefusalForQuoteItem')->willReturnCallback(
            static fn (Item $item, float $qty) => in_array($qty, $refused, true)
                ? __('This product is sold in multiples of %1', '6')
                : null
        );

        return $rules;
    }

    /**
     * @param int[] $itemIds
     */
    private function cartWithItems(array $itemIds): Cart
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getItemById')->willReturnCallback(
            fn ($id) => in_array($id, $itemIds, true) ? $this->createStub(Item::class) : false
        );
        $cart = $this->createStub(Cart::class);
        $cart->method('getQuote')->willReturn($quote);

        return $cart;
    }
}
