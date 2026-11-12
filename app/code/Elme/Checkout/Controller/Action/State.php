<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Model\PlacedOrderRecovery;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * POST elme_checkout/action/state - the current checkout state.
 */
class State extends AbstractAction
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        CustomerUrl $customerUrl,
        CheckoutSession $checkoutSession,
        UrlInterface $urlBuilder,
        Json $json,
        LoggerInterface $logger,
        FormKeyValidator $formKeyValidator,
        private readonly CheckoutManagementInterface $checkoutManagement,
        private readonly PlacedOrderRecovery $placedOrderRecovery
    ) {
        parent::__construct(
            $request,
            $jsonFactory,
            $customerSession,
            $customerUrl,
            $checkoutSession,
            $urlBuilder,
            $json,
            $logger,
            $formKeyValidator
        );
    }

    protected function run(int $cartId): CheckoutStateInterface
    {
        return $this->checkoutManagement->getState($cartId);
    }

    /**
     * pageshow / recovery after a lost place-order answer: same success redirect as PlaceOrder.
     */
    protected function resolveEmptyCart(): ?JsonResult
    {
        return $this->placedOrderRecovery->getSuccessUrl() !== null
            ? $this->redirectTo('checkout/onepage/success')
            : null;
    }
}
