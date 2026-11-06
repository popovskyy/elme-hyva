<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * No order without a delivery method that is still on offer, at the price the customer saw: refresh() may replace the
 * method or its price, and that needs a new look from the customer.
 */
class DeliveryMethodSelected implements PlaceOrderValidatorInterface
{
    private const PRICE_PRECISION = 0.0001;

    public function __construct(
        private readonly QuoteShippingMethod $shippingMethod
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        /** @var Quote $quote */
        $selected = $this->shippingMethod->getSelected($quote);
        $price = $this->shippingMethod->getSavedPrice($quote);

        $this->shippingMethod->refresh($quote);

        $current = $this->shippingMethod->getSelected($quote);
        if ($current === '') {
            throw new CheckoutInputException(
                $selected === ''
                    ? __('Please choose a delivery method.')
                    : __('Your delivery method is no longer available. Please choose another one.'),
                QuoteShippingMethod::SECTION
            );
        }

        if ($current !== $selected
            || abs($this->shippingMethod->getSavedPrice($quote) - $price) > self::PRICE_PRECISION
        ) {
            throw new CheckoutInputException(
                __('Your delivery options have changed. Please check them and place your order again.'),
                QuoteShippingMethod::SECTION
            );
        }
    }
}
