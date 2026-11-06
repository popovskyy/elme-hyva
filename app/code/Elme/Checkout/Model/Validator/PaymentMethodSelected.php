<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * No order without a payment method that is still on offer, and none on a method refresh() swapped in by itself.
 */
class PaymentMethodSelected implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly QuotePaymentMethod $paymentMethod
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        /** @var Quote $quote */
        $selected = $this->paymentMethod->getSelected($quote);

        $this->paymentMethod->refresh($quote);

        $current = $this->paymentMethod->getSelected($quote);
        if ($current === '') {
            throw new CheckoutInputException(
                $selected === ''
                    ? __('Please choose a payment method.')
                    : __('Your payment method is no longer available. Please choose another one.'),
                QuotePaymentMethod::SECTION
            );
        }

        if ($current !== $selected) {
            throw new CheckoutInputException(
                __('Your payment options have changed. Please check them and place your order again.'),
                QuotePaymentMethod::SECTION
            );
        }
    }
}
