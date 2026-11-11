<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Address;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * The customer's saved addresses, always scoped to that customer.
 */
class AddressBook
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressInterfaceFactory $addressFactory,
        private readonly RegionInterfaceFactory $regionFactory
    ) {
    }

    /**
     * @param int $customerId
     * @return AddressInterface[]
     */
    public function getAddresses(int $customerId): array
    {
        return $this->customerRepository->getById($customerId)->getAddresses() ?: [];
    }

    /**
     * @param int $customerId
     * @param int $addressId
     * @return AddressInterface
     * @throws NoSuchEntityException When the address is gone or belongs to someone else
     */
    public function getAddress(int $customerId, int $addressId): AddressInterface
    {
        $address = $this->addressRepository->getById($addressId);
        if ((int) $address->getCustomerId() !== $customerId) {
            throw new NoSuchEntityException(__('The address does not belong to this customer.'));
        }

        return $address;
    }

    public function getDefaultShipping(int $customerId): ?AddressInterface
    {
        return $this->findDefault($customerId, (int) $this->customerRepository->getById($customerId)->getDefaultShipping());
    }

    public function getDefaultBilling(int $customerId): ?AddressInterface
    {
        return $this->findDefault($customerId, (int) $this->customerRepository->getById($customerId)->getDefaultBilling());
    }

    /**
     * Saves a quote address to the book. Same rule as core at place order: a customer's first address becomes
     * the default for whatever has no default yet.
     */
    public function add(int $customerId, QuoteAddress $source): AddressInterface
    {
        $customer = $this->customerRepository->getById($customerId);
        $region = $this->regionFactory->create()
            ->setRegionId((int) $source->getRegionId())
            ->setRegion((string) $source->getRegion())
            ->setRegionCode((string) $source->getRegionCode());

        $address = $this->addressFactory->create()
            ->setCustomerId($customerId)
            ->setFirstname((string) $source->getFirstname())
            ->setLastname((string) $source->getLastname())
            ->setCompany((string) $source->getCompany())
            ->setStreet($source->getStreet())
            ->setCity((string) $source->getCity())
            ->setRegion($region)
            ->setRegionId((int) $source->getRegionId())
            ->setPostcode((string) $source->getPostcode())
            ->setCountryId((string) $source->getCountryId())
            ->setTelephone((string) $source->getTelephone())
            ->setIsDefaultShipping(!$customer->getDefaultShipping())
            ->setIsDefaultBilling(!$customer->getDefaultBilling());

        return $this->addressRepository->save($address);
    }

    /**
     * Removes a book entry that belongs to this customer. Used when a checkout save fails after the book was written.
     *
     * @param int $customerId
     * @param int $addressId
     * @return void
     * @throws NoSuchEntityException When the address is gone or belongs to someone else
     */
    public function delete(int $customerId, int $addressId): void
    {
        $this->addressRepository->delete($this->getAddress($customerId, $addressId));
    }

    private function findDefault(int $customerId, int $addressId): ?AddressInterface
    {
        if (!$addressId) {
            return null;
        }

        try {
            return $this->getAddress($customerId, $addressId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
