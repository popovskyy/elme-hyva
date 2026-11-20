<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\ImageUpload;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\View\Result\PageFactory;

/**
 * Uploads an image field's file (drag&drop or file-picker) and returns its URL.
 */
class UploadImage extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_save';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param ImageUpload $imageUpload
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EntryRepositoryInterface $entryRepository,
        private readonly ImageUpload $imageUpload,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $url = $this->imageUpload->upload('image');

            return $resultJson->setData(['success' => true, 'url' => $url]);
        } catch (ValidatorException $exception) {
            return $resultJson->setData(['success' => false, 'error' => $exception->getMessage()]);
        }
    }
}
