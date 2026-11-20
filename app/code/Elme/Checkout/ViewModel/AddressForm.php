<?php

declare(strict_types=1);

namespace Elme\Checkout\ViewModel;

use Elme\Checkout\Model\Address\QuoteAddresses;
use Magento\Directory\Model\ResourceModel\Region\CollectionFactory;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class AddressForm implements ArgumentInterface
{
    public function __construct(
        private readonly CollectionFactory $regionCollectionFactory
    ) {
    }

    /**
     * @return array<int, string> Region id => name
     */
    public function getRegions(): array
    {
        $regions = [];
        foreach ($this->regionCollectionFactory->create()->addCountryFilter(QuoteAddresses::COUNTRY_ID) as $region) {
            $regions[(int) $region->getRegionId()] = (string) $region->getName();
        }

        return $regions;
    }
}
