<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Elme\QtyRules\Model\QtyRules;
use Elme\Theme\ViewModel\Lookbook;
use Elme\Theme\ViewModel\ProductTile;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see Lookbook
 */
class LookbookTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-lookbook-test-' . uniqid();
        mkdir($this->mediaDir . '/looks', 0777, true);
        file_put_contents(
            $this->mediaDir . '/looks/look.png',
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 1440, 960) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->mediaDir . '/looks/look.png');
        @rmdir($this->mediaDir . '/looks');
        @rmdir($this->mediaDir);
    }

    public function testJsonLdHasNoProductOrOffers(): void
    {
        $cms = $this->createMock(CmsPage::class);
        $cms->method('getIdentifier')->willReturn('shop-the-looks');
        $cms->method('getMetaTitle')->willReturn('Shop the Looks | Elme Living Wholesale Inspiration');
        $cms->method('getMetaDescription')->willReturn('Wholesale styled looks.');
        $cms->method('getTitle')->willReturn('Shop the Looks');

        $entryRepo = $this->createMock(EntryRepositoryInterface::class);
        $entry = $this->createMock(EntryInterface::class);
        $entry->method('isActive')->willReturn(true);
        $entry->method('getEntryData')->willReturn([
            'looks' => [
                [
                    'title' => 'Living vignette',
                    'image' => self::MEDIA_URL . 'looks/look.png',
                    'description' => 'Showroom set',
                    'hotspots' => [
                        ['x' => 20, 'y' => 30, 'sku' => 'FE53-VASE'],
                    ],
                ],
            ],
        ]);
        $entryRepo->method('getByIdentifier')->willReturn($entry);

        $graph = $this->viewModel(false, $cms, $entryRepo)->getJsonLdGraph();
        $json = (string) json_encode($graph);

        $this->assertSame(['CollectionPage', 'WebPage'], $graph['@graph'][0]['@type']);
        $this->assertSame('ItemList', $graph['@graph'][2]['@type']);
        $this->assertSame('ImageObject', $graph['@graph'][2]['itemListElement'][0]['item']['@type']);
        $this->assertStringNotContainsString('"Product"', $json);
        $this->assertStringNotContainsString('"Offer"', $json);
        $this->assertStringNotContainsString('offers', $json);
        $this->assertStringNotContainsString('FE53-VASE', $json);
    }

    public function testSkipsLooksWithoutImageOrHotspots(): void
    {
        $items = $this->viewModel(false)->getLooks([
            'looks' => [
                ['title' => 'No image', 'hotspots' => [['x' => 10, 'y' => 10, 'sku' => 'X']]],
                [
                    'title' => 'No pins',
                    'image' => self::MEDIA_URL . 'looks/look.png',
                    'hotspots' => [],
                ],
            ],
        ]);

        $this->assertSame([], $items);
    }

    public function testRoomFiltersOnlyPresentRooms(): void
    {
        $filters = $this->viewModel(false)->getRoomFilters([
            ['room' => 'dining'],
            ['room' => 'living'],
            ['room' => 'dining'],
        ]);

        $this->assertSame(
            [
                ['code' => 'dining', 'label' => 'Dining', 'count' => 2],
                ['code' => 'living', 'label' => 'Living', 'count' => 1],
            ],
            $filters
        );
    }

    public function testFeatureLayoutReadsLooksFromSourceEntry(): void
    {
        $entryRepo = $this->createMock(EntryRepositoryInterface::class);
        $entry = $this->createMock(EntryInterface::class);
        $entry->method('isActive')->willReturn(true);
        $entry->method('getEntryData')->willReturn([
            'looks' => [
                ['title' => 'No pins', 'image' => self::MEDIA_URL . 'looks/look.png', 'hotspots' => []],
            ],
        ]);
        $entryRepo->expects($this->atLeastOnce())
            ->method('getByIdentifier')
            ->with('shop-the-looks-gallery')
            ->willReturn($entry);

        $viewModel = $this->viewModel(false, null, $entryRepo);
        $content = ['layout' => 'feature', 'source' => 'shop-the-looks-gallery', 'looks' => []];

        $this->assertSame(Lookbook::LAYOUT_FEATURE, $viewModel->getLayout($content));
        $this->assertSame(Lookbook::LAYOUT_GALLERY, $viewModel->getLayout([]));
        // the source look has no resolvable pins, so the feature shows nothing (UX-01)
        $this->assertSame([], $viewModel->getFeatureLooks($content));
    }

    public function testLinkMakesPathsAbsolute(): void
    {
        $viewModel = $this->viewModel(false);

        $this->assertSame(
            ['label' => 'Shop the Looks', 'url' => 'https://elme.test/shop-the-looks'],
            $viewModel->getLink(['link_label' => 'Shop the Looks', 'link' => '/shop-the-looks'])
        );
        $this->assertNull($viewModel->getLink(['link_label' => 'Shop the Looks']));
    }

    private function viewModel(
        bool $loggedIn,
        ?CmsPage $cmsPage = null,
        ?EntryRepositoryInterface $entryRepository = null
    ): Lookbook {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static function (string $type = '') {
                if ($type === UrlInterface::URL_TYPE_MEDIA) {
                    return LookbookTest::MEDIA_URL;
                }
                return 'https://elme.test/';
            }
        );
        $store->method('getUrl')->willReturnCallback(
            static fn (string $route = ''): string => 'https://elme.test/' . ltrim($route, '/')
        );
        $store->method('getId')->willReturn(1);

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

        $loginState = $this->createMock(LoginState::class);
        $loginState->method('isLoggedIn')->willReturn($loggedIn);

        $cms = $cmsPage ?? $this->createMock(CmsPage::class);
        $cms->method('getIdentifier')->willReturn('shop-the-looks');

        $repo = $entryRepository ?? $this->createMock(EntryRepositoryInterface::class);

        return new Lookbook(
            $storeManager,
            $filesystem,
            $loginState,
            $cms,
            $repo,
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(ImageHelper::class),
            $this->createMock(CartHelper::class),
            $this->createMock(QtyRules::class),
            $this->createMock(ProductTile::class),
            $this->createMock(GetProductSalableQtyInterface::class),
            $this->createMock(StockResolverInterface::class),
            $this->createMock(IsSourceItemManagementAllowedForProductTypeInterface::class),
            $this->createMock(CacheInterface::class)
        );
    }
}
