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
 * Content Studio type `proof_pillars` (FE-61): lifestyle image + trade-proof rows with one link each.
 */
class ProofPillars implements ArgumentInterface
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
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
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

        return [
            'src' => $src,
            'width' => $width,
            'height' => $height,
            'alt' => $this->text($content, 'image_alt'),
        ];
    }

    /**
     * @param array<string, mixed> $content
     * @return list<array{title: string, text: string, link_label: string, link: string}>
     */
    public function getItems(array $content): array
    {
        $items = [];

        foreach ((array) ($content['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = $this->text($row, 'title');
            if ($title === '') {
                continue;
            }

            $linkLabel = $this->text($row, 'link_label');
            $link = $this->text($row, 'link');
            $hasLink = $linkLabel !== '' && $link !== '';

            $items[] = [
                'title' => $title,
                'text' => $this->text($row, 'text'),
                'link_label' => $hasLink ? $linkLabel : '',
                'link' => $hasLink ? $link : '',
            ];

            if (count($items) === self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
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

        return [(int) $size[0], (int) $size[1]];
    }

    private function mediaRelativePath(string $url): ?string
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
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
                $this->mediaPath = rtrim((string) parse_url($base, PHP_URL_PATH), '/') . '/';
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
