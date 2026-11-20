<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Cart;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\CartData;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * GET /quick-order/cart/get: {success, cart: {items, totals, summary_count, items_count, can_checkout}}.
 */
class Get extends AbstractJson implements HttpGetActionInterface
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        FormKeyValidator $formKeyValidator,
        JsonSerializer $json,
        LoggerInterface $logger,
        private readonly CartData $cartData
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        return ['cart' => $this->cartData->get($customerId, $customerGroupId)];
    }
}
