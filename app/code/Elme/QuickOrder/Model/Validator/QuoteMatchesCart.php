<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\QuickOrder\Model\CartList;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * The quote was built from cart_list when checkout opened. If the cart changed since (another tab, another
 * device), placing the order would buy what the customer no longer has in the cart: reload checkout first.
 */
class QuoteMatchesCart implements PlaceOrderValidatorInterface
{
    private const EPSILON = 0.0001;

    public function __construct(
        private readonly CartList $cartList
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        $customerId = (int) $quote->getCustomer()->getId();
        if (!$customerId || !$quote instanceof Quote) {
            return;
        }

        $inQuote = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            // a configurable line has the sku of the chosen simple product, as its cart row
            $sku = (string) $item->getSku();
            $inQuote[$sku] = ($inQuote[$sku] ?? 0.0) + (float) $item->getQty();
        }

        $inCart = $this->cartList->get($customerId);
        if (count($inQuote) !== count($inCart)) {
            $this->refuse();
        }
        foreach ($inCart as $sku => $qty) {
            if (abs(($inQuote[(string) $sku] ?? 0.0) - $qty) > self::EPSILON) {
                $this->refuse();
            }
        }
    }

    /**
     * @throws CheckoutInputException
     */
    private function refuse(): void
    {
        throw new CheckoutInputException(
            __('Your cart has changed since checkout was opened. Please reload the page to check your order.')
        );
    }
}
