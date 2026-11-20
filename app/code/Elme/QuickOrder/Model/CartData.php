<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Elme\QtyRules\Model\QtyRules;
use Elme\Theme\ViewModel\ProductTile;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Api\TaxCalculationInterface;

/**
 * The cart as the browser gets it: rows with live data and the totals. Prices are ex GST; the GST and the total
 * come from here, so the browser never holds the tax rate. Nothing here touches the quote.
 */
class CartData
{
    /** @var array<int, float> GST % per product tax class */
    private array $rates = [];

    public function __construct(
        private readonly CartList $cartList,
        private readonly CartLines $cartLines,
        private readonly QtyRules $qtyRules,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly TaxCalculationInterface $taxCalculation,
        private readonly Image $imageHelper,
        private readonly MinimumOrderValue $minimumOrderValue,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    /**
     * @param array<string, string> $errors sku => message of rows a save or add refused, shown on that row
     * @return array<string, mixed>
     */
    public function get(int $customerId, int $customerGroupId, array $errors = []): array
    {
        return $this->present($this->cartList->get($customerId), $customerId, $customerGroupId, $errors);
    }

    /**
     * The same row payload as the cart, for any sku => qty map (a named shopping list).
     *
     * @param array<string, float> $rows
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    public function present(array $rows, int $customerId, int $customerGroupId, array $errors = []): array
    {
        $lines = $this->cartLines->getLines($rows, $customerId, $customerGroupId);

        $items = [];
        $subtotal = 0.0;
        $gst = 0.0;
        $summaryCount = 0.0;
        foreach ($lines as $line) {
            $items[] = $this->getItem($line, $errors[$line->sku] ?? null);
            $summaryCount += $line->qty;
            if ($line->isAvailable()) {
                $subtotal += $line->getRowTotal();
                $gst += $line->getRowTotal() * $this->getRate($line->product, $customerId) / 100;
            }
        }
        $subtotal = round($subtotal, 2);
        $gst = round($gst, 2);
        $customer = $this->getCustomer($customerId);
        $minimumOrder = $this->minimumOrderValue->evaluate($subtotal, $customer);
        $rowsOk = $items && !array_filter($lines, static fn (CartLine $line): bool => !$line->isAvailable());

        return [
            'items' => $items,
            'summary_count' => $summaryCount,
            'items_count' => count($items),
            'can_checkout' => $rowsOk && $minimumOrder['met'],
            'minimum_order' => $minimumOrder,
            'totals' => [
                'subtotal' => $subtotal,
                'gst' => $gst,
                'total' => round($subtotal + $gst, 2),
                'subtotal_formatted' => $this->format($subtotal),
                'gst_formatted' => $this->format($gst),
                'total_formatted' => $this->format($subtotal + $gst),
            ],
        ];
    }

    private function getCustomer(int $customerId): ?CustomerInterface
    {
        try {
            return $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException | LocalizedException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getItem(CartLine $line, ?string $error): array
    {
        $product = $line->product;
        $shown = $line->parent ?? $product;
        $price = $line->price;
        $level = $line->salableQty !== null ? ProductTile::stockLevelForQty($line->salableQty) : null;

        return [
            'sku' => $line->sku,
            'qty' => $line->qty,
            'product_id' => $product ? (int) $product->getId() : null,
            'name' => $product ? (string) $product->getName() : $line->sku,
            'url' => $shown && (int) $shown->getStatus() === Product\Attribute\Source\Status::STATUS_ENABLED
                ? (string) $shown->getProductUrl()
                : null,
            'image' => $product ? $this->getImageUrl($product, $line->parent) : null,
            'price' => $price === null ? null : [
                'final' => $price['final'],
                'regular' => $price['regular'],
                'final_formatted' => $this->format($price['final']),
                'regular_formatted' => $this->format($price['regular']),
                'has_discount' => !empty($price['promo']),
            ],
            'row_total' => $line->getRowTotal(),
            'row_total_formatted' => $this->format($line->getRowTotal()),
            'min' => $line->rules['min'] ?? 1.0,
            'step' => $line->rules['step'] ?? 1.0,
            'max' => $line->rules['max'] ?? null,
            'stock' => [
                'qty' => $line->salableQty,
                'qty_formatted' => $line->salableQty !== null ? $this->qtyRules->formatQty($line->salableQty) : null,
                'level' => $level,
                'label' => match ($level) {
                    ProductTile::STOCK_IN => (string) __('In Stock'),
                    ProductTile::STOCK_LOW => (string) __('Low Stock'),
                    default => null,
                },
            ],
            'available' => $line->isAvailable(),
            'message' => $error ?? ($line->refusal !== null ? (string) $line->refusal : null),
        ];
    }

    private function getImageUrl(Product $product, ?Product $parent): string
    {
        $own = (string) $product->getData('small_image');
        $source = ($own === '' || $own === 'no_selection') && $parent ? $parent : $product;

        return (string) $this->imageHelper->init($source, 'cart_page_product_thumbnail')->getUrl();
    }

    private function getRate(?Product $product, int $customerId): float
    {
        $taxClassId = $product ? (int) $product->getData('tax_class_id') : 0;

        return $this->rates[$taxClassId] ??= (float) $this->taxCalculation->getCalculatedRate($taxClassId, $customerId);
    }

    private function format(float $amount): string
    {
        return (string) $this->priceCurrency->format($amount, false);
    }
}
