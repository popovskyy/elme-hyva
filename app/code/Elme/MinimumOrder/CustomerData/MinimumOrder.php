<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\CustomerData;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Magento\Customer\CustomerData\SectionSourceInterface;

/**
 * Customer section minimum_order: threshold and progress for mini-cart (UX-12) and cart proceed (FE-33).
 */
class MinimumOrder implements SectionSourceInterface
{
    public function __construct(
        private readonly MinimumOrderValue $minimumOrderValue
    ) {
    }

    public function getSectionData(): array
    {
        return $this->minimumOrderValue->getStatus();
    }
}
