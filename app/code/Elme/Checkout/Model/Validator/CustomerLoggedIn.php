<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Elme sells to account customers only: no guest orders, whichever way the quote got here.
 */
class CustomerLoggedIn implements PlaceOrderValidatorInterface
{
    public function validate(CartInterface $quote, array $input = []): void
    {
        if (!$quote->getCustomer()->getId() || $quote->getCustomerIsGuest()) {
            throw new CheckoutInputException(__('Please login to access this page.'));
        }
    }
}
