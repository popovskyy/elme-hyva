<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * POST elme_checkout/action/paymentmethod - {method}.
 */
class PaymentMethod extends AbstractAction
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
        private readonly CheckoutManagementInterface $checkoutManagement
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
        return $this->checkoutManagement->setPaymentMethod($cartId, (string) ($this->getPayload()['method'] ?? ''));
    }
}
