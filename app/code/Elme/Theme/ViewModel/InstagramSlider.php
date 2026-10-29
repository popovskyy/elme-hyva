<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `instagram_slider` (etc/content_types.xml, FE-12): curated stockist posts,
 * honest link targets, optional PDP SKU filter. No live Instagram API (D10).
 */
class InstagramSlider implements ArgumentInterface
{
    public const MAX_POSTS = 24;

    public const IMAGE_SIZE = [800, 800];

    public const PROFILE_BASE = 'https://www.instagram.com/';

    public const SHOPPABLE_PATH = '/shoppable-instagram';

    public const PDP_HEADING = 'Styled by our stockists';

    private const INSTAGRAM_HANDLE = 'elmeliving';

    private ?string $mediaPath = null;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly Registry $registry
    ) {
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content, bool $filterByProduct): string
    {
        if ($filterByProduct) {
            return self::PDP_HEADING;
        }

        $heading = $this->text($content, 'heading');

        return $heading !== '' ? $heading : (string)__('As styled by you');
    }

    /**
     * Handle without @; empty falls back to elmeliving.
     *
     * @param array<string, mixed> $content
     */
    public function getHandle(array $content): string
    {
        $handle = ltrim($this->text($content, 'handle'), '@');

        return $handle !== '' ? $handle : self::INSTAGRAM_HANDLE;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getProfileUrl(array $content): string
    {
        return self::PROFILE_BASE . rawurlencode($this->getHandle($content)) . '/';
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getInvite(array $content): string
    {
        return $this->text($content, 'invite');
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getFollowLabel(array $content): string
    {
        $label = $this->text($content, 'follow_label');

        return $label !== '' ? $label : (string)__('Follow Us');
    }

    /**
     * Posts ready for the template. When $filterByProduct is true, keep only rows whose SKUs
     * include the current product; with no product or no match, return [].
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     image: array{src: string, width: int, height: int},
     *     image_alt: string,
     *     href: string,
     *     external: bool,
     *     action_label: string,
     *     credit_handle: string,
     *     credit_location: string,
     *     sku_count: int,
     *     accessible_name: string
     * }>
     */
    public function getPosts(array $content, bool $filterByProduct = false): array
    {
        $filterSku = null;

        if ($filterByProduct) {
            $filterSku = $this->currentProductSku();

            if ($filterSku === null) {
                return [];
            }
        }

        $posts = [];

        foreach ((array)($content['posts'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $post = $this->normalisePost($row);

            if ($post === null) {
                continue;
            }

            if ($filterSku !== null && !$this->postHasSku($post['skus'], $filterSku)) {
                continue;
            }

            $posts[] = $this->presentPost($post);

            if (count($posts) === self::MAX_POSTS) {
                break;
            }
        }

        return $posts;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{
     *     image: string,
     *     image_alt: string,
     *     post_url: string,
     *     credit_handle: string,
     *     credit_location: string,
     *     skus: list<string>
     * }|null
     */
    private function normalisePost(array $row): ?array
    {
        $image = $this->text($row, 'image');
        $postUrl = $this->text($row, 'post_url');
        $handle = ltrim($this->text($row, 'credit_handle'), '@');
        $alt = $this->text($row, 'image_alt');

        if ($alt === '') {
            $alt = $this->text($row, 'caption');
        }

        if ($image === '' || $postUrl === '' || $handle === '' || $alt === '') {
            return null;
        }

        return [
            'image' => $image,
            'image_alt' => $alt,
            'post_url' => $postUrl,
            'credit_handle' => $handle,
            'credit_location' => $this->text($row, 'credit_location'),
            'skus' => $this->parseSkus($this->text($row, 'skus')),
        ];
    }

    /**
     * @param array{
     *     image: string,
     *     image_alt: string,
     *     post_url: string,
     *     credit_handle: string,
     *     credit_location: string,
     *     skus: list<string>
     * } $post
     * @return array{
     *     image: array{src: string, width: int, height: int},
     *     image_alt: string,
     *     href: string,
     *     external: bool,
     *     action_label: string,
     *     credit_handle: string,
     *     credit_location: string,
     *     sku_count: int,
     *     accessible_name: string
     * }
     */
    private function presentPost(array $post): array
    {
        $skuCount = count($post['skus']);
        $external = $skuCount === 0;
        $actionLabel = $external
            ? (string)__('View on Instagram')
            : (string)__('View products');
        $href = $external
            ? $post['post_url']
            : self::SHOPPABLE_PATH . '#' . rawurlencode($post['skus'][0]);

        $credit = '@' . $post['credit_handle'];
        if ($post['credit_location'] !== '') {
            $credit .= ' · ' . $post['credit_location'];
        }

        return [
            'image' => $this->image($post['image']),
            'image_alt' => $post['image_alt'],
            'href' => $href,
            'external' => $external,
            'action_label' => $actionLabel,
            'credit_handle' => $post['credit_handle'],
            'credit_location' => $post['credit_location'],
            'sku_count' => $skuCount,
            'accessible_name' => $credit . ' — ' . $actionLabel,
        ];
    }

    /**
     * @return list<string>
     */
    private function parseSkus(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $skus = [];

        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $sku) {
            $sku = trim((string)$sku);

            if ($sku !== '' && !in_array($sku, $skus, true)) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    /**
     * @param list<string> $skus
     */
    private function postHasSku(array $skus, string $filterSku): bool
    {
        foreach ($skus as $sku) {
            if (strcasecmp($sku, $filterSku) === 0) {
                return true;
            }
        }

        return false;
    }

    private function currentProductSku(): ?string
    {
        $product = $this->registry->registry('current_product');

        if (!$product instanceof ProductInterface) {
            return null;
        }

        $sku = trim((string)$product->getSku());

        return $sku !== '' ? $sku : null;
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
