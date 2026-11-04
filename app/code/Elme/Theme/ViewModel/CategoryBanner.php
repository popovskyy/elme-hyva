<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Image as CategoryImage;
use Magento\Catalog\ViewModel\Category\Output as CategoryOutput;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * FE-18 category PLP header: name, description, desktop/mobile images, optional background colour.
 *
 * Does not fall back to the parent name/description (old Overdose Banner bug for level > 2).
 */
class CategoryBanner implements ArgumentInterface
{
    public const ATTR_IMAGE = 'image';

    public const ATTR_MOBILE_IMAGE = 'mobile_image';

    public const ATTR_BACKGROUND_COLOUR = 'background_colour';

    /** Fallback aspect when the file size cannot be read (desktop ~70% of a 1920 frame). */
    private const FALLBACK_DESKTOP = [1344, 720];

    private const FALLBACK_MOBILE = [750, 420];

    private ?string $mediaPath = null;

    public function __construct(
        private readonly Registry $registry,
        private readonly CategoryImage $categoryImage,
        private readonly CategoryOutput $categoryOutput,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    public function getCategory(): ?Category
    {
        $category = $this->registry->registry('current_category');

        return $category instanceof Category ? $category : null;
    }

    public function getName(): string
    {
        $category = $this->getCategory();

        return $category ? (string) $category->getName() : '';
    }

    public function getHeadingId(): string
    {
        return 'category-banner-heading';
    }

    public function hasImage(): bool
    {
        return $this->getDesktopImageUrl() !== '';
    }

    public function getDesktopImageUrl(): string
    {
        return $this->imageUrl(self::ATTR_IMAGE);
    }

    /**
     * Mobile asset when set; otherwise the desktop image (SEO / ticket contract).
     */
    public function getMobileImageUrl(): string
    {
        $mobile = $this->imageUrl(self::ATTR_MOBILE_IMAGE);

        return $mobile !== '' ? $mobile : $this->getDesktopImageUrl();
    }

    public function hasDistinctMobileImage(): bool
    {
        $mobile = $this->imageUrl(self::ATTR_MOBILE_IMAGE);

        return $mobile !== '' && $mobile !== $this->getDesktopImageUrl();
    }

    /**
     * Informative alt for the category hero — never empty.
     */
    public function getImageAlt(): string
    {
        $category = $this->getCategory();
        if (!$category) {
            return '';
        }

        $label = trim((string) $category->getData('image_label'));

        return $label !== '' ? $label : (string) $category->getName();
    }

    /**
     * @return array{width: int, height: int}
     */
    public function getDesktopImageSize(): array
    {
        return $this->imageSize(self::ATTR_IMAGE, self::FALLBACK_DESKTOP);
    }

    /**
     * @return array{width: int, height: int}
     */
    public function getMobileImageSize(): array
    {
        if ($this->hasDistinctMobileImage()) {
            return $this->imageSize(self::ATTR_MOBILE_IMAGE, self::FALLBACK_MOBILE);
        }

        return $this->getDesktopImageSize();
    }

    /**
     * Filtered description HTML, or empty string when the attribute is empty.
     * Empty means the template must omit the description wrapper.
     */
    public function getDescriptionHtml(): string
    {
        $category = $this->getCategory();
        if (!$category) {
            return '';
        }

        $description = $category->getDescription();
        if ($description === null || $description === '') {
            return '';
        }

        return (string) $this->categoryOutput->categoryAttribute(
            $category,
            $description,
            'description'
        );
    }

    /**
     * Admin hex/CSS colour for the banner band. Presentation only — not schema.
     */
    public function getBackgroundColour(): string
    {
        $category = $this->getCategory();
        if (!$category) {
            return '';
        }

        return trim((string) $category->getData(self::ATTR_BACKGROUND_COLOUR));
    }

    private function imageUrl(string $attributeCode): string
    {
        $category = $this->getCategory();
        if (!$category || !$category->getData($attributeCode)) {
            return '';
        }

        try {
            return (string) $this->categoryImage->getUrl($category, $attributeCode);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array{0: int, 1: int} $fallback
     * @return array{width: int, height: int}
     */
    private function imageSize(string $attributeCode, array $fallback): array
    {
        $path = $this->mediaRelativePath($attributeCode);
        if ($path !== null) {
            $absolute = $this->mediaAbsolutePath($path);
            if ($absolute !== null) {
                try {
                    $size = getimagesize($absolute);
                    if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
                        return ['width' => (int) $size[0], 'height' => (int) $size[1]];
                    }
                } catch (Throwable) {
                    // fall through
                }
            }
        }

        return ['width' => $fallback[0], 'height' => $fallback[1]];
    }

    private function mediaRelativePath(string $attributeCode): ?string
    {
        $category = $this->getCategory();
        if (!$category) {
            return null;
        }

        $image = $category->getData($attributeCode);
        if (!is_string($image) || $image === '') {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            try {
                $mediaBase = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                if (str_starts_with($image, $mediaBase)) {
                    return ltrim(substr($image, strlen($mediaBase)), '/');
                }
            } catch (Throwable) {
                return null;
            }

            return null;
        }

        if (str_starts_with($image, '/')) {
            return null;
        }

        if (str_starts_with($image, 'catalog/category/')) {
            return $image;
        }

        return 'catalog/category/' . ltrim($image, '/');
    }

    private function mediaAbsolutePath(string $relative): ?string
    {
        $base = $this->mediaPath();
        if ($base === null) {
            return null;
        }

        $absolute = $base . '/' . ltrim($relative, '/');

        return is_file($absolute) ? $absolute : null;
    }

    private function mediaPath(): ?string
    {
        if ($this->mediaPath !== null) {
            return $this->mediaPath;
        }

        try {
            $this->mediaPath = $this->filesystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath();
        } catch (Throwable) {
            $this->mediaPath = null;
        }

        return $this->mediaPath;
    }
}
