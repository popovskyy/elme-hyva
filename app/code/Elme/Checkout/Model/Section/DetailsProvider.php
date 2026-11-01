<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Elme\Checkout\Api\OrderDetailsInterface;
use Elme\Checkout\Api\SectionProviderInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * The order details section: what the customer has entered so far.
 */
class DetailsProvider implements SectionProviderInterface
{
    public function getData(CartInterface $quote): array
    {
        /** @var Quote $quote */
        return [
            OrderDetailsInterface::CUSTOMER_REFERENCE =>
                (string) $quote->getData(OrderDetailsInterface::CUSTOMER_REFERENCE),
            OrderDetailsInterface::DELIVERY_INSTRUCTIONS =>
                (string) $quote->getData(OrderDetailsInterface::DELIVERY_INSTRUCTIONS),
        ];
    }
}
