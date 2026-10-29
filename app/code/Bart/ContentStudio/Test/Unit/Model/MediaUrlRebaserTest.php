<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model;

use Bart\ContentStudio\Model\MediaUrlRebaser;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MediaUrlRebaserTest extends TestCase
{
    #[DataProvider('valueProvider')]
    public function testRebase(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->rebaser()->rebase($value));
    }

    public static function valueProvider(): array
    {
        return [
            'old host, file exists' => [
                'https://elme-hyva.local/media/bart_content_studio/fe14/stems-wide.jpg',
                'https://preview.example/media/bart_content_studio/fe14/stems-wide.jpg',
            ],
            'pub/media and a query string' => [
                'https://old.example/pub/media/bart_content_studio/fe14/stems-wide.jpg?v=2',
                'https://preview.example/media/bart_content_studio/fe14/stems-wide.jpg?v=2',
            ],
            'file missing stays' => [
                'https://cdn.example/media/other/missing.jpg',
                'https://cdn.example/media/other/missing.jpg',
            ],
            'not a media URL stays' => ['/flowers-plants.html', '/flowers-plants.html'],
            'text with a URL inside stays' => [
                'See https://elme-hyva.local/media/bart_content_studio/fe14/stems-wide.jpg',
                'See https://elme-hyva.local/media/bart_content_studio/fe14/stems-wide.jpg',
            ],
        ];
    }

    private function rebaser(): MediaUrlRebaser
    {
        $media = $this->createMock(ReadInterface::class);
        $media->method('isFile')->willReturnCallback(
            static fn (string $path): bool => $path === 'bart_content_studio/fe14/stems-wide.jpg'
        );
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($media);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://preview.example/media/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new MediaUrlRebaser($storeManager, $filesystem);
    }
}
