<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * A facet of the current result.
 *
 * - type 'multi': options with counts, several may be selected (OR).
 * - type 'boolean': one option (value '1'), e.g. In stock.
 * - type 'range': min / max of the current result (other filters applied, not its own range) and the selected
 *   bounds; options stay empty.
 */
final class Facet
{
    public const TYPE_MULTI = 'multi';
    public const TYPE_RANGE = 'range';
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * @param FacetOption[] $options
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $type,
        public readonly array $options = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly ?float $selectedMin = null,
        public readonly ?float $selectedMax = null
    ) {
    }

    public function isSelected(): bool
    {
        if ($this->type === self::TYPE_RANGE) {
            return $this->selectedMin !== null || $this->selectedMax !== null;
        }
        foreach ($this->options as $option) {
            if ($option->selected) {
                return true;
            }
        }
        return false;
    }
}
