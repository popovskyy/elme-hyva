<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

/**
 * POST elme_checkout/action/details - {delivery_instructions}.
 */
class Details extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        return $this->checkoutManagement->saveDetails($cartId, $this->getPayload());
    }
}
