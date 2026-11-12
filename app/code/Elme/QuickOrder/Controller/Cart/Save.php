<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Cart;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\CartData;
use Elme\QuickOrder\Model\CartEditor;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * POST /quick-order/cart/save, JSON {form_key, mode: "replace"|"merge", items: [{sku, qty}]}.
 * Answer: {success, errors: {sku: message}, cart}. Refused rows keep their old qty; the rest is saved, so the
 * answer is still a 200 and the browser shows the cart it holds.
 */
class Save extends AbstractJson implements HttpPostActionInterface
{
    private const MAX_ROWS = 500;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        FormKeyValidator $formKeyValidator,
        JsonSerializer $json,
        LoggerInterface $logger,
        private readonly CartEditor $cartEditor,
        private readonly CartData $cartData
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        $mode = ($this->getPayload()['mode'] ?? '') === CartEditor::MODE_MERGE
            ? CartEditor::MODE_MERGE
            : CartEditor::MODE_REPLACE;
        $errors = $this->cartEditor->save($customerId, $customerGroupId, $this->getRows(self::MAX_ROWS), $mode);

        return [
            'errors' => (object) $errors,
            'cart' => $this->cartData->get($customerId, $customerGroupId, $errors),
        ];
    }
}
