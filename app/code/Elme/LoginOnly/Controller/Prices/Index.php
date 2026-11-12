<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Controller\Prices;

use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * GET /customer-price/prices?ids=1,2,3 - prices of the current customer, never cached.
 *
 * Response: {"1": {"final": 12.5, "regular": 15, "promo": true, "final_formatted": "$12.50",
 * "regular_formatted": "$15.00"}}. Guests get 401 and no prices. `promo` means was/now is a sale.
 */
class Index implements HttpGetActionInterface
{
    private const MAX_IDS = 200;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CustomerPriceProviderInterface $priceProvider,
        private readonly PriceCurrencyInterface $priceCurrency
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

        $prices = $this->priceProvider->getPrices(
            $ids,
            (int) $this->customerSession->getCustomerId(),
            (int) $this->customerSession->getCustomerGroupId()
        );

        $data = [];
        foreach ($prices as $productId => $price) {
            $data[$productId] = $price + [
                'final_formatted' => $this->priceCurrency->format($price['final'], false),
                'regular_formatted' => $this->priceCurrency->format($price['regular'], false),
            ];
        }

        return $result->setData((object) $data);
    }
}
