<?php

declare(strict_types=1);

namespace Elme\Checkout\Api;

use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * One check before place order. Add one through di.xml on Elme\Checkout\Model\PlaceOrderValidatorPool.
 */
interface PlaceOrderValidatorInterface
{
    /**
     * @param CartInterface $quote
     * @return void
     * @throws CheckoutInputException When the order cannot be placed; says which section and fields to fix
     */
    public function validate(CartInterface $quote): void;
}
