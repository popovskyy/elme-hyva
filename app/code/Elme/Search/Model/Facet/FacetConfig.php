<?php

declare(strict_types=1);

namespace Elme\Search\Model\Facet;

use Elme\Search\Api\Data\Facet;
use Magento\Framework\Config\DataInterface;

/**
 * Facet definitions from etc/elme_search_facets.xml (merged over all modules, cached), in display order.
 */
class FacetConfig
{
    /** @var FacetDefinition[]|null */
    private ?array $facets = null;

    public function __construct(private readonly DataInterface $data)
    {
    }

    /**
     * @return FacetDefinition[] keyed by code
     */
    public function getFacets(): array
    {
        if ($this->facets !== null) {
            return $this->facets;
        }
        $rows = (array)$this->data->get('facets', []);
        $facets = [];
        foreach ($rows as $code => $row) {
            if (filter_var($row['disabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $facets[(string)$code] = new FacetDefinition(
                (string)$code,
                (string)($row['type'] ?? Facet::TYPE_MULTI),
                (string)($row['label'] ?? $code),
                (int)($row['sortOrder'] ?? 0),
                (int)($row['size'] ?? 50),
                (string)($row['optionSort'] ?? FacetDefinition::SORT_COUNT),
                filter_var($row['quick'] ?? false, FILTER_VALIDATE_BOOLEAN),
                max(1, (int)($row['quickLimit'] ?? FacetDefinition::DEFAULT_QUICK_LIMIT))
            );
        }
        uasort($facets, static fn (FacetDefinition $a, FacetDefinition $b): int => $a->sortOrder <=> $b->sortOrder);
        return $this->facets = $facets;
    }

    public function get(string $code): ?FacetDefinition
    {
        return $this->getFacets()[$code] ?? null;
    }
}
