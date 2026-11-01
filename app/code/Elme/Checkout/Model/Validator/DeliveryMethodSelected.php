<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * No order without a delivery method that is still on offer.
 */
class DeliveryMethodSelected implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly QuoteShippingMethod $shippingMethod
    ) {
    }

    public function validate(CartInterface $quote): void
    {
        /** @var Quote $quote */
        $this->shippingMethod->refresh($quote);

        if ($this->shippingMethod->getSelected($quote) === '') {
            throw new CheckoutInputException(
                __('Please choose a delivery method.'),
                QuoteShippingMethod::SECTION
            );
        }
    }
}
