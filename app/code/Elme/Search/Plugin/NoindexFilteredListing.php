<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Model\Facet\FacetConfig;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Result\Page;

/**
 * A listing with any facet parameter in the URL (a code from etc/elme_search_facets.xml) is a filtered state:
 * NOINDEX,FOLLOW, and the canonical is the clean category URL (catalog/seo/category_canonical_tag; core would
 * append ?p=N, which is dropped here for filtered pages only).
 * Keyed on the URL, not the engine: with engine = magento the parameters do nothing and the page is a duplicate.
 * Paging and sort without facets are left alone.
 */
class NoindexFilteredListing
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly FacetConfig $facetConfig
    ) {
    }

    /**
     * @param object $subject category view or search result controller
     * @param mixed $result
     * @return mixed
     */
    public function afterExecute(object $subject, mixed $result): mixed
    {
        if ($result instanceof Page && $this->hasFacetParam()) {
            $result->getConfig()->setRobots('NOINDEX,FOLLOW');
        }
        return $result;
    }

    public function afterGetCanonicalUrl(CategoryHelper $subject, string $result, string $categoryUrl): string
    {
        if (!$this->hasFacetParam()) {
            return $result;
        }
        // core: escapeUrl($categoryUrl . '?p=' . $p); the category URL itself has no query string
        return (string)preg_replace('/\?p=.*$/', '', $result);
    }

    private function hasFacetParam(): bool
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
