<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\ImportExport\File\Download as FileDownload;
use Bart\ContentStudio\Model\ImportExport\Processor\Export as ExportProcessor;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Downloads a single entry as JSON.
 */
class Export extends Entry implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_export';

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Bart\ContentStudio\Api\EntryRepositoryInterface $entryRepository
     * @param LoggerInterface $logger
     * @param FileDownload $fileDownload
     * @param ExportProcessor $exportProcessor
     */
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Bart\ContentStudio\Api\EntryRepositoryInterface $entryRepository,
        private readonly LoggerInterface $logger,
        private readonly FileDownload $fileDownload,
        private readonly ExportProcessor $exportProcessor
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return ResponseInterface|Redirect
     */
    public function execute()
    {
        $entryId = (int)$this->getRequest()->getParam('entry_id');

        try {
            $entry = $this->entryRepository->getById($entryId);

            return $this->fileDownload->generateDownloadFile(
                (string)$entry->getIdentifier(),
                $this->exportProcessor->getExportData($entryId)
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->critical($exception);
            $this->messageManager->addErrorMessage(
                __('An error occurred while exporting entry %1.', $entryId)->render()
            );
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath('*/*/');
    }
}
