<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;

/**
 * POST elme_checkout/action/state - the current checkout state.
 */
class State extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        return $this->checkoutManagement->getState($cartId);
    }

    /**
     * pageshow / recovery after a lost place-order answer: same success redirect as PlaceOrder.
     */
    protected function resolveEmptyCart(): ?JsonResult
    {
        return $this->redirectToPlacedOrder();
    }
}
