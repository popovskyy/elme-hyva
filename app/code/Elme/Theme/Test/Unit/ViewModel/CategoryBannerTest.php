<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\CategoryBanner;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Image as CategoryImage;
use Magento\Catalog\ViewModel\Category\Output as CategoryOutput;
use Magento\Framework\Filesystem;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * A Cloudinary URL stored on the category must reach the template unchanged. The category image
 * service prefixes any value that does not start with a slash, which would break that URL.
 *
 * @see CategoryBanner
 */
class CategoryBannerTest extends TestCase
{
    public function testAbsoluteImageUrlIsReturnedAsStored(): void
    {
        $url = 'https://res.cloudinary.com/dmndjbrsi/image/upload/v1/stems.jpg';
        $images = $this->createMock(CategoryImage::class);
        $images->expects($this->never())->method('getUrl');

        $banner = $this->banner($images, ['image' => '  ' . $url . '  ']);

        $this->assertSame($url, $banner->getDesktopImageUrl());
        $this->assertTrue($banner->hasImage());
    }

    public function testRelativeImageStillUsesTheCategoryImageService(): void
    {
        $served = 'https://elme.test/media/catalog/category/elme-banner-flowers.jpg';
        $images = $this->createMock(CategoryImage::class);
        $images->expects($this->once())->method('getUrl')->willReturn($served);

        $banner = $this->banner($images, ['image' => 'elme-banner-flowers.jpg']);

        $this->assertSame($served, $banner->getDesktopImageUrl());
    }

    public function testEmptyImageIsNotABanner(): void
    {
        $images = $this->createMock(CategoryImage::class);
        $images->expects($this->never())->method('getUrl');

        $this->assertFalse($this->banner($images, ['image' => '   '])->hasImage());
    }

    /**
     * @param array<string, string> $data
     */
    private function banner(CategoryImage $images, array $data): CategoryBanner
    {
        $category = $this->createMock(Category::class);
        $category->method('getData')->willReturnCallback(
            static fn (string $key): ?string => $data[$key] ?? null
        );

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_category')->willReturn($category);

        return new CategoryBanner(
            $registry,
            $images,
            $this->createMock(CategoryOutput::class),
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Filesystem::class)
        );
    }
}
