<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\List;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\CartData;
use Elme\QuickOrder\Model\CartList;
use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * GET /quick-order/list/get?name= — one named shopping list, with the same row payload as the cart.
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
        private readonly QuickOrderList $lists,
        private readonly CartList $cartList,
        private readonly CartData $cartData
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        $name = trim((string) $this->request->getParam('name'));
        if ($name === '' || $name === CartList::NAME || mb_strlen($name) > 255) {
            throw new LocalizedException(__('List name is required.'));
        }

        $found = $this->lists->exists($customerId, $name);

        return [
            'found' => $found,
            'name' => $name,
            'list' => $this->cartData->present(
                $found ? $this->cartList->get($customerId, $name) : [],
                $customerId,
                $customerGroupId
            ),
        ];
    }
}
