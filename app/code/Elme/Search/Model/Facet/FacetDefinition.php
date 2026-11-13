<?php

declare(strict_types=1);

namespace Elme\Search\Model\Facet;

use Elme\Search\Api\Data\Facet;

/**
 * One row of etc/elme_search_facets.xml.
 */
final class FacetDefinition
{
    public const SORT_COUNT = 'count';
    public const SORT_LABEL = 'label';

    public function __construct(
        public readonly string $code,
        public readonly string $type = Facet::TYPE_MULTI,
        public readonly string $label = '',
        public readonly int $sortOrder = 0,
        public readonly int $size = 50,
        public readonly string $optionSort = self::SORT_COUNT
    ) {
    }
}
