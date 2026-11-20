<?php

declare(strict_types=1);

namespace Elme\Search\Model\Storefront;

use Elme\Search\Model\Facet\FacetConfig;
use Magento\Framework\App\RequestInterface;

/**
 * Is the current URL a filtered listing: does it carry any facet parameter (a code from
 * etc/elme_search_facets.xml)? Keyed on the URL, not the engine and not on whether the value applies: with
 * engine = magento, or with ?in_stock=0, the parameter does nothing and the page is a duplicate of the clean one.
 */
class FilteredListing
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly FacetConfig $facetConfig
    ) {
    }

    public function isFiltered(): bool
    {
        foreach (array_keys($this->facetConfig->getFacets()) as $code) {
            $value = $this->request->getParam($code);
            if ($value !== null && $value !== '' && $value !== []) {
                return true;
            }
        }
        return false;
    }
}
