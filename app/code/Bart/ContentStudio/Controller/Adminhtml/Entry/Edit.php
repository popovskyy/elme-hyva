<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Content Studio entry editor.
 */
class Edit extends Entry implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_save';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param SchemaConfig $schemaConfig
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EntryRepositoryInterface $entryRepository,
        private readonly SchemaConfig $schemaConfig
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return Page|Redirect
     */
    public function execute()
    {
        $entryId = (int)$this->getRequest()->getParam('entry_id');
        $type = (string)$this->getRequest()->getParam('type');

        if ($entryId) {
            try {
                $this->entryRepository->getById($entryId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This entry no longer exists.')->render());

                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } elseif (!$this->schemaConfig->hasType($type)) {
            return $this->resultRedirectFactory->create()->setPath('*/*/new');
        }

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();

        return $this->initPage($resultPage, $entryId ? __('Edit Entry')->render() : __('New Entry')->render());
    }
}
