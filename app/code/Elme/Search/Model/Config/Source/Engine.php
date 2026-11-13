<?php

declare(strict_types=1);

namespace Elme\Search\Model\Config\Source;

use Elme\Search\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Engine implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ENGINE_MAGENTO, 'label' => __('Magento catalog search (default)')],
            ['value' => Config::ENGINE_OPENSEARCH, 'label' => __('Elme product search (ProductSearchInterface)')],
        ];
    }
}
