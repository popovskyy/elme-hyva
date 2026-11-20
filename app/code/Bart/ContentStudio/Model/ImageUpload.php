<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem;
use Magento\Framework\HTTP\Adapter\FileTransferFactory;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Uploads an image dropped/picked in the editor's image field directly into
 * pub/media, independent of the CMS media browser's own stateful "current
 * folder" session (Magento\Cms\Model\Wysiwyg\Images\Storage requires a prior
 * folder-selection request to set that up - see plan section 3, image picker). This
 * is a narrower need: save the file, return its absolute URL.
 */
class ImageUpload
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    /**
     * Also read by Block\Adminhtml\Editor, which encodes it into the media
     * browser's `current_tree_path` so Browse opens in the same folder
     * drag&drop uploads into.
     */
    public const UPLOAD_DIR = 'bart_content_studio';

    /**
     * @param FileTransferFactory $fileTransferFactory
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     * @param AdapterFactory $imageAdapterFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly FileTransferFactory $fileTransferFactory,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly AdapterFactory $imageAdapterFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param string $fieldName The multipart field name the file was posted under.
     * @return string Absolute URL of the saved image.
     * @throws ValidatorException
     */
    public function upload(string $fieldName): string
    {
        $fileTransferAdapter = $this->fileTransferFactory->create();

        if (!$fileTransferAdapter->isValid($fieldName)) {
            throw new ValidatorException(__('The file was not uploaded.'));
        }

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $uploader = $this->uploaderFactory->create(['fileId' => $fieldName]);
        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        // The extension allowlist alone only checks the file's name, so any
        // file renamed to .png would be written into pub/media. This is the
        // same content check Magento's own product gallery upload performs:
        // the image adapter has to be able to actually open the file.
        $uploader->addValidateCallback(
            'bart_content_studio_image',
            $this->imageAdapterFactory->create(),
            'validateUploadFile'
        );
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(true);
        $uploader->skipDbProcessing(true);

        try {
            $result = $uploader->save($mediaDirectory->getAbsolutePath(self::UPLOAD_DIR));
        } catch (Throwable $exception) {
            $this->logger->error('Content Studio image upload failed: ' . $exception->getMessage(), [
                'exception' => $exception,
            ]);

            throw new ValidatorException(__('The image could not be uploaded.'), $exception);
        }

        $mediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');

        return $mediaUrl . '/' . self::UPLOAD_DIR . $result['file'];
    }
}
