<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\CategoryTiles;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Silent storefront failures: a dropped tile or a wrong width/height still renders a page.
 *
 * @see CategoryTiles
 */
class CategoryTilesTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-category-tiles-test-' . uniqid();
        mkdir($this->mediaDir . '/tiles', 0777, true);
        file_put_contents(
            $this->mediaDir . '/tiles/tile.png',
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 620, 450) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->mediaDir . '/tiles/tile.png');
        @rmdir($this->mediaDir . '/tiles');
        @rmdir($this->mediaDir);
    }

    public function testIncompleteTilesAreDropped(): void
    {
        $tiles = $this->viewModel()->getTiles(['tiles' => [
            ['link' => '', 'label' => 'A', 'image' => self::MEDIA_URL . 'tiles/tile.png'],
            ['link' => '/a', 'label' => '  ', 'image' => self::MEDIA_URL . 'tiles/tile.png'],
            ['link' => '/a', 'label' => 'A', 'image' => ''],
            ['link' => '/vessels.html', 'label' => ' Vessels ', 'image' => self::MEDIA_URL . 'tiles/tile.png'],
            'not a row',
        ]]);

        $this->assertCount(1, $tiles);
        $this->assertSame('Vessels', $tiles[0]['label']);
        $this->assertSame('/vessels.html', $tiles[0]['link']);
    }

    public function testAtMostSixteenTiles(): void
    {
        $row = [
            'link' => '/x',
            'label' => 'X',
            'image' => '/media/tiles/tile.png',
        ];

        $this->assertCount(
            CategoryTiles::MAX_TILES,
            $this->viewModel()->getTiles(['tiles' => array_fill(0, 20, $row)])
        );
    }

    public function testImageSizeIsReadFromTheMediaFile(): void
    {
        $tile = $this->viewModel()->getTiles(['tiles' => [[
            'link' => '/x',
            'label' => 'X',
            'image' => self::MEDIA_URL . 'tiles/tile.png',
        ]]])[0];

        $this->assertSame(
            ['src' => self::MEDIA_URL . 'tiles/tile.png', 'width' => 620, 'height' => 450],
            $tile['image']
        );
    }

    public function testMissingImageFallsBackToRecommendedSize(): void
    {
        $tile = $this->viewModel()->getTiles(['tiles' => [[
            'link' => '/x',
            'label' => 'X',
            'image' => 'https://cdn.example.com/missing.jpg',
        ]]])[0];

        $this->assertSame(CategoryTiles::IMAGE_SIZE, [$tile['image']['width'], $tile['image']['height']]);
    }

    public function testHeadingIsTrimmed(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('Shop by category', $vm->getHeading(['heading' => ' Shop by category ']));
        $this->assertSame('', $vm->getHeading(['heading' => '  ']));
        $this->assertSame('', $vm->getHeading([]));
    }

    private function viewModel(): CategoryTiles
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

        return new CategoryTiles($storeManager, $filesystem);
    }
}
