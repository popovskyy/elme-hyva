<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Content Studio entry grid.
 */
class Index extends Entry implements HttpGetActionInterface
{
    /**
     * @return Page
     */
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        return $this->initPage($resultPage);
    }
}
