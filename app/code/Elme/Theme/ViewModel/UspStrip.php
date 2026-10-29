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
 * Content Studio type `usp_strip` (etc/content_types.xml, FE-16): stockist benefits image + trade facts.
 */
class UspStrip implements ArgumentInterface
{
    public const MAX_ITEMS = 6;

    public const IMAGE_SIZE = [720, 742];

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
     * @return array{src: string, width: int, height: int, alt: string}|null
     */
    public function getImage(array $content): ?array
    {
        $src = $this->text($content, 'image');

        if ($src === '') {
            return null;
        }

        [$width, $height] = $this->readSize($src) ?? self::IMAGE_SIZE;
        $alt = $this->text($content, 'image_alt');

        return ['src' => $src, 'width' => $width, 'height' => $height, 'alt' => $alt];
    }

    /**
     * Extra photos (image_2, image_3) that turn the side image into a three-photo collage. The
     * collage needs both; with one or none the template shows the single image.
     *
     * @param array<string, mixed> $content
     * @return list<array{src: string, width: int, height: int}>
     */
    public function getCollage(array $content): array
    {
        $images = [];

        foreach (['image_2', 'image_3'] as $key) {
            $src = $this->text($content, $key);

            if ($src === '') {
                return [];
            }

            [$width, $height] = $this->readSize($src) ?? self::IMAGE_SIZE;
            $images[] = ['src' => $src, 'width' => $width, 'height' => $height];
        }

        return $images;
    }

    /**
     * @param array<string, mixed> $content
     * @return list<array{title: string, text: string}>
     */
    public function getItems(array $content): array
    {
        $items = [];

        foreach ((array)($content['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = $this->text($row, 'title');

            if ($title === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'text' => $this->text($row, 'text'),
            ];

            if (count($items) === self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * Guest CTA from the entry. Caller swaps destination when the visitor is logged in.
     *
     * @param array<string, mixed> $content
     * @return array{label: string, link: string}|null
     */
    public function getGuestButton(array $content): ?array
    {
        $label = $this->text($content, 'button_label');
        $link = $this->text($content, 'button_link');

        if ($label === '' || $link === '') {
            return null;
        }

        return ['label' => $label, 'link' => $link];
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
