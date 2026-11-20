<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

use Elme\CustomerPricing\Model\ResourceModel\ProntoPriceData;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Magento customer -> Pronto customer, through the customer attribute pronto_customer_id.
 */
class ProntoCustomerResolver
{
    public const ATTRIBUTE_CODE = 'pronto_customer_id';

    /**
     * @var array<int, ProntoCustomer|null>
     */
    private array $resolved = [];

    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProntoPriceData $priceData
    ) {
    }

    public function get(int $customerId): ?ProntoCustomer
    {
        if (!array_key_exists($customerId, $this->resolved)) {
            $this->resolved[$customerId] = $this->resolve($customerId);
        }

        return $this->resolved[$customerId];
    }

    private function resolve(int $customerId): ?ProntoCustomer
    {
        try {
            $attribute = $this->customerRepository->getById($customerId)->getCustomAttribute(self::ATTRIBUTE_CODE);
        } catch (NoSuchEntityException) {
            return null;
        }

        $prontoId = trim((string) $attribute?->getValue());
        if ($prontoId === '') {
            return null;
        }

        $mapping = $this->priceData->getCustomerMapping($prontoId);

        return new ProntoCustomer($prontoId, $mapping['marketing_flag'] ?? null, $mapping['region_group'] ?? null);
    }
}
