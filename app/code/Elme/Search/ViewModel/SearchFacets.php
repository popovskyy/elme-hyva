<?php

declare(strict_types=1);

namespace Elme\Search\ViewModel;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Storefront\CurrentSearch;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Facets of the page's product listing for FE-21 (filters) and FE-23 (search results).
 *
 * Only filled when the listing came from ProductSearchInterface (engine = opensearch) and after the product
 * collection has loaded, so render it after the product list (e.g. a block below "category.products.list"
 * in the layout, or read it lazily in the sidebar template).
 */
class SearchFacets implements ArgumentInterface
{
    public function __construct(private readonly CurrentSearch $currentSearch)
    {
    }

    public function isActive(): bool
    {
        return $this->currentSearch->getResult() !== null;
    }

    /**
     * @return Facet[] in display order (etc/elme_search_facets.xml); facets with nothing in this result are left out
     */
    public function getFacets(): array
    {
        return $this->currentSearch->getResult()?->facets ?? [];
    }

    public function getTotal(): int
    {
        return $this->currentSearch->getResult()?->total ?? 0;
    }

    /**
     * The selection as it is in the URL: code => values (multi / boolean) and code => [min, max] (range).
     *
     * @return array{filters: array<string, string[]>, ranges: array<string, array{min: ?float, max: ?float}>}
     */
    public function getSelection(): array
    {
        $request = $this->currentSearch->getRequest();
        return [
            'filters' => $request?->filters ?? [],
            'ranges' => $request?->ranges ?? [],
        ];
    }

    public function getRequest(): ?SearchRequest
    {
        return $this->currentSearch->getRequest();
    }
}
