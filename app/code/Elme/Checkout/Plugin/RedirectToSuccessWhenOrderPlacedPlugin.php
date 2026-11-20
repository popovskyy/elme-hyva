<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Elme\Checkout\Model\PlacedOrderRecovery;
use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * A reload of /checkout after a placed order (answer lost) must open the success page, not the empty cart.
 */
class RedirectToSuccessWhenOrderPlacedPlugin
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly PlacedOrderRecovery $placedOrderRecovery,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function aroundExecute(Index $subject, callable $proceed): ResultInterface
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() && $quote->hasItems() && !$quote->getHasError() && $quote->validateMinimumAmount()) {
            return $proceed();
        }

        $successUrl = $this->placedOrderRecovery->getSuccessUrl();
        if ($successUrl === null) {
            return $proceed();
        }

        return $this->redirectFactory->create()->setUrl($successUrl);
    }
}
