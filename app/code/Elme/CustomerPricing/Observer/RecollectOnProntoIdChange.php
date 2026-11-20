<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Observer;

use Elme\CustomerPricing\Model\ProntoCustomerResolver;
use Elme\CustomerPricing\Model\QuoteRecollect;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * customer_save_after_data_object (admin, storefront, API): a customer whose pronto_customer_id changed has other
 * prices now, so their active cart is flagged to reprice on its next load.
 */
class RecollectOnProntoIdChange implements ObserverInterface
{
    public function __construct(
        private readonly QuoteRecollect $quoteRecollect
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getEvent()->getData('customer_data_object');
        $original = $observer->getEvent()->getData('orig_customer_data_object');
        if (!$customer instanceof CustomerInterface || !$original instanceof CustomerInterface) {
            return;
        }

        if ($this->getProntoId($customer) !== $this->getProntoId($original)) {
            $this->quoteRecollect->byCustomerIds([(int) $customer->getId()]);
        }
    }

    private function getProntoId(CustomerInterface $customer): string
    {
        return trim((string) $customer->getCustomAttribute(ProntoCustomerResolver::ATTRIBUTE_CODE)?->getValue());
    }
}
