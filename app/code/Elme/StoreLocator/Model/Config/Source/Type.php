<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Config\Source;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Type implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => LocationInterface::TYPE_SHOWROOM,
                'label' => __('Showroom'),
            ],
            [
                'value' => LocationInterface::TYPE_DISTRIBUTION_PICKUP,
                'label' => __('Distribution and pick-up'),
            ],
            [
                'value' => LocationInterface::TYPE_TRADE_ONLY,
                'label' => __('Trade-only'),
            ],
        ];
    }
}
