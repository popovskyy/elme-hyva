<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\File;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory as HttpFileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Writes export JSON to var/ and triggers a browser download.
 */
class Download
{
    private const EXPORT_DIR = 'bart_content_studio/importexport';
    private const FILE_EXTENSION = 'json';

    /**
     * @param HttpFileFactory $httpFileFactory
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly HttpFileFactory $httpFileFactory,
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * @param string $fileId
     * @param array<string, mixed> $data
     * @return ResponseInterface
     */
    public function generateDownloadFile(string $fileId, array $data): ResponseInterface
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $filePath = self::EXPORT_DIR . DIRECTORY_SEPARATOR
            . 'entry-' . $fileId . '-' . hash('sha256', (string)microtime(true)) . '.' . self::FILE_EXTENSION;

        $varDirectory->create(self::EXPORT_DIR);
        $stream = $varDirectory->openFile($filePath, 'w+');
        $stream->lock();
        $stream->write($this->json->serialize($data));
        $stream->unlock();
        $stream->close();

        $downloadName = $fileId . '.' . self::FILE_EXTENSION;

        return $this->httpFileFactory->create(
            $downloadName,
            ['type' => 'filename', 'value' => $filePath, 'rm' => true],
            DirectoryList::VAR_DIR,
            'application/json'
        );
    }
}
