<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `collaboration` (FE-89): banner, intro, lifestyle collage, Shop the look
 * product grid resolved by SKU for the shared FE-19 card.
 */
class Collaboration implements ArgumentInterface
{
    public const MAX_PRODUCTS = 60;

    public const BANNER_SIZE = [1920, 700];

    public const COLLAGE_SIZE = [1125, 2705];

    private ?string $mediaPath = null;

    /** @var array<string, Product|false> */
    private array $productsBySku = [];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly ProductRepositoryInterface $productRepository
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
     */
    public function isPageTitle(array $content): bool
    {
        return !empty($content['is_page_title']);
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
     */
    public function getSectionHeading(array $content): string
    {
        $heading = $this->text($content, 'section_heading');

        return $heading !== '' ? $heading : (string) __('Shop the look');
    }

    /**
     * @param array<string, mixed> $content
     * @return array{src: string, width: int, height: int, alt: string}|null
     */
    public function getBannerDesktop(array $content): ?array
    {
        return $this->imageField($content, 'banner_desktop', 'banner_alt', self::BANNER_SIZE);
    }

    /**
     * @param array<string, mixed> $content
     * @return array{src: string, width: int, height: int, alt: string}|null
     */
    public function getBannerMobile(array $content): ?array
    {
        $mobile = $this->imageField($content, 'banner_mobile', 'banner_alt', self::BANNER_SIZE);
        if ($mobile !== null) {
            return $mobile;
        }

        return $this->getBannerDesktop($content);
    }

    /**
     * @param array<string, mixed> $content
     * @return array{src: string, width: int, height: int, alt: string}|null
     */
    public function getCollage(array $content): ?array
    {
        $alt = $this->text($content, 'collage_alt');
        if ($alt === '') {
            $alt = $this->getHeading($content);
        }

        $image = $this->imageField($content, 'collage', 'collage_alt', self::COLLAGE_SIZE);
        if ($image === null) {
            return null;
        }

        $image['alt'] = $alt;

        return $image;
    }

    /**
     * Enabled, visible products in admin order. Missing / disabled / not-visible SKUs are skipped.
     *
     * @param array<string, mixed> $content
     * @return list<Product>
     */
    public function getProducts(array $content): array
    {
        $products = [];
        $seen = [];

        foreach ((array)($content['products'] ?? []) as $row) {
            if (!is_array($row) || count($products) >= self::MAX_PRODUCTS) {
                break;
            }

            $sku = trim((string)($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            $key = strtolower($sku);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $product = $this->loadProduct($sku);
            if ($product === null) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    private function loadProduct(string $sku): ?Product
    {
        $key = strtolower($sku);
        if (array_key_exists($key, $this->productsBySku)) {
            $cached = $this->productsBySku[$key];

            return $cached instanceof Product ? $cached : null;
        }

        try {
            $product = $this->productRepository->get(
                $sku,
                false,
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException) {
            $this->productsBySku[$key] = false;

            return null;
        } catch (Throwable) {
            $this->productsBySku[$key] = false;

            return null;
        }

        if (!$product instanceof Product) {
            $this->productsBySku[$key] = false;

            return null;
        }

        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            $this->productsBySku[$key] = false;

            return null;
        }

        if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            $this->productsBySku[$key] = false;

            return null;
        }

        $product->setData('image_custom_attributes', ['loading' => 'lazy']);
        $this->productsBySku[$key] = $product;

        return $product;
    }

    /**
     * @param array<string, mixed> $content
     * @param array{0: int, 1: int} $fallbackSize
     * @return array{src: string, width: int, height: int, alt: string}|null
     */
    private function imageField(array $content, string $srcKey, string $altKey, array $fallbackSize): ?array
    {
        $src = $this->text($content, $srcKey);
        if ($src === '') {
            return null;
        }

        [$width, $height] = $this->imageSize($src) ?? $fallbackSize;

        return [
            'src' => $src,
            'width' => $width,
            'height' => $height,
            'alt' => $this->text($content, $altKey),
        ];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function imageSize(string $url): ?array
    {
        $path = $this->mediaPathFromUrl($url);
        if ($path === null || !is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);
        if (!is_array($size) || (int) $size[0] < 1 || (int) $size[1] < 1) {
            return null;
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private function mediaPathFromUrl(string $url): ?string
    {
        $mediaBase = rtrim(
            (string) $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/';

        if (!str_starts_with($url, $mediaBase) && !str_starts_with($url, '/media/')) {
            return null;
        }

        $relative = str_starts_with($url, $mediaBase)
            ? substr($url, strlen($mediaBase))
            : substr($url, strlen('/media/'));

        $relative = ltrim(str_replace(['\\', "\0"], '', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        return $this->mediaRoot() . '/' . $relative;
    }

    private function mediaRoot(): string
    {
        if ($this->mediaPath === null) {
            $this->mediaPath = $this->filesystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath();
        }

        return rtrim($this->mediaPath, '/');
    }

    /**
     * @param array<string, mixed> $content
     */
    private function text(array $content, string $key): string
    {
        return trim((string)($content[$key] ?? ''));
    }
}
