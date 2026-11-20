<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Elme\Checkout\Api\SectionProviderInterface;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * The delivery section: the rates for the quote's delivery address, prices ex GST.
 */
class DeliveryProvider implements SectionProviderInterface
{
    public function __construct(
        private readonly QuoteShippingMethod $shippingMethod,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function getData(CartInterface $quote): array
    {
        /** @var Quote $quote */
        $lockReason = $this->shippingMethod->getLockReason($quote);
        if ($lockReason !== null) {
            return ['locked' => (string) $lockReason, 'rates' => [], 'selected' => ''];
        }

        $rates = [];
        foreach ($this->shippingMethod->getRates($quote) as $rate) {
            $price = (float) $rate->getPriceExclTax();
            $rates[] = [
                'code' => $this->shippingMethod->getCode($rate),
                'carrier_code' => (string) $rate->getCarrierCode(),
                'method_code' => (string) $rate->getMethodCode(),
                'carrier_title' => (string) $rate->getCarrierTitle(),
                'method_title' => (string) $rate->getMethodTitle(),
                'price' => $price,
                'price_formatted' => (string) $this->priceCurrency->format($price, false),
            ];
        }

        return ['locked' => '', 'rates' => $rates, 'selected' => $this->shippingMethod->getSelected($quote)];
    }
}
