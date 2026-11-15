<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Model\Storefront\FilteredListing;
use Magento\Catalog\Controller\Category\View as CategoryView;
use Magento\Catalog\Helper\Category as CategoryHelper;
use Magento\Framework\View\Result\Page;

/**
 * A category page with any facet parameter in the URL (Model\Storefront\FilteredListing) is a filtered state:
 * NOINDEX,FOLLOW, and the canonical is the clean category URL (catalog/seo/category_canonical_tag; core would
 * append ?p=N, which is dropped here for filtered pages only).
 * Paging and sort without facets are left alone. Search results: Observer\NoindexFilteredSearch.
 */
class NoindexFilteredListing
{
    public function __construct(private readonly FilteredListing $filteredListing)
    {
    }

    /**
     * @param CategoryView $subject
     * @param mixed $result a Page, or a forward when the category cannot be shown
     * @return mixed
     */
    public function afterExecute(CategoryView $subject, mixed $result): mixed
    {
        if ($result instanceof Page && $this->filteredListing->isFiltered()) {
            $result->getConfig()->setRobots('NOINDEX,FOLLOW');
        }
        return $result;
    }

    public function afterGetCanonicalUrl(CategoryHelper $subject, string $result, string $categoryUrl): string
    {
        if (!$this->filteredListing->isFiltered()) {
            return $result;
        }
        // core: escapeUrl($categoryUrl . '?p=' . $p); the category URL itself has no query string
        return (string)preg_replace('/\?p=.*$/', '', $result);
    }
}
