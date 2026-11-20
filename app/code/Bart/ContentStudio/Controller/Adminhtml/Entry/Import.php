<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\ImportExport\Helper\Data as ImportExportHelper;

/**
 * Entry import page.
 */
class Import extends Entry implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_import';

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Bart\ContentStudio\Api\EntryRepositoryInterface $entryRepository
     * @param ImportExportHelper $importExportHelper
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Bart\ContentStudio\Api\EntryRepositoryInterface $entryRepository,
        private readonly ImportExportHelper $importExportHelper
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return Page
     */
    public function execute(): Page
    {
        $this->messageManager->addNoticeMessage($this->importExportHelper->getMaxUploadSizeMessage());

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        return $this->initPage($resultPage, __('Import')->render());
    }
}
