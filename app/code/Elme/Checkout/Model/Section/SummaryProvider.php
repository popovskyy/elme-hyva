<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Elme\Checkout\Api\SectionProviderInterface;
use Magento\Checkout\Model\Cart\ImageProvider;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\TotalsItemInterface;
use Magento\Quote\Model\Quote;

/**
 * The order summary: items and totals, ex GST with GST as its own row.
 */
class SummaryProvider implements SectionProviderInterface
{
    private const GRAND_TOTAL = 'grand_total';

    public function __construct(
        private readonly CartTotalRepositoryInterface $cartTotalRepository,
        private readonly ImageProvider $imageProvider,
        private readonly TotalsMapper $totalsMapper,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Json $json
    ) {
    }

    public function getData(CartInterface $quote): array
    {
        /** @var Quote $quote */
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        $totals = $this->cartTotalRepository->get((int) $quote->getId());
        $rows = $this->totalsMapper->map(
            $totals->getTotalSegments() ?: [],
            (bool) $quote->getShippingAddress()->getShippingMethod()
        );

        $grandTotal = '';
        foreach ($rows as $row) {
            if ($row['code'] === self::GRAND_TOTAL) {
                $grandTotal = $row['formatted'];
            }
        }

        return [
            'items' => $this->getItems($quote, $totals->getItems() ?: []),
            'items_qty' => (float) $quote->getItemsQty(),
            'totals' => $rows,
            'grand_total_formatted' => $grandTotal,
        ];
    }

    /**
     * @param Quote $quote
     * @param TotalsItemInterface[] $totalsItems
     * @return array<int, array<string, mixed>>
     */
    private function getItems(Quote $quote, array $totalsItems): array
    {
        $optionsByItem = [];
        foreach ($totalsItems as $totalsItem) {
            $optionsByItem[(int) $totalsItem->getItemId()] = $totalsItem->getOptions();
        }

        $images = $this->imageProvider->getImages((int) $quote->getId());

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemId = (int) $item->getId();
            $items[] = [
                'id' => $itemId,
                'name' => (string) $item->getName(),
                'sku' => (string) $item->getSku(),
                'qty' => (float) $item->getQty(),
                'options' => $this->getOptions((string) ($optionsByItem[$itemId] ?? '')),
                'image' => $images[$itemId] ?? null,
                'row_total' => (float) $item->getRowTotal(),
                'row_total_formatted' => (string) $this->priceCurrency->format((float) $item->getRowTotal(), false),
            ];
        }

        return $items;
    }

    /**
     * Option values come back from the core item converter escaped for HTML; the template prints plain text.
     *
     * @param string $options JSON list of {label, value}
     * @return array<int, array{label: string, value: string}>
     */
    private function getOptions(string $options): array
    {
        if ($options === '') {
            return [];
        }

        $result = [];
        foreach ($this->json->unserialize($options) as $option) {
            $result[] = [
                'label' => $this->plainText((string) ($option['label'] ?? '')),
                'value' => $this->plainText((string) ($option['value'] ?? '')),
            ];
        }

        return $result;
    }

    private function plainText(string $value): string
    {
        return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
