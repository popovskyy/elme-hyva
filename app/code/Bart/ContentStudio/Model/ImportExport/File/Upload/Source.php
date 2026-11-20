<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\File\Upload;

use Exception;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\HTTP\Adapter\FileTransferFactory;
use Magento\Framework\Validation\ValidationException;
use Magento\ImportExport\Helper\Data as ImportExportHelper;
use Magento\ImportExport\Model\Import as ImportModel;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Zend_Validate_File_Upload;

/**
 * Validates and stores the uploaded import file in Magento's import working directory.
 */
class Source
{
    public const ENTITY = 'bart_content_studio';
    private const ALLOWED_EXTENSIONS = ['json', 'zip'];

    /**
     * @param FileTransferFactory $fileTransferFactory
     * @param ImportExportHelper $importExportHelper
     * @param ImportModel $import
     * @param UploaderFactory $uploaderFactory
     */
    public function __construct(
        private readonly FileTransferFactory $fileTransferFactory,
        private readonly ImportExportHelper $importExportHelper,
        private readonly ImportModel $import,
        private readonly UploaderFactory $uploaderFactory
    ) {
    }

    /**
     * @return string Absolute path to the saved upload
     * @throws ValidatorException
     */
    public function upload(): string
    {
        $this->validateFile();

        $uploader = $this->uploaderFactory->create(['fileId' => ImportModel::FIELD_NAME_SOURCE_FILE]);
        $uploader->setAllowedExtensions(self::ALLOWED_EXTENSIONS);
        $uploader->skipDbProcessing(true);

        $workingDir = $this->import->getWorkingDir();
        $fileName = self::ENTITY . '-' . hash('sha256', (string)microtime(true)) . '.' . $uploader->getFileExtension();

        try {
            $result = $uploader->save($workingDir, $fileName);
        } catch (ValidationException $exception) {
            throw new ValidatorException(__($exception->getMessage()));
        } catch (Exception) {
            throw new ValidatorException(__('The file cannot be uploaded.'));
        }

        return $result['path'] . $result['file'];
    }

    /**
     * @return void
     * @throws ValidatorException
     */
    private function validateFile(): void
    {
        $fileTransferAdapter = $this->fileTransferFactory->create();

        if (!$fileTransferAdapter->isValid(ImportModel::FIELD_NAME_SOURCE_FILE)) {
            $errors = $fileTransferAdapter->getErrors();
            $errorMessage = ($errors[0] ?? null) === Zend_Validate_File_Upload::INI_SIZE
                ? $this->importExportHelper->getMaxUploadSizeMessage()
                : __('The file was not uploaded.');

            throw new ValidatorException($errorMessage);
        }
    }
}
