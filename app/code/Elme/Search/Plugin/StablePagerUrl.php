<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\ViewModel\ListingFilters;
use Elme\Search\ViewModel\SearchFacets;
use Magento\Theme\Block\Html\Pager;

/**
 * Page links of a listing answered by ProductSearchInterface follow the stable URL form of ListingFilters, like
 * the chips, the drawer and the toolbar: one selection gives one URL (one page cache entry) on every page.
 * Core builds them from the current request instead (code[0]=...&code[1]=... in the order of ticking).
 * Other pagers (orders, reviews) and listings with engine = magento are left alone.
 */
class StablePagerUrl
{
    private const LISTING_PAGER = 'product_list_toolbar_pager';

    public function __construct(
        private readonly ListingFilters $listingFilters,
        private readonly SearchFacets $searchFacets
    ) {
    }

    /**
     * @param Pager $subject
     * @param string $result
     * @param int|string $page
     * @return string
     */
    public function afterGetPageUrl(Pager $subject, $result, $page)
    {
        if ($subject->getNameInLayout() !== self::LISTING_PAGER || !$this->searchFacets->isActive()) {
            return $result;
        }
        return $this->listingFilters->getPageUrl((int)$page);
    }
}
