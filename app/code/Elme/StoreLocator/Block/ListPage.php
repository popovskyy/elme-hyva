<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Block;

use Elme\StoreLocator\ViewModel\LocatorPage;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ListPage extends Template
{
    public function __construct(
        Context $context,
        private readonly LocatorPage $locatorPage,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getLocatorPage(): LocatorPage
    {
        return $this->locatorPage;
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $page = $this->locatorPage;
        $this->pageConfig->getTitle()->set($page->getMetaTitle());
        $this->pageConfig->setDescription($page->getMetaDescription());
        $this->pageConfig->setRobots('INDEX,FOLLOW');
        $this->pageConfig->addRemotePageAsset(
            $page->getCanonicalUrl(),
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );
        $this->pageConfig->setMetadata('og:type', 'website');
        $this->pageConfig->setMetadata('og:title', $page->getMetaTitle());
        $this->pageConfig->setMetadata('og:description', $page->getMetaDescription());
        $this->pageConfig->setMetadata('og:url', $page->getCanonicalUrl());
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
            ]);
        }

        return $this;
    }
}
