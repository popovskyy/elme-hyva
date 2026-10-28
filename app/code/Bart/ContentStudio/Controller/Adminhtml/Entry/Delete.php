<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Delete a Content Studio entry (POST + form_key via Magento UI `post: true`).
 */
class Delete extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_delete';

    /**
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $entryId = (int)$this->getRequest()->getParam('entry_id');

        if (!$entryId) {
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->entryRepository->deleteById($entryId);
            $this->messageManager->addSuccessMessage(__('The entry has been deleted.')->render());
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This entry no longer exists.')->render());
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
