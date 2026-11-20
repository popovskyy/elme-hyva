<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Address\AddressValidator;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * No order without a complete delivery and billing address. The address form may be closed at this point, so the error
 * is one message for the section, not one per field.
 */
class AddressesComplete implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly AddressValidator $addressValidator
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        /** @var Quote $quote */
        $shipping = $quote->getShippingAddress();
        if (!$shipping->getFirstname()) {
            throw new CheckoutInputException(__('Please add a delivery address.'), QuoteAddresses::SECTION);
        }
        if ($this->addressValidator->getErrors($shipping)) {
            throw new CheckoutInputException(
                __('Your delivery address is missing some details. Please check it.'),
                QuoteAddresses::SECTION
            );
        }

        $billing = $quote->getBillingAddress();
        if (!$billing->getFirstname() || $this->addressValidator->getErrors($billing)) {
            throw new CheckoutInputException(
                __('Your billing address is missing some details. Please check it.'),
                QuoteAddresses::SECTION
            );
        }
    }
}
