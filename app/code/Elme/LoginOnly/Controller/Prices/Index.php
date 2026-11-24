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
 * Response: {"1": {"final": 12.5, "regular": 15, "promo": true, "list": 15, "trade": null, "was": 15,
 * "final_formatted": "$12.50", "regular_formatted": "$15.00", "list_formatted": "$15.00", "trade_formatted": null,
 * "was_formatted": "$15.00"}}. Guests get 401 and no prices. `was` is the struck-through price (null = no sale),
 * `list` the price list price and `trade` the customer's agreed price (null hides that line); see
 * CustomerPriceProviderInterface.
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
            $row = $price + ['list' => null, 'trade' => null, 'was' => null];
            $row['final_formatted'] = $this->priceCurrency->format($price['final'], false);
            $row['regular_formatted'] = $this->priceCurrency->format($price['regular'], false);
            // an empty price stays null: format(null) would print $0.00
            foreach (['list', 'trade', 'was'] as $key) {
                $row[$key . '_formatted'] = $row[$key] !== null
                    ? $this->priceCurrency->format($row[$key], false)
                    : null;
            }
            $data[$productId] = $row;
        }

        return $result->setData((object) $data);
    }
}
