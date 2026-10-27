<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\File;

use Bart\ContentStudio\Model\ImportExport\File\Upload\Content;
use Bart\ContentStudio\Model\ImportExport\File\Upload\Source;

/**
 * Uploads an import file and returns parsed entry payloads.
 */
class Upload
{
    /**
     * @param Content $content
     * @param Source $source
     */
    public function __construct(
        private readonly Content $content,
        private readonly Source $source
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function uploadFileAndGetData(): array
    {
        return $this->content->parseUploadedFile($this->source->upload());
    }
}
