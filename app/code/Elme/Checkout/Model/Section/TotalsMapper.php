<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\TotalSegmentInterface;

/**
 * Magento total segments to order summary rows, in Magento's own order.
 *
 * A segment with an entry in $rowTitles uses the row template of that code and the Elme title. Anything else
 * (freight fees and so on) keeps its own title and uses the generic row, so a new total needs no change here.
 */
class TotalsMapper
{
    private const GENERIC_ROW = 'generic';

    private const SHIPPING = 'shipping';

    /**
     * @param PriceCurrencyInterface $priceCurrency
     * @param string[] $rowTitles Segment code => title
     */
    public function __construct(
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly array $rowTitles = []
    ) {
    }

    /**
     * @param TotalSegmentInterface[] $segments
     * @param bool $hasShippingMethod Until a method is chosen the shipping row says so instead of $0.00
     * @return array<int, array{code: string, row: string, title: string, value: float, formatted: string}>
     */
    public function map(array $segments, bool $hasShippingMethod): array
    {
        $rows = [];
        foreach ($segments as $segment) {
            $code = (string) $segment->getCode();
            $value = (float) $segment->getValue();
            $hasOwnRow = isset($this->rowTitles[$code]);
            $rows[] = [
                'code' => $code,
                'row' => $hasOwnRow ? $code : self::GENERIC_ROW,
                'title' => $hasOwnRow ? (string) __($this->rowTitles[$code]) : (string) $segment->getTitle(),
                'value' => $value,
                'formatted' => $code === self::SHIPPING && !$hasShippingMethod
                    ? (string) __('Calculated at delivery')
                    : (string) $this->priceCurrency->format($value, false),
            ];
        }

        return $rows;
    }
}
