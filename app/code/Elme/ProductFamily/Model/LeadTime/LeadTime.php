<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model\LeadTime;

/**
 * Lead time of one product: whole weeks and the storefront text ("With you in 14 weeks").
 */
final class LeadTime
{
    public function __construct(
        public readonly int $weeks,
        public readonly string $label
    ) {
    }

    /**
     * @return array{weeks: int, label: string}
     */
    public function toArray(): array
    {
        return ['weeks' => $this->weeks, 'label' => $this->label];
    }
}
