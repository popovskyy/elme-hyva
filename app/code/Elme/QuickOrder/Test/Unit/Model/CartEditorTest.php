<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Test\Unit\Model;

use Elme\QuickOrder\Model\CartEditor;
use Elme\QuickOrder\Model\CartLine;
use Elme\QuickOrder\Model\CartLines;
use Elme\QuickOrder\Model\CartList;
use Magento\Framework\Phrase;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @see CartEditor
 */
class CartEditorTest extends TestCase
{
    private const MOQ = 'This product is sold in multiples of 6';

    /** @var array<string, float> what was saved last */
    private array $saved = [];

    public function testAddSumsTheQtyAndChecksTheNewTotal(): void
    {
        $lines = $this->createMock(CartLines::class);
        $lines->expects($this->once())->method('getRefusal')->with('A', 12.0, 7, 1)->willReturn(null);

        $refusal = (new CartEditor($this->cartList(['A' => 6.0]), $lines))->add(7, 1, 'A', 6.0);

        $this->assertNull($refusal);
        $this->assertSame(['A' => 12.0], $this->saved);
    }

    public function testRefusedAddChangesNothing(): void
    {
        $lines = $this->createStub(CartLines::class);
        $lines->method('getRefusal')->willReturn(new Phrase(self::MOQ));
        $this->saved = ['A' => 6.0];

        $refusal = (new CartEditor($this->cartList(['A' => 6.0]), $lines))->add(7, 1, 'A', 1.0);

        $this->assertSame(self::MOQ, (string) $refusal);
        $this->assertSame(['A' => 6.0], $this->saved);
    }

    public function testReplaceChecksOnlyChangedRowsAndARefusedRowKeepsItsQty(): void
    {
        $lines = $this->createMock(CartLines::class);
        // B is unchanged, C is removed: only A (changed) and D (new) are checked
        $lines->expects($this->once())->method('getLines')
            ->with(['A' => 7.0, 'D' => 6.0])
            ->willReturn([
                'A' => new CartLine('A', 7.0, refusal: new Phrase(self::MOQ)),
                'D' => new CartLine('D', 6.0),
            ]);

        $errors = (new CartEditor($this->cartList(['A' => 6.0, 'B' => 2.0, 'C' => 1.0]), $lines))
            ->save(7, 1, ['A' => 7.0, 'B' => 2.0, 'C' => 0.0, 'D' => 6.0]);

        $this->assertSame(['A' => self::MOQ], $errors);
        $this->assertSame(['A' => 6.0, 'B' => 2.0, 'C' => 0.0, 'D' => 6.0], $this->saved);
    }

    public function testARefusedNewRowIsNotSaved(): void
    {
        $lines = $this->createStub(CartLines::class);
        $lines->method('getLines')->willReturn(['N' => new CartLine('N', 1.0, refusal: new Phrase('Gone'))]);

        $errors = (new CartEditor($this->cartList([]), $lines))->save(7, 1, ['N' => 1.0]);

        $this->assertSame(['N' => 'Gone'], $errors);
        $this->assertSame([], $this->saved);
    }

    public function testMergeAddsToTheQtyInTheCart(): void
    {
        $lines = $this->createMock(CartLines::class);
        $lines->expects($this->once())->method('getLines')->with(['A' => 12.0, 'B' => 1.0])->willReturn([]);

        (new CartEditor($this->cartList(['A' => 6.0, 'C' => 3.0]), $lines))
            ->save(7, 1, ['A' => 6.0, 'B' => 1.0], CartEditor::MODE_MERGE);

        $this->assertSame(['A' => 12.0, 'C' => 3.0, 'B' => 1.0], $this->saved);
    }

    public function testANumericSkuWorks(): void
    {
        $lines = $this->createStub(CartLines::class);
        $lines->method('getLines')->willReturn([]);

        (new CartEditor($this->cartList(['12345' => 1.0]), $lines))->save(7, 1, ['12345' => 2.0]);

        $this->assertSame([12345 => 2.0], $this->saved);
    }

    /**
     * @param array<string, float> $rows
     */
    private function cartList(array $rows): CartList&Stub
    {
        $list = $this->createStub(CartList::class);
        $list->method('get')->willReturn($rows);
        $list->method('replace')->willReturnCallback(function (int $customerId, array $saved): void {
            $this->saved = $saved;
        });

        return $list;
    }
}
