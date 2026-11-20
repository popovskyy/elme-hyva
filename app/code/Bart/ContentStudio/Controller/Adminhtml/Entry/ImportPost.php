<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\ImportExport\File\Upload as FileUpload;
use Bart\ContentStudio\Model\ImportExport\Processor\Import as ImportProcessor;
use Bart\ContentStudio\Model\OptionSource\ImportMode;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Processes the entry import form submission and reports the outcome
 * (created/updated/skipped/error, per entry) as admin flash messages -
 * see Processor\Import's doc comment for why one bad entry still shows a
 * full report instead of a single opaque failure.
 */
class ImportPost extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_import';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param LoggerInterface $logger
     * @param FileUpload $fileUpload
     * @param ImportProcessor $importProcessor
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EntryRepositoryInterface $entryRepository,
        private readonly LoggerInterface $logger,
        private readonly FileUpload $fileUpload,
        private readonly ImportProcessor $importProcessor
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $mode = (string)$this->getRequest()->getParam('import_mode', ImportMode::MODE_UPDATE);
        $dryRun = (bool)$this->getRequest()->getParam('dry_run');

        try {
            $payloads = $this->fileUpload->uploadFileAndGetData();
            $report = $this->importProcessor->importBatch($payloads, $mode, $dryRun);

            $this->flashReport($report, $dryRun);
        } catch (ValidatorException | LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->critical($exception);
            $this->messageManager->addErrorMessage(__('An error occurred while importing entries.'));
        }

        return $resultRedirect->setPath('*/*/import');
    }

    /**
     * @param array<int, array<string, string>> $report
     * @param bool $dryRun
     * @return void
     */
    private function flashReport(array $report, bool $dryRun): void
    {
        $counts = $this->countByStatus($report);

        if ($counts['error'] > 0) {
            $this->messageManager->addErrorMessage(
                __('Import failed, nothing was saved: %1 entr(y/ies) had errors.', $counts['error'])->render()
            );
            $this->flashRowMessages($report, 'error', 'addErrorMessage');

            return;
        }

        $this->messageManager->addSuccessMessage($this->buildSummary($counts, $dryRun));
        $this->flashRowMessages($report, 'created', 'addNoticeMessage', true);
        $this->flashRowMessages($report, 'updated', 'addNoticeMessage', true);
    }

    /**
     * @param array<int, array<string, string>> $report
     * @return array<string, int>
     */
    private function countByStatus(array $report): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($report as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @param bool $dryRun
     * @return string
     */
    private function buildSummary(array $counts, bool $dryRun): string
    {
        $verb = $dryRun ? __('Dry run:') : __('Import complete:');

        return __(
            '%1 %2 to create, %3 to update, %4 skipped.',
            $verb,
            $counts['created'],
            $counts['updated'],
            $counts['skipped']
        )->render();
    }

    /**
     * Only rows carrying a message (a store-code warning, or an error
     * detail) are worth a separate flash - a plain "created"/"updated" row
     * is already covered by the summary count.
     *
     * @param array<int, array<string, string>> $report
     * @param string $status
     * @param string $messageManagerMethod
     * @param bool $onlyWithMessage
     * @return void
     */
    private function flashRowMessages(
        array $report,
        string $status,
        string $messageManagerMethod,
        bool $onlyWithMessage = false
    ): void {
        foreach ($report as $row) {
            if ($row['status'] !== $status || ($onlyWithMessage && $row['message'] === '')) {
                continue;
            }

            $this->messageManager->{$messageManagerMethod}("{$row['identifier']}: {$row['message']}");
        }
    }
}
