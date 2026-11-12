<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Address;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Sales\Model\Order\Address as OrderAddress;

/**
 * One shape for a saved address and a quote address, as the checkout shows them.
 */
class AddressFormatter
{
    /**
     * @param AddressInterface $address
     * @return array<string, string>
     */
    public function fromCustomerAddress(AddressInterface $address): array
    {
        return $this->format(
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
            (string) $address->getCompany(),
            $address->getStreet() ?: [],
            (string) $address->getCity(),
            (string) $address->getRegion()?->getRegionCode(),
            (string) $address->getPostcode(),
            (string) $address->getTelephone()
        );
    }

    /**
     * @param QuoteAddress $address
     * @return array<string, string>
     */
    public function fromQuoteAddress(QuoteAddress $address): array
    {
        return $this->format(
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
            (string) $address->getCompany(),
            $address->getStreet() ?: [],
            (string) $address->getCity(),
            (string) $address->getRegionCode(),
            (string) $address->getPostcode(),
            (string) $address->getTelephone()
        );
    }

    /**
     * @param OrderAddress $address
     * @return array<string, string>
     */
    public function fromOrderAddress(OrderAddress $address): array
    {
        return $this->format(
            (string) $address->getFirstname(),
            (string) $address->getLastname(),
            (string) $address->getCompany(),
            $address->getStreet() ?: [],
            (string) $address->getCity(),
            (string) $address->getRegionCode(),
            (string) $address->getPostcode(),
            (string) $address->getTelephone()
        );
    }

    /**
     * @param string $firstname
     * @param string $lastname
     * @param string $company
     * @param string[] $street
     * @param string $city
     * @param string $region
     * @param string $postcode
     * @param string $telephone
     * @return array<string, string>
     */
    private function format(
        string $firstname,
        string $lastname,
        string $company,
        array $street,
        string $city,
        string $region,
        string $postcode,
        string $telephone
    ): array {
        $name = trim($firstname . ' ' . $lastname);
        $streetLine = implode(', ', array_filter(array_map('strval', $street), 'strlen'));
        $locality = trim($city . ' ' . $region . ' ' . $postcode);

        return [
            'name' => $name,
            'company' => $company,
            'street' => $streetLine,
            'locality' => $locality,
            'telephone' => $telephone,
            'line' => implode(', ', array_filter([$name, $company, $streetLine, $locality], 'strlen')),
        ];
    }
}
