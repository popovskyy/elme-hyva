<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Block;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\ViewModel\LocationPage;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class LocationView extends Template
{
    public function __construct(
        Context $context,
        private readonly LocationPage $locationPage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLocationPage(): LocationPage
    {
        return $this->locationPage;
    }

    public function getLocation(): ?LocationInterface
    {
        return $this->locationPage->getLocation();
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $location = $this->getLocation();
        if (!$location) {
            return $this;
        }

        $page = $this->locationPage;
        $presenter = $page->getPresenter();
        $canonical = $presenter->getLocationUrl($location);

        $this->pageConfig->getTitle()->set($page->getMetaTitle($location));
        $this->pageConfig->setDescription($page->getMetaDescription($location));
        $this->pageConfig->setRobots('INDEX,FOLLOW');
        $this->pageConfig->addRemotePageAsset(
            $canonical,
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );
        $this->pageConfig->setMetadata('og:type', 'website');
        $this->pageConfig->setMetadata('og:title', $page->getMetaTitle($location));
        $this->pageConfig->setMetadata('og:description', $page->getMetaDescription($location));
        $this->pageConfig->setMetadata('og:url', $canonical);
        $this->pageConfig->setMetadata('og:locale', 'en_AU');

        $breadcrumbs = $this->getLayout()->getBlock('breadcrumbs');
        if ($breadcrumbs) {
            $breadcrumbs->setData('skip_ld_json_schema', true);
            $breadcrumbs->addCrumb('home', [
                'label' => __('Home'),
                'title' => __('Go to Home Page'),
                'link' => $this->_storeManager->getStore()->getBaseUrl(),
            ]);
            $breadcrumbs->addCrumb('showroom_locator', [
                'label' => __('Showroom locator'),
                'title' => __('Showroom locator'),
                'link' => $presenter->getListUrl(),
            ]);
            $breadcrumbs->addCrumb('location', [
                'label' => $location->getName(),
                'title' => $location->getName(),
            ]);
        }

        return $this;
    }
}
