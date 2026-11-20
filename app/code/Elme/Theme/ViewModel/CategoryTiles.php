<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `category_tiles` (etc/content_types.xml, FE-15): normalises the entry into
 * the tiles the template prints, with each image's real pixel size for width/height attributes.
 */
class CategoryTiles implements ArgumentInterface
{
    public const MAX_TILES = 16;

    public const IMAGE_SIZE = [620, 450];

    private ?string $mediaPath = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Optional section title above the carousel. Empty means no heading element.
     *
     * @param array<string, mixed> $content Block\Entry::getContent()
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * Tiles with a link, label and image, in editor order, at most MAX_TILES.
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     link: string,
     *     label: string,
     *     image: array{src: string, width: int, height: int}
     * }>
     */
    public function getTiles(array $content): array
    {
        $tiles = [];

        foreach ((array)($content['tiles'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $link = $this->text($row, 'link');
            $label = $this->text($row, 'label');
            $image = $this->text($row, 'image');

            if ($link === '' || $label === '' || $image === '') {
                continue;
            }

            $tiles[] = [
                'link' => $link,
                'label' => $label,
                'image' => $this->image($image),
            ];

            if (count($tiles) === self::MAX_TILES) {
                break;
            }
        }

        return $tiles;
    }

    /**
     * @return array{src: string, width: int, height: int}
     */
    private function image(string $url): array
    {
        [$width, $height] = $this->readSize($url) ?? self::IMAGE_SIZE;

        return ['src' => $url, 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function readSize(string $url): ?array
    {
        $relative = $this->mediaRelativePath($url);

        if ($relative === null) {
            return null;
        }

        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);

            if (!$media->isFile($relative)) {
                return null;
            }

            $size = getimagesize($media->getAbsolutePath($relative));
        } catch (Throwable) {
            return null;
        }

        if (!$size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return [(int)$size[0], (int)$size[1]];
    }

    /**
     * `https://host/media/a/b.jpg` or `/media/a/b.jpg` -> `a/b.jpg`; null outside media.
     */
    private function mediaRelativePath(string $url): ?string
    {
        $path = rawurldecode((string)parse_url($url, PHP_URL_PATH));
        $mediaPath = $this->mediaPath();

        if ($mediaPath === '' || !str_starts_with($path, $mediaPath)) {
            return null;
        }

        $relative = substr($path, strlen($mediaPath));

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }

    private function mediaPath(): string
    {
        if ($this->mediaPath === null) {
            try {
                $base = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                $this->mediaPath = rtrim((string)parse_url($base, PHP_URL_PATH), '/') . '/';
            } catch (Throwable) {
                $this->mediaPath = '';
            }
        }

        return $this->mediaPath;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
