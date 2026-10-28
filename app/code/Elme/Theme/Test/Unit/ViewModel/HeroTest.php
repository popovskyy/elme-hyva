<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\Hero;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every failure here is silent on the storefront: a dropped slide, a wrong width/height on the LCP image,
 * or an autoplay that ignores the editor's switch all still render a page.
 *
 * @see Hero
 */
class HeroTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-hero-test-' . uniqid();
        mkdir($this->mediaDir . '/hero', 0777, true);
        // Smallest file getimagesize() reads: a PNG signature and its IHDR chunk, 640x210.
        file_put_contents(
            $this->mediaDir . '/hero/wide.png',
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 640, 210) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->mediaDir . '/hero/wide.png');
        @rmdir($this->mediaDir . '/hero');
        @rmdir($this->mediaDir);
    }

    public function testSlidesWithoutDesktopImageOrHeadingAreDropped(): void
    {
        $slides = $this->hero()->getSlides(['slides' => [
            ['image_desktop' => '', 'heading' => 'No image'],
            ['image_desktop' => self::MEDIA_URL . 'hero/wide.png', 'heading' => '  '],
            ['image_desktop' => self::MEDIA_URL . 'hero/wide.png', 'heading' => ' Kept '],
            'not a row',
        ]]);

        $this->assertCount(1, $slides);
        $this->assertSame('Kept', $slides[0]['heading']);
    }

    public function testAtMostFiveSlides(): void
    {
        $row = ['image_desktop' => '/media/hero/wide.png', 'heading' => 'H'];

        $this->assertCount(Hero::MAX_SLIDES, $this->hero()->getSlides(['slides' => array_fill(0, 7, $row)]));
    }

    public function testImageSizeIsReadFromTheMediaFile(): void
    {
        $slide = $this->hero()->getSlides(['slides' => [[
            'image_desktop' => self::MEDIA_URL . 'hero/wide.png',
            // Root-relative, as the admin preview frame rewrites media URLs.
            'image_mobile' => '/media/hero/wide.png',
            'heading' => 'H',
        ]]])[0];

        $this->assertSame(['src' => self::MEDIA_URL . 'hero/wide.png', 'width' => 640, 'height' => 210], $slide['desktop']);
        $this->assertSame(['src' => '/media/hero/wide.png', 'width' => 640, 'height' => 210], $slide['mobile']);
    }

    public function testUnknownOrMissingImagesFallBackToTheRecommendedSize(): void
    {
        $slide = $this->hero()->getSlides(['slides' => [[
            'image_desktop' => 'https://cdn.example.com/hero.jpg',
            'image_mobile' => self::MEDIA_URL . 'hero/missing.jpg',
            'heading' => 'H',
        ]]])[0];

        $this->assertSame(Hero::DESKTOP_SIZE, [$slide['desktop']['width'], $slide['desktop']['height']]);
        $this->assertSame(Hero::MOBILE_SIZE, [$slide['mobile']['width'], $slide['mobile']['height']]);
    }

    public function testPathTraversalOutOfTheMediaFolderIsNotRead(): void
    {
        $slide = $this->hero()->getSlides(['slides' => [[
            'image_desktop' => self::MEDIA_URL . '../hero/wide.png',
            'heading' => 'H',
        ]]])[0];

        $this->assertSame(Hero::DESKTOP_SIZE, [$slide['desktop']['width'], $slide['desktop']['height']]);
    }

    public function testNoMobileImageMeansNull(): void
    {
        $slide = $this->hero()->getSlides(['slides' => [['image_desktop' => '/media/hero/wide.png', 'heading' => 'H']]])[0];

        $this->assertNull($slide['mobile']);
    }

    public function testButtonNeedsBothLabelAndLink(): void
    {
        $slides = $this->hero()->getSlides(['slides' => [
            ['image_desktop' => '/media/hero/wide.png', 'heading' => 'A', 'button_label' => 'Shop', 'button_link' => ''],
            ['image_desktop' => '/media/hero/wide.png', 'heading' => 'B', 'button_label' => '', 'button_link' => '/x'],
            ['image_desktop' => '/media/hero/wide.png', 'heading' => 'C', 'button_label' => 'Shop', 'button_link' => '/x'],
        ]]);

        $this->assertSame(['', '', 'Shop'], array_column($slides, 'button_label'));
        $this->assertSame(['', '', '/x'], array_column($slides, 'button_link'));
    }

    public function testUnknownPanelPositionIsCentre(): void
    {
        $slides = $this->hero()->getSlides(['slides' => [
            ['image_desktop' => '/media/hero/wide.png', 'heading' => 'A', 'panel_position' => 'right'],
            ['image_desktop' => '/media/hero/wide.png', 'heading' => 'B', 'panel_position' => 'top'],
        ]]);

        $this->assertSame(['right', 'center'], array_column($slides, 'panel_position'));
    }

    #[DataProvider('autoplayProvider')]
    public function testAutoplayInterval(array $content, int $expected): void
    {
        $this->assertSame($expected, $this->hero()->getAutoplayInterval($content));
    }

    public static function autoplayProvider(): array
    {
        return [
            'switched off' => [['autoplay' => false, 'interval' => 7], 0],
            'off as stored by a form' => [['autoplay' => '0'], 0],
            'missing switch is the schema default, on' => [['interval' => 9], 9000],
            'schema default stored as "1"' => [['autoplay' => '1'], 7000],
            'too fast is clamped' => [['autoplay' => true, 'interval' => 1], 4000],
            'too slow is clamped' => [['autoplay' => true, 'interval' => 60], 15000],
            'not a number' => [['autoplay' => true, 'interval' => 'soon'], 7000],
        ];
    }

    public function testPageTitleSwitch(): void
    {
        $this->assertTrue($this->hero()->isPageTitle(['is_page_title' => true]));
        $this->assertFalse($this->hero()->isPageTitle(['is_page_title' => false]));
        $this->assertFalse($this->hero()->isPageTitle([]));
    }

    private function hero(): Hero
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn(self::MEDIA_URL);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $mediaDir = $this->mediaDir;
        $media = $this->createMock(ReadInterface::class);
        $media->method('isFile')->willReturnCallback(
            static fn (string $path): bool => is_file($mediaDir . '/' . $path)
        );
        $media->method('getAbsolutePath')->willReturnCallback(
            static fn (string $path): string => $mediaDir . '/' . $path
        );
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        return new Hero($storeManager, $filesystem);
    }
}
