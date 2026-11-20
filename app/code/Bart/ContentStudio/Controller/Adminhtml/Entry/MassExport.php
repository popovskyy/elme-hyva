<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\ImportExport\ExportFileNamer;
use Bart\ContentStudio\Model\ImportExport\File\ZipDownload;
use Bart\ContentStudio\Model\ImportExport\Processor\Export as ExportProcessor;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;

/**
 * Mass-exports selected entries into a single ZIP archive.
 */
class MassExport extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_export';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ExportProcessor $exportProcessor
     * @param ZipDownload $zipDownload
     * @param ExportFileNamer $fileNamer
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EntryRepositoryInterface $entryRepository,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ExportProcessor $exportProcessor,
        private readonly ZipDownload $zipDownload,
        private readonly ExportFileNamer $fileNamer,
        private readonly DateTime $dateTime,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return ResponseInterface|Redirect
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $fileNames = $this->fileNamer->nameFiles($collection->getItems());
            $files = [];

            foreach ($fileNames as $entryId => $fileName) {
                $files[$fileName . '.json'] = $this->exportProcessor->getExportData($entryId);
            }

            if ($files === []) {
                throw new LocalizedException(__('Please select entries to export.'));
            }

            $fileName = sprintf('content-studio-export_%s.zip', $this->dateTime->date('Ymd_His'));

            return $this->zipDownload->generateDownloadArchive($fileName, $files);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->critical($exception);
            $this->messageManager->addErrorMessage(__('An error occurred while exporting entries.'));
        }

        $resultRedirect = $this->resultRedirectFactory->create();

        return $resultRedirect->setPath('*/*/');
    }
}
