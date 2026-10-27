<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\File\Upload;

use LogicException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\Serializer\Json;
use ZipArchive;

/**
 * Parses an uploaded JSON or ZIP (containing JSON files) into entry payloads.
 *
 * A malformed file - one that can't even be decoded - throws here, before
 * Processor\Import ever starts a transaction. That is what makes "a
 * corrupted ZIP imports nothing" true for genuinely broken files; a
 * structurally valid payload that fails schema validation is a different
 * case, handled per-entry inside Processor\Import (see its doc comment).
 */
class Content
{
    /**
     * @param Filesystem $filesystem
     * @param Json $json
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Json $json
    ) {
    }

    /**
     * @param string $sourceFile
     * @return array<int, array<string, mixed>>
     */
    public function parseUploadedFile(string $sourceFile): array
    {
        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $sourceFilePath = $varDirectory->getRelativePath($sourceFile);
        $extension = strtolower((string)pathinfo($sourceFilePath, PATHINFO_EXTENSION));

        try {
            $payloads = match ($extension) {
                'zip' => $this->parseZipFile($varDirectory->getAbsolutePath($sourceFilePath)),
                'json' => [$this->parseJsonFile($varDirectory, $sourceFilePath)],
                default => throw new LocalizedException(__('Unsupported import file type.')),
            };
        } finally {
            if ($varDirectory->isExist($sourceFilePath)) {
                $varDirectory->delete($sourceFilePath);
            }
        }

        return array_values(array_filter($payloads));
    }

    /**
     * @param WriteInterface $varDirectory
     * @param string $sourceFilePath
     * @return array<string, mixed>
     */
    private function parseJsonFile(WriteInterface $varDirectory, string $sourceFilePath): array
    {
        try {
            $stream = $varDirectory->openFile($sourceFilePath, 'r');
        } catch (FileSystemException) {
            throw new LogicException((string)__('Unable to open uploaded file.'));
        }

        $data = '';

        while (!$stream->eof()) {
            $data .= $stream->read(1024);
        }

        $stream->close();

        $decoded = $this->json->unserialize($data);

        if (!is_array($decoded)) {
            throw new LocalizedException(__('Invalid JSON import file.'));
        }

        return $decoded;
    }

    /**
     * @param string $absolutePath
     * @return array<int, array<string, mixed>>
     * @throws LocalizedException
     */
    private function parseZipFile(string $absolutePath): array
    {
        $zipArchive = new ZipArchive();
        $result = $zipArchive->open($absolutePath);

        if ($result !== true) {
            throw new LocalizedException(__('Cannot open ZIP archive.'));
        }

        $payloads = $this->readZipEntries($zipArchive);
        $zipArchive->close();

        if ($payloads === []) {
            throw new LocalizedException(__('ZIP archive does not contain any valid JSON export files.'));
        }

        return $payloads;
    }

    /**
     * @param ZipArchive $zipArchive
     * @return array<int, array<string, mixed>>
     */
    private function readZipEntries(ZipArchive $zipArchive): array
    {
        $payloads = [];

        for ($index = 0; $index < $zipArchive->numFiles; $index++) {
            $payload = $this->readZipEntry($zipArchive, $index);

            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param ZipArchive $zipArchive
     * @param int $index
     * @return array<string, mixed>|null
     */
    private function readZipEntry(ZipArchive $zipArchive, int $index): ?array
    {
        $entryName = (string)$zipArchive->getNameIndex($index);

        if (!str_ends_with(strtolower($entryName), '.json') || str_contains($entryName, '..')) {
            return null;
        }

        $contents = $zipArchive->getFromIndex($index);

        if ($contents === false) {
            return null;
        }

        $decoded = $this->json->unserialize($contents);

        return is_array($decoded) ? $decoded : null;
    }
}
