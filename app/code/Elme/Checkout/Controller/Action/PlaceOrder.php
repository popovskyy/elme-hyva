<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * POST elme_checkout/action/placeorder - {agreements: [id, ...]}. Answers with the URL of the success page.
 */
class PlaceOrder extends AbstractAction
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
