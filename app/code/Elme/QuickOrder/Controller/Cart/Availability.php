<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Cart;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\CartLines;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * POST /quick-order/cart/availability, JSON {form_key, items: [{sku, qty}]}: could these qty be ordered now?
 * Answer: {success, availability: {sku: {available, message, min, step, max}}}. Changes nothing.
 */
class Availability extends AbstractJson implements HttpPostActionInterface
{
    private const MAX_ROWS = 40;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        FormKeyValidator $formKeyValidator,
        JsonSerializer $json,
        LoggerInterface $logger,
        private readonly CartLines $cartLines
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        $availability = [];
        foreach ($this->cartLines->getLines($this->getRows(self::MAX_ROWS), $customerId, $customerGroupId) as $line) {
            $availability[$line->sku] = [
                'available' => $line->isAvailable(),
                'message' => $line->refusal !== null ? (string) $line->refusal : null,
                'min' => $line->rules['min'] ?? null,
                'step' => $line->rules['step'] ?? null,
                'max' => $line->rules['max'] ?? null,
            ];
        }

        return ['availability' => (object) $availability];
    }
}
