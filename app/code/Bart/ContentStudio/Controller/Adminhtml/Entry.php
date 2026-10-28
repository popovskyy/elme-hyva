<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Base controller for the Content Studio admin section.
 */
abstract class Entry extends Action
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     */
    public function __construct(
        Context $context,
        protected readonly PageFactory $resultPageFactory,
        protected readonly EntryRepositoryInterface $entryRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @param Page $resultPage
     * @param string $title
     * @return Page
     */
    protected function initPage(Page $resultPage, string $title = ''): Page
    {
        // Our full-bleed pages strip the admin chrome, and setActiveMenu() calls
        // ->setActive() on the 'menu' block without a null check, so it fatals
        // once that block is gone. addBreadcrumb() below guards internally
        // already; this mirrors that for the menu.
        if ($resultPage->getLayout()->getBlock('menu')) {
            $resultPage->setActiveMenu(self::ADMIN_RESOURCE);
        }

        $resultPage->addBreadcrumb(__('Content Studio')->render(), __('Content Studio')->render());

        $pageTitle = $resultPage->getConfig()->getTitle();
        $pageTitle->prepend(__('Content Studio')->render());

        if ($title !== '') {
            $pageTitle->prepend($title);
        }

        return $resultPage;
    }
}
