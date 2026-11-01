<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Quote\Api\Data\CartInterface;

class PlaceOrderValidatorPool
{
    /**
     * @param PlaceOrderValidatorInterface[] $validators
     */
    public function __construct(private readonly array $validators = [])
    {
    }

    /**
     * @param CartInterface $quote
     * @return void
     * @throws CheckoutInputException The first failed check
     */
    public function validate(CartInterface $quote): void
    {
        foreach ($this->validators as $validator) {
            $validator->validate($quote);
        }
    }
}
