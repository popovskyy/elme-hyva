<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Plugin;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Magento\Quote\Model\Quote;

/**
 * Core checks a quote against the store default only (sales/minimum_order/amount) before it opens /checkout, so a
 * customer with a lower minimum of their own was sent back to the cart, where Proceed to checkout was enabled.
 * Wherever core asks, the answer is the customer's minimum, the same one the cart and the place-order validator use.
 */
class UseCustomerMinimumPlugin
{
    public function __construct(
        private readonly MinimumOrderValue $minimumOrderValue
    ) {
    }

    /**
     * @param Quote $subject
     * @param callable $proceed
     * @param bool $multishipping
     * @return bool
     */
    public function aroundValidateMinimumAmount(Quote $subject, callable $proceed, $multishipping = false): bool
    {
        return $this->minimumOrderValue->evaluateQuote($subject)['met'];
    }
}
