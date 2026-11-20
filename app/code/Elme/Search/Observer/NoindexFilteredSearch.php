<?php

declare(strict_types=1);

namespace Elme\Search\Observer;

use Elme\Search\Model\Storefront\FilteredListing;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * Search results with any facet parameter are NOINDEX,FOLLOW, like filtered category pages
 * (Plugin\NoindexFilteredListing). The search result controller renders inside execute() and returns nothing,
 * so there is no page to change after it: this runs between its loadLayout() and renderLayout().
 */
class NoindexFilteredSearch implements ObserverInterface
{
    public function __construct(
        private readonly FilteredListing $filteredListing,
        private readonly PageConfig $pageConfig
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->filteredListing->isFiltered()) {
            $this->pageConfig->setRobots('NOINDEX,FOLLOW');
        }
    }
}
