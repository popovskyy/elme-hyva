<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Section;

use Elme\Checkout\Model\Section\TotalsMapper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;
use PHPUnit\Framework\TestCase;

class TotalsMapperTest extends TestCase
{
    private TotalsMapper $mapper;

    protected function setUp(): void
    {
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(
            static fn (float $amount): string => '$' . number_format($amount, 2)
        );

        $this->mapper = new TotalsMapper($priceCurrency, [
            'subtotal' => 'Subtotal (ex GST)',
            'shipping' => 'Shipping',
            'tax' => 'GST',
            'grand_total' => 'Total (incl GST)',
        ]);
    }

    public function testKnownSegmentsGetTheirOwnRowAndElmeTitleInMagentoOrder(): void
    {
        $rows = $this->mapper->map([
            $this->segment('subtotal', 'Subtotal', 100.0),
            $this->segment('tax', 'Tax', 10.0),
            $this->segment('grand_total', 'Grand Total', 110.0),
        ], true);

        $this->assertSame(
            [
                ['code' => 'subtotal', 'row' => 'subtotal', 'title' => 'Subtotal (ex GST)', 'value' => 100.0, 'formatted' => '$100.00'],
                ['code' => 'tax', 'row' => 'tax', 'title' => 'GST', 'value' => 10.0, 'formatted' => '$10.00'],
                ['code' => 'grand_total', 'row' => 'grand_total', 'title' => 'Total (incl GST)', 'value' => 110.0, 'formatted' => '$110.00'],
            ],
            $rows
        );
    }

    public function testUnknownSegmentKeepsItsTitleAndUsesTheGenericRow(): void
    {
        [$row] = $this->mapper->map([$this->segment('freight_fee', 'Freight fee', 12.5)], true);

        $this->assertSame('generic', $row['row']);
        $this->assertSame('Freight fee', $row['title']);
        $this->assertSame('$12.50', $row['formatted']);
    }

    public function testShippingWithoutAMethodSaysSoInsteadOfZero(): void
    {
        [$row] = $this->mapper->map([$this->segment('shipping', 'Shipping & Handling', 0.0)], false);

        $this->assertSame('Calculated at delivery', $row['formatted']);
        $this->assertSame(0.0, $row['value']);
    }

    public function testShippingWithAMethodShowsItsAmount(): void
    {
        [$row] = $this->mapper->map([$this->segment('shipping', 'Shipping & Handling', 25.0)], true);

        $this->assertSame('$25.00', $row['formatted']);
    }

    private function segment(string $code, string $title, float $value): TotalSegmentInterface
    {
        $segment = $this->createStub(TotalSegmentInterface::class);
        $segment->method('getCode')->willReturn($code);
        $segment->method('getTitle')->willReturn($title);
        $segment->method('getValue')->willReturn($value);

        return $segment;
    }
}
