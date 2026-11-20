<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\CatalogueCovers;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see CatalogueCovers
 */
class CatalogueCoversTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-catalogue-covers-test-' . uniqid();
        mkdir($this->mediaDir . '/covers', 0777, true);
        // Minimal IHDR so getimagesize can read 620x877.
        file_put_contents(
            $this->mediaDir . '/covers/cover.png',
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 620, 877) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->mediaDir . '/covers/cover.png');
        @rmdir($this->mediaDir . '/covers');
        @rmdir($this->mediaDir);
    }

    public function testEmptyCardsWhenRepeaterMissing(): void
    {
        $this->assertSame([], $this->viewModel()->getCards([]));
    }

    public function testSkipsIncompleteRowsAndReadsImageSize(): void
    {
        $cards = $this->viewModel()->getCards([
            'cards' => [
                ['title' => 'Lookbook', 'link' => '/shop-the-looks'],
                [
                    'title' => 'Virtual Catalogues',
                    'link' => '/virtual-catalogues',
                    'image' => self::MEDIA_URL . 'covers/cover.png',
                    'image_alt' => 'Catalogue cover',
                    'text' => '  Full ranges  ',
                ],
            ],
        ]);

        $this->assertCount(1, $cards);
        $this->assertSame('Virtual Catalogues', $cards[0]['title']);
        $this->assertSame('/virtual-catalogues', $cards[0]['link']);
        $this->assertSame(
            ['src' => self::MEDIA_URL . 'covers/cover.png', 'width' => 620, 'height' => 877],
            $cards[0]['image']
        );
        $this->assertSame('Catalogue cover', $cards[0]['image_alt']);
        $this->assertSame('Full ranges', $cards[0]['text']);
    }

    public function testSeeAllRequiresBothFields(): void
    {
        $vm = $this->viewModel();

        $this->assertNull($vm->getSeeAll(['see_all_label' => 'See All']));
        $this->assertSame(
            ['label' => 'See All Catalogues', 'link' => '/virtual-catalogues'],
            $vm->getSeeAll([
                'see_all_label' => 'See All Catalogues',
                'see_all_link' => '/virtual-catalogues',
            ])
        );
    }

    public function testAtMostFourCards(): void
    {
        $row = [
            'title' => 'X',
            'link' => '/x',
            'image' => '/media/covers/cover.png',
        ];

        $this->assertCount(
            CatalogueCovers::MAX_CARDS,
            $this->viewModel()->getCards(['cards' => array_fill(0, 8, $row)])
        );
    }

    private function viewModel(): CatalogueCovers
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

        return new CatalogueCovers($storeManager, $filesystem);
    }
}
