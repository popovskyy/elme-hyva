<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Image fields store the full URL of an upload (https://host/media/...). When the site moves to
 * another host (staging, production, a phone preview tunnel) those URLs would still point at the old
 * one. This puts the current media base URL in front of the path, but only for a value that is just
 * a media URL and whose file exists under pub/media, so external URLs and text stay untouched.
 */
class MediaUrlRebaser
{
    private const MEDIA_URL = '#^https?://[^/\s]+/(?:pub/)?media/([^\s?\#]+)([?\#]\S*)?$#';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    public function rebase(string $value): string
    {
        if (!preg_match(self::MEDIA_URL, $value, $match)) {
            return $value;
        }

        try {
            if (!$this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->isFile(rawurldecode($match[1]))) {
                return $value;
            }
            $base = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/');
        } catch (Throwable) {
            return $value;
        }

        return $base . '/' . $match[1] . ($match[2] ?? '');
    }
}
