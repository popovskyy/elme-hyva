<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class State implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'NSW', 'label' => __('NSW')],
            ['value' => 'VIC', 'label' => __('VIC')],
            ['value' => 'QLD', 'label' => __('QLD')],
            ['value' => 'SA', 'label' => __('SA')],
            ['value' => 'WA', 'label' => __('WA')],
            ['value' => 'TAS', 'label' => __('TAS')],
            ['value' => 'ACT', 'label' => __('ACT')],
            ['value' => 'NT', 'label' => __('NT')],
        ];
    }
}
