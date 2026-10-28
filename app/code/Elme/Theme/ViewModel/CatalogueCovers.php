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
 * Content Studio type `catalogue_covers` (etc/content_types.xml, FE-16): normalises cover cards and
 * reads each image's pixel size. Empty / incomplete cards mean the section must not render (UX-01).
 */
class CatalogueCovers implements ArgumentInterface
{
    public const MAX_CARDS = 4;

    public const IMAGE_SIZE = [620, 877];

    private ?string $mediaPath = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param array<string, mixed> $content Block\Entry::getContent()
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        return $this->text($content, 'intro');
    }

    /**
     * @param array<string, mixed> $content
     * @return array{label: string, link: string}|null
     */
    public function getSeeAll(array $content): ?array
    {
        $label = $this->text($content, 'see_all_label');
        $link = $this->text($content, 'see_all_link');

        if ($label === '' || $link === '') {
            return null;
        }

        return ['label' => $label, 'link' => $link];
    }

    /**
     * Cards with image, title and link. Incomplete rows are skipped; an empty list means hide the band.
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     title: string,
     *     link: string,
     *     image_alt: string,
     *     image: array{src: string, width: int, height: int}
     * }>
     */
    public function getCards(array $content): array
    {
        $cards = [];

        foreach ((array)($content['cards'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = $this->text($row, 'title');
            $link = $this->text($row, 'link');
            $image = $this->text($row, 'image');

            if ($title === '' || $link === '' || $image === '') {
                continue;
            }

            $alt = $this->text($row, 'image_alt');

            $cards[] = [
                'title' => $title,
                'link' => $link,
                'image_alt' => $alt !== '' ? $alt : $title,
                'image' => $this->image($image),
            ];

            if (count($cards) === self::MAX_CARDS) {
                break;
            }
        }

        return $cards;
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
