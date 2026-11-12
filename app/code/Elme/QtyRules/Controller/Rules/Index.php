<?php

declare(strict_types=1);

namespace Elme\QtyRules\Controller\Rules;

use Elme\QtyRules\Model\QtyRules;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * GET /qty-rules/rules?ids=1,2 - live MOQ rules (max and salable change with stock). Logged-in only.
 *
 * Response: {"1": {"min": 6, "step": 6, "max": 30, "salable": true}}. max is null when nothing limits it.
 */
class Index implements HttpGetActionInterface
{
    private const MAX_IDS = 50;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QtyRules $qtyRules
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData([]);
        }

        $ids = array_slice(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) $this->request->getParam('ids', ''))
        ))), 0, self::MAX_IDS);

        $data = [];
        foreach ($ids as $id) {
            try {
                $product = $this->productRepository->getById($id);
            } catch (NoSuchEntityException) {
                continue;
            }
            $rules = $this->qtyRules->getRules($product);
            $data[$id] = [
                'min' => $rules['min'],
                'step' => $rules['step'],
                'max' => $rules['max'],
                'salable' => $rules['salable'],
            ];
        }

        return $result->setData((object) $data);
    }
}
