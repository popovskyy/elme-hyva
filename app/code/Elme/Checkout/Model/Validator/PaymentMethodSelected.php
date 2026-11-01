<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * No order without a payment method that is still on offer.
 */
class PaymentMethodSelected implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly QuotePaymentMethod $paymentMethod
    ) {
    }

    public function validate(CartInterface $quote): void
    {
        /** @var Quote $quote */
        $this->paymentMethod->refresh($quote);

        if ($this->paymentMethod->getSelected($quote) === '') {
            throw new CheckoutInputException(
                __('Please choose a payment method.'),
                QuotePaymentMethod::SECTION
            );
        }
    }
}
