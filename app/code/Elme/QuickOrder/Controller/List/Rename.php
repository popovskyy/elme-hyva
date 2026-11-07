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
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * POST /quick-order/list/rename - JSON {form_key, name, new_name}. A name another list has is refused
 * ("A list with this name already exists."). Answers the new name as stored.
 */
class Rename extends AbstractJson implements HttpPostActionInterface
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
        $payload = $this->getPayload();

        return [
            'name' => $this->savedLists->rename(
                $customerId,
                (string) ($payload['name'] ?? ''),
                (string) ($payload['new_name'] ?? '')
            ),
        ];
    }
}
