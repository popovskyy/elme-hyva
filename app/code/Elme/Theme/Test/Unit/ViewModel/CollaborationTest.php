<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\Collaboration;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see Collaboration
 */
class CollaborationTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    /** @var ProductRepositoryInterface&MockObject */
    private ProductRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-collaboration-test-' . uniqid();
        mkdir($this->mediaDir, 0777, true);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->mediaDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->mediaDir);
    }

    public function testHeadingAndSectionHeadingDefaults(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('Maison Soleil', $vm->getHeading(['heading' => ' Maison Soleil ']));
        $this->assertTrue($vm->isPageTitle(['is_page_title' => true]));
        $this->assertFalse($vm->isPageTitle([]));
        $this->assertSame('Shop the look', $vm->getSectionHeading([]));
        $this->assertSame('Shop the look', $vm->getSectionHeading(['section_heading' => 'Shop the look']));
    }

    public function testGetProductsSkipsMissingDisabledAndDuplicatesPreservesOrder(): void
    {
        $first = $this->product('A', Status::STATUS_ENABLED, Visibility::VISIBILITY_BOTH);
        $disabled = $this->product('B', Status::STATUS_DISABLED, Visibility::VISIBILITY_BOTH);
        $hidden = $this->product('C', Status::STATUS_ENABLED, Visibility::VISIBILITY_NOT_VISIBLE);
        $second = $this->product('D', Status::STATUS_ENABLED, Visibility::VISIBILITY_IN_CATALOG);

        $this->productRepository->method('get')->willReturnCallback(
            function (string $sku) use ($first, $disabled, $hidden, $second) {
                return match ($sku) {
                    'A' => $first,
                    'B' => $disabled,
                    'C' => $hidden,
                    'D' => $second,
                    'MISSING' => throw new NoSuchEntityException(__('Missing')),
                    default => throw new NoSuchEntityException(__('Missing')),
                };
            }
        );

        $products = $this->viewModel()->getProducts([
            'products' => [
                ['sku' => 'A'],
                ['sku' => 'MISSING'],
                ['sku' => 'B'],
                ['sku' => 'a'],
                ['sku' => 'C'],
                ['sku' => 'D'],
                ['sku' => ''],
            ],
        ]);

        $this->assertSame(['A', 'D'], array_map(static fn (Product $p) => $p->getSku(), $products));
    }

    public function testCollageReadsImageSizeAndFallsBackAltToHeading(): void
    {
        $relative = 'fe89/collage.png';
        $path = $this->mediaDir . '/' . $relative;
        mkdir(dirname($path), 0777, true);
        file_put_contents(
            $path,
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 1125, 2705) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );

        $collage = $this->viewModel()->getCollage([
            'heading' => 'THREE BIRDS',
            'collage' => self::MEDIA_URL . $relative,
            'collage_alt' => '',
        ]);

        $this->assertNotNull($collage);
        $this->assertSame(self::MEDIA_URL . $relative, $collage['src']);
        $this->assertSame(1125, $collage['width']);
        $this->assertSame(2705, $collage['height']);
        $this->assertSame('THREE BIRDS', $collage['alt']);
    }

    public function testBannerMobileFallsBackToDesktop(): void
    {
        $vm = $this->viewModel();
        $content = [
            'banner_desktop' => self::MEDIA_URL . 'banner.jpg',
            'banner_alt' => 'Campaign banner',
            'banner_mobile' => '',
        ];

        $desktop = $vm->getBannerDesktop($content);
        $mobile = $vm->getBannerMobile($content);

        $this->assertNotNull($desktop);
        $this->assertSame($desktop, $mobile);
        $this->assertSame('Campaign banner', $desktop['alt']);
        $this->assertNull($vm->getBannerDesktop([]));
    }

    private function product(string $sku, int $status, int $visibility): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getStatus')->willReturn($status);
        $product->method('getVisibility')->willReturn($visibility);
        $product->expects($this->any())->method('setData')->willReturnSelf();

        return $product;
    }

    private function viewModel(): Collaboration
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn(self::MEDIA_URL);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $directory = $this->createMock(ReadInterface::class);
        $directory->method('getAbsolutePath')->willReturn($this->mediaDir . '/');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($directory);

        return new Collaboration($storeManager, $filesystem, $this->productRepository);
    }
}
