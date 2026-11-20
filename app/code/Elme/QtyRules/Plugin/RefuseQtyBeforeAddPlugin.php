<?php

declare(strict_types=1);

namespace Elme\QtyRules\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Refuses a requested qty before the quote changes. The stock check alone comes too late for a path that saves the cart
 * after an error, as reorder does: core has by then added the qty to a line already in the cart, or kept the refused line
 * (addToCartInvalidProduct). Admin orders are left alone, as in EnforceQtyRulesPlugin.
 */
class RefuseQtyBeforeAddPlugin
{
    public function __construct(
        private readonly QtyRules $qtyRules
    ) {
    }

    /**
     * @param Quote $subject
     * @param Product $product
     * @param DataObject|int|float|string|null $request
     * @return void
     * @throws LocalizedException
     */
    public function beforeAddProduct(Quote $subject, Product $product, $request = null): void
    {
        $qty = $request instanceof DataObject ? $request->getData('qty') : $request;

        // No qty: core asks for the MOQ itself
        if ($subject->getIsSuperMode() || !is_numeric($qty)) {
            return;
        }

        $message = $this->qtyRules->getRefusalMessage(
            (int) $this->getBoughtProduct($product, $request)->getId(),
            (float) $qty,
            (int) $product->getStore()->getWebsiteId()
        );
        if ($message !== null) {
            // The finished text: REST would answer a phrase with arguments as "multiples of %1" and a parameters list
            throw new LocalizedException(__((string) $message));
        }
    }

    /**
     * For a configurable, the simple product the customer chose.
     *
     * @param Product $product
     * @param DataObject|int|float|string|null $request
     * @return Product
     */
    private function getBoughtProduct(Product $product, $request): Product
    {
        $typeInstance = $product->getTypeInstance();
        $attributes = $request instanceof DataObject ? $request->getData('super_attribute') : null;
        if (!$typeInstance instanceof Configurable || !is_array($attributes) || !$attributes) {
            return $product;
        }

        return $typeInstance->getProductByAttributes($attributes, $product) ?: $product;
    }
}
