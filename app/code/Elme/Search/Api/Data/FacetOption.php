<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * One facet value. value is what goes back into SearchRequest::$filters; count counts result items
 * (families collapsed) with this value when the other facets' filters are applied.
 */
final class FacetOption
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly int $count,
        public readonly bool $selected = false
    ) {
    }
}
