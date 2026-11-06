<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Elme\Checkout\Api\SectionProviderInterface;
use Elme\Checkout\Model\Address\AddressBook;
use Elme\Checkout\Model\Address\AddressFormatter;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * The address section: the address book, and what the quote delivers and invoices to.
 */
class AddressProvider implements SectionProviderInterface
{
    public function __construct(
        private readonly AddressBook $addressBook,
        private readonly AddressFormatter $formatter,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function getData(CartInterface $quote): array
    {
        /** @var Quote $quote */
        $customerId = (int) $quote->getCustomerId();
        $customer = $this->customerRepository->getById($customerId);

        $addresses = [];
        foreach ($this->addressBook->getAddresses($customerId) as $address) {
            $addressId = (int) $address->getId();
            $addresses[$addressId] = ['id' => $addressId]
                + $this->formatter->fromCustomerAddress($address)
                + [
                    'is_default_shipping' => $addressId === (int) $customer->getDefaultShipping(),
                    'is_default_billing' => $addressId === (int) $customer->getDefaultBilling(),
                ];
        }

        $shipping = $quote->getShippingAddress();

        return [
            'addresses' => array_values($addresses),
            'shipping' => $this->getSelected($shipping, $addresses),
            'billing' => $this->getSelected($quote->getBillingAddress(), $addresses)
                + ['same_as_delivery' => (bool) $shipping->getSameAsBilling()],
        ];
    }

    /**
     * @param QuoteAddress $address
     * @param array<int, mixed> $addresses The address book by id
     * @return array{is_set: bool, address_id: int, address: array<string, mixed>|null}
     */
    private function getSelected(QuoteAddress $address, array $addresses): array
    {
        if (!$address->getFirstname()) {
            return ['is_set' => false, 'address_id' => 0, 'address' => null];
        }

        $addressId = (int) $address->getCustomerAddressId();

        return [
            'is_set' => true,
            'address_id' => isset($addresses[$addressId]) ? $addressId : 0,
            'address' => $this->formatter->fromQuoteAddress($address),
        ];
    }
}
