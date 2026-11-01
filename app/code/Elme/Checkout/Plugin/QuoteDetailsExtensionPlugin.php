<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * The order details as extension attributes of a cart from the repository. getActive() and getActiveForCustomer() go
 * through the two getters here.
 */
class QuoteDetailsExtensionPlugin
{
    public function afterGet(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->addDetails($quote);
    }

    public function afterGetForCustomer(CartRepositoryInterface $subject, CartInterface $quote): CartInterface
    {
        return $this->addDetails($quote);
    }

    /**
     * @param CartRepositoryInterface $subject
     * @param SearchResultsInterface $result
     * @return SearchResultsInterface
     */
    public function afterGetList(CartRepositoryInterface $subject, $result)
    {
        foreach ($result->getItems() as $quote) {
            $this->addDetails($quote);
        }

        return $result;
    }

    private function addDetails(CartInterface $quote): CartInterface
    {
        /** @var Quote $quote */
        $extension = $quote->getExtensionAttributes();
        $extension->setCustomerReference($quote->getData(OrderDetailsInterface::CUSTOMER_REFERENCE));
        $extension->setDeliveryInstructions($quote->getData(OrderDetailsInterface::DELIVERY_INSTRUCTIONS));
        $quote->setExtensionAttributes($extension);

        return $quote;
    }
}
