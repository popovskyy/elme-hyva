<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\File;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory as HttpFileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;
use ZipArchive;

/**
 * Builds a ZIP archive with one JSON file per exported entry.
 */
class ZipDownload
{
    private const EXPORT_DIR = 'bart_content_studio/importexport';

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
     * @param string $downloadFileName
     * @param array<string, array<string, mixed>> $files Map of filename (without path) => payload
     * @return ResponseInterface
     */
    public function generateDownloadArchive(string $downloadFileName, array $files): ResponseInterface
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $varDirectory->create(self::EXPORT_DIR);

        $archivePath = self::EXPORT_DIR . DIRECTORY_SEPARATOR
            . 'export-' . hash('sha256', (string)microtime(true)) . '.zip';
        $absoluteArchivePath = $varDirectory->getAbsolutePath($archivePath);

        $zipArchive = new ZipArchive();
        $zipArchive->open($absoluteArchivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $fileName => $payload) {
            $zipArchive->addFromString($fileName, $this->json->serialize($payload));
        }

        $zipArchive->close();

        return $this->httpFileFactory->create(
            $downloadFileName,
            ['type' => 'filename', 'value' => $archivePath, 'rm' => true],
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }
}
