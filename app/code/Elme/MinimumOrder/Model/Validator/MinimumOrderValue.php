<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Elme\MinimumOrder\Model\MinimumOrderValue as MinimumOrderValueService;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Refuse place order when the quote subtotal (ex GST) is below the customer's minimum.
 * The message carries the remaining amount so the UI can show "Add $X more".
 */
class MinimumOrderValue implements PlaceOrderValidatorInterface
{
    public function __construct(
        private readonly MinimumOrderValueService $minimumOrderValue
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        $status = $this->minimumOrderValue->evaluateQuote($quote);
        if ($status['met']) {
            return;
        }

        throw new CheckoutInputException(
            __('Add %1 more to place your order.', $status['remaining_formatted'])
        );
    }
}
