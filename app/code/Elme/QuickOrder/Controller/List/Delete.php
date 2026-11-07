<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\List;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\SavedLists;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * POST /quick-order/list/delete — JSON {form_key, id} or form-encoded id + form_key.
 */
class Delete extends AbstractJson implements HttpPostActionInterface
{
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        FormKeyValidator $formKeyValidator,
        JsonSerializer $json,
        LoggerInterface $logger,
        private readonly SavedLists $savedLists
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        $id = (int) ($this->getPayload()['id'] ?? $this->request->getParam('id'));
        try {
            $this->savedLists->delete($customerId, $id);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('List not found.'));
        }

        return [];
    }
}
