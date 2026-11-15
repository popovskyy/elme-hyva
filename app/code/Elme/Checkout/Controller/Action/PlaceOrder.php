<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Exception\LocalizedException;

/**
 * POST elme_checkout/action/placeorder - {agreements: [id, ...]}. Answers with the URL of the success page.
 */
class PlaceOrder extends AbstractAction
{
    /**
     * @param int $cartId
     * @return array{redirect: string}
     * @throws LocalizedException
     */
    protected function run(int $cartId): array
    {
        $this->checkoutManagement->placeOrder($cartId, $this->getPayload());

        return ['redirect' => $this->urlBuilder->getUrl('checkout/onepage/success')];
    }

    /**
     * Cart already converted and the session still holds that order: send the customer to the success page.
     */
    protected function resolveEmptyCart(): ?JsonResult
    {
        return $this->redirectToPlacedOrder();
    }

    /**
     * A validator may have changed the quote (a carrier that is gone), so the page gets the state it has to fix.
     */
    protected function inputErrorData(int $cartId): array
    {
        try {
            return ['state' => $this->checkoutManagement->getState($cartId)->toArray()];
        } catch (LocalizedException) {
            return [];
        }
    }
}
