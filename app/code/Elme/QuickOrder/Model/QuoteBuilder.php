<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The one rule between the cart and checkout: whenever /checkout opens, the quote is rebuilt from cart_list. Old
 * quote lines go, every row CartLines allows is added, and the refused rows come back with their reason.
 * No merge, no "only if empty", no skip flag: the old site's hydration rules made checkout ignore the cart.
 * cart_list itself is never changed here.
 */
class QuoteBuilder
{
    public function __construct(
        private readonly CartList $cartList,
        private readonly CartLines $cartLines,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $quoteRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, string> sku => why that row is not in the quote
     */
    public function rebuild(int $customerId, int $customerGroupId): array
    {
        /** @var Quote $quote */
        $quote = $this->checkoutSession->getQuote();
        $quote->removeAllItems();

        $refused = [];
        foreach ($this->cartLines->getLines($this->cartList->get($customerId), $customerId, $customerGroupId) as $line) {
            if ($line->refusal !== null) {
                $refused[$line->sku] = (string) $line->refusal;
                continue;
            }
            $error = $this->addLine($quote, $line);
            if ($error !== null) {
                $refused[$line->sku] = $error;
            }
        }

        $quote->collectTotals();
        // Stock and qty checks that only run with the quote (e.g. stock reserved since the cart was read)
        $removed = false;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getHasError()) {
                $refused[(string) $item->getSku()] = (string) $item->getMessage() ?: (string) __('This product cannot be ordered online.');
                $quote->deleteItem($item);
                $removed = true;
            }
        }
        if ($removed) {
            $quote->setTotalsCollectedFlag(false)->collectTotals();
        }

        $this->quoteRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote);

        return $refused;
    }

    /**
     * @return string|null Why the row could not be added
     */
    private function addLine(Quote $quote, CartLine $line): ?string
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        try {
            $product = $this->productRepository->get($line->sku, false, $storeId, true);
            $request = ['qty' => $line->qty];
            // A configurable row holds the simple product; it goes in under its parent, as from the product page
            if ($line->parent) {
                $parent = $this->productRepository->getById((int) $line->parent->getId(), false, $storeId, true);
                $request['super_attribute'] = $this->getSuperAttributes($parent, $product);
                $product = $parent;
            }

            $result = $quote->addProduct($product, new DataObject($request));

            return is_string($result) ? $result : null;
        } catch (LocalizedException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->warning('Elme_QuickOrder: a cart row could not be added to the quote.', [
                'sku' => $line->sku,
                'exception' => $e,
            ]);

            return (string) __('This product cannot be ordered online.');
        }
    }

    /**
     * @return array<int, int|string> attribute id => the simple product's option value
     */
    private function getSuperAttributes(Product $parent, Product $child): array
    {
        /** @var Configurable $type */
        $type = $parent->getTypeInstance();
        $values = [];
        foreach ($type->getConfigurableAttributesAsArray($parent) as $attribute) {
            $values[(int) $attribute['attribute_id']] = $child->getData($attribute['attribute_code']);
        }

        return $values;
    }
}
