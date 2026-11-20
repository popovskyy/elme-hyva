<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Content type picker shown before creating a new entry.
 */
class NewAction extends Entry implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_save';

    /**
     * @return Page
     */
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        return $this->initPage($resultPage, __('New Entry')->render());
    }
}
