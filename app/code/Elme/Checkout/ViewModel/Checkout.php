<?php

declare(strict_types=1);

namespace Elme\Checkout\ViewModel;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The first checkout state, rendered into the page so the first paint needs no request.
 */
class Checkout implements ArgumentInterface
{
    public function __construct(
        private readonly CheckoutManagementInterface $checkoutManagement,
        private readonly CheckoutSession $checkoutSession,
        private readonly Json $json
    ) {
    }

    public function getInitialStateJson(): string
    {
        $state = $this->checkoutManagement->getState((int) $this->checkoutSession->getQuote()->getId());

        return (string) $this->json->serialize($state->toArray());
    }
}
