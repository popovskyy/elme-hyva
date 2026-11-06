<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\Registry;

abstract class Location extends Action
{
    public const ADMIN_RESOURCE = 'Elme_StoreLocator::location';

    public function __construct(
        Context $context,
        protected readonly Registry $coreRegistry
    ) {
        parent::__construct($context);
    }

    protected function initPage(Page $resultPage): Page
    {
        $resultPage->setActiveMenu('Elme_StoreLocator::location')
            ->addBreadcrumb(__('Content'), __('Content'))
            ->addBreadcrumb(__('Showroom Locations'), __('Showroom Locations'));

        return $resultPage;
    }
}
