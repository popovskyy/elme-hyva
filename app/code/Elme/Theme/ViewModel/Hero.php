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
 * Content Studio type `hero` (etc/content_types.xml, FE-14): turns the entry content into the slides
 * the template prints, with the real pixel size of every image.
 *
 * Image fields store a URL. The size comes from the file under pub/media, so <img> and <source>
 * carry their own width/height; a URL outside the media folder falls back to the recommended
 * size. The hero box has a fixed ratio either way, so the numbers only tell the browser the
 * intrinsic ratio before the file arrives.
 */
class Hero implements ArgumentInterface
{
    public const MAX_SLIDES = 5;

    public const DESKTOP_SIZE = [2400, 1200];

    public const MOBILE_SIZE = [900, 1125];

    /** Light photos get dark text on a light fade; dark photos (the default) white text on a dark one. */
    public const TONES = ['dark', 'light'];

    private const INTERVAL_DEFAULT = 7;

    private const INTERVAL_MIN = 4;

    private const INTERVAL_MAX = 15;

    private ?string $mediaPath = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Slides with a desktop image and a heading, in editor order, at most MAX_SLIDES.
     *
     * @param array<string, mixed> $content Block\Entry::getContent()
     * @return list<array{
     *     desktop: array{src: string, width: int, height: int},
     *     mobile: array{src: string, width: int, height: int}|null,
     *     alt: string,
     *     heading: string,
     *     lines: list<string>,
     *     text: string,
     *     button_label: string,
     *     button_link: string,
     *     tone: string
     * }>
     */
    public function getSlides(array $content): array
    {
        $slides = [];

        foreach ((array)($content['slides'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $desktop = $this->text($row, 'image_desktop');
            $heading = $this->text($row, 'heading');

            if ($desktop === '' || $heading === '') {
                continue;
            }

            $mobile = $this->text($row, 'image_mobile');
            $buttonLabel = $this->text($row, 'button_label');
            $buttonLink = $this->text($row, 'button_link');
            $hasButton = $buttonLabel !== '' && $buttonLink !== '';
            $tone = $this->text($row, 'tone');

            $slides[] = [
                'desktop' => $this->image($desktop, self::DESKTOP_SIZE),
                'mobile' => $mobile !== '' ? $this->image($mobile, self::MOBILE_SIZE) : null,
                'alt' => $this->text($row, 'image_alt'),
                'heading' => $heading,
                'lines' => $this->lines($heading),
                'text' => $this->text($row, 'text'),
                'button_label' => $hasButton ? $buttonLabel : '',
                'button_link' => $hasButton ? $buttonLink : '',
                'tone' => in_array($tone, self::TONES, true) ? $tone : 'dark',
            ];

            if (count($slides) === self::MAX_SLIDES) {
                break;
            }
        }

        return $slides;
    }

    /**
     * Milliseconds between slides, 0 when autoplay is off. A missing `autoplay` key reads as the
     * schema default (on), so an imported entry behaves like one created in the editor.
     *
     * @param array<string, mixed> $content
     */
    public function getAutoplayInterval(array $content): int
    {
        if (array_key_exists('autoplay', $content) && empty($content['autoplay'])) {
            return 0;
        }

        $seconds = is_numeric($content['interval'] ?? null) ? (int)$content['interval'] : self::INTERVAL_DEFAULT;

        return max(self::INTERVAL_MIN, min(self::INTERVAL_MAX, $seconds)) * 1000;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function isPageTitle(array $content): bool
    {
        return !empty($content['is_page_title']);
    }

    /**
     * Heading lines as the editor broke them; each line rises on its own when the slide shows.
     *
     * @return list<string>
     */
    private function lines(string $heading): array
    {
        $lines = array_map('trim', preg_split('/\R/', $heading) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @param array{0: int, 1: int} $fallbackSize
     * @return array{src: string, width: int, height: int}
     */
    private function image(string $url, array $fallbackSize): array
    {
        [$width, $height] = $this->readSize($url) ?? $fallbackSize;

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
     * `https://host/media/a/b.jpg` or `/media/a/b.jpg` (the admin preview frame makes media URLs
     * root-relative) -> `a/b.jpg`; null for anything outside the media folder.
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
