<?php

declare(strict_types=1);

namespace Elme\Theme\Observer;

use Elme\Theme\ViewModel\HelpPages;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Page\Config as PageConfig;

/**
 * FE-45 / FE-93: add cms_page_view_help handle + help-page body classes on curated help URLs.
 */
class AddHelpPageHandle implements ObserverInterface
{
    public const HANDLE = 'cms_page_view_help';

    public function __construct(
        private readonly CmsPage $cmsPage,
        private readonly RequestInterface $request,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageConfig $pageConfig,
        private readonly HelpPages $helpPages
    ) {
    }

    public function execute(Observer $observer): void
    {
        $fullActionName = (string) $observer->getEvent()->getData('full_action_name');
        if ($fullActionName !== 'cms_page_view') {
            return;
        }

        $identifier = $this->resolveIdentifier();
        if (!$this->helpPages->isHelpPage($identifier)) {
            return;
        }

        /** @var \Magento\Framework\View\LayoutInterface $layout */
        $layout = $observer->getEvent()->getData('layout');
        $layout->getUpdate()->addHandle(self::HANDLE);

        $this->pageConfig->addBodyClass('help-page');
        if ($this->helpPages->hasBanner($identifier)) {
            $this->pageConfig->addBodyClass('help-page-banner');
        }
    }

    private function resolveIdentifier(): string
    {
        $identifier = (string) $this->cmsPage->getIdentifier();
        if ($identifier !== '') {
            return $identifier;
        }

        $pageId = (int) $this->request->getParam('page_id');
        if ($pageId <= 0) {
            return '';
        }

        try {
            return (string) $this->pageRepository->getById($pageId)->getIdentifier();
        } catch (LocalizedException) {
            return '';
        }
    }
}
