<?php

declare(strict_types=1);

namespace Elme\ThirdParty\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConsentMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'defer_only',
                'label' => __('After interactive (no CMP yet)'),
            ],
            [
                'value' => 'event',
                'label' => __('Wait for elme:consent-marketing event'),
            ],
            [
                'value' => 'cookie_restriction',
                'label' => __('Magento cookie restriction allow'),
            ],
        ];
    }
}
