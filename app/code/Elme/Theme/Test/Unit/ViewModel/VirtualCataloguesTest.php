<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Elme\Theme\ViewModel\VirtualCatalogues;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see VirtualCatalogues
 */
class VirtualCataloguesTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-virtual-catalogues-test-' . uniqid();
        mkdir($this->mediaDir . '/covers', 0777, true);
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

    public function testGuestCataloguesOmitPrivateUrls(): void
    {
        $items = $this->viewModel(false)->getCatalogues([
            'catalogues' => [
                [
                    'title' => 'SS27',
                    'image' => self::MEDIA_URL . 'covers/cover.png',
                    'open_url' => 'https://e.issuu.com/embed.html?d=ss27&u=elmeliving',
                    'shop_url' => '/new-arrivals',
                ],
            ],
        ]);

        $this->assertCount(1, $items);
        $this->assertSame('SS27', $items[0]['title']);
        $this->assertSame('', $items[0]['open_url']);
        $this->assertSame('', $items[0]['shop_url']);
    }

    public function testLoggedInCataloguesIncludePrivateUrls(): void
    {
        $open = 'https://e.issuu.com/embed.html?d=ss27&u=elmeliving';
        $items = $this->viewModel(true)->getCatalogues([
            'catalogues' => [
                [
                    'title' => 'SS27',
                    'image' => self::MEDIA_URL . 'covers/cover.png',
                    'open_url' => $open,
                    'shop_url' => '/new-arrivals',
                ],
            ],
        ]);

        $this->assertSame($open, $items[0]['open_url']);
        $this->assertSame('/new-arrivals', $items[0]['shop_url']);
    }

    public function testJsonLdHasNoFlipbookUrl(): void
    {
        $cms = $this->createMock(CmsPage::class);
        $cms->method('getIdentifier')->willReturn('virtual-catalogues');
        $cms->method('getMetaTitle')->willReturn('Virtual Catalogues | Elme Living Wholesale Ranges');
        $cms->method('getMetaDescription')->willReturn('Wholesale catalogues.');
        $cms->method('getTitle')->willReturn('Virtual Catalogues');

        $entryRepo = $this->createMock(EntryRepositoryInterface::class);
        $entry = $this->createMock(EntryInterface::class);
        $entry->method('isActive')->willReturn(true);
        $entry->method('getEntryData')->willReturn([
            'catalogues' => [
                [
                    'title' => 'SS27',
                    'image' => self::MEDIA_URL . 'covers/cover.png',
                    'open_url' => 'https://e.issuu.com/embed.html?d=secret&u=elmeliving',
                    'description' => 'Season edit',
                ],
            ],
        ]);
        $entryRepo->method('getByIdentifier')->willReturn($entry);

        $graph = $this->viewModel(false, $cms, $entryRepo)->getJsonLdGraph();
        $json = json_encode($graph);

        $this->assertStringNotContainsString('issuu', (string) $json);
        $this->assertStringNotContainsString('secret', (string) $json);
        $this->assertSame(['CollectionPage', 'WebPage'], $graph['@graph'][0]['@type']);
        $this->assertSame('ItemList', $graph['@graph'][2]['@type']);
        $this->assertSame('CreativeWork', $graph['@graph'][2]['itemListElement'][0]['item']['@type']);
        $this->assertArrayNotHasKey('url', $graph['@graph'][2]['itemListElement'][0]['item']);
    }

    public function testSkipsIncompleteRows(): void
    {
        $this->assertSame(
            [],
            $this->viewModel(true)->getCatalogues([
                'catalogues' => [
                    ['title' => 'Only title'],
                    ['image' => self::MEDIA_URL . 'covers/cover.png'],
                ],
            ])
        );
    }

    public function testGuestMessageFromContent(): void
    {
        $message = 'Sign in to see our brand new Special Offers Catalogue!';
        $this->assertSame(
            $message,
            $this->viewModel(false)->getGuestMessage(['guest_message' => $message])
        );
        $this->assertSame('', $this->viewModel(false)->getGuestMessage([]));
    }

    public function testSpecialOffersPageIsRecognised(): void
    {
        $cms = $this->createMock(CmsPage::class);
        $cms->method('getIdentifier')->willReturn('virtual-special-offers-catalogue');
        $cms->method('getMetaTitle')->willReturn('Special Offers Catalogue | Elme Living Product Showcase');
        $cms->method('getMetaDescription')->willReturn('Best value offer.');
        $cms->method('getTitle')->willReturn('Special Offers Catalogue');

        $entryRepo = $this->createMock(EntryRepositoryInterface::class);
        $entry = $this->createMock(EntryInterface::class);
        $entry->method('isActive')->willReturn(true);
        $entry->method('getEntryData')->willReturn([
            'guest_message' => 'Sign in to see our brand new Special Offers Catalogue!',
            'catalogues' => [
                [
                    'title' => 'Special Offers',
                    'image' => self::MEDIA_URL . 'covers/cover.png',
                    'open_url' => 'https://e.issuu.com/embed.html?d=secret&u=elmeliving',
                ],
            ],
        ]);
        $entryRepo->method('getByIdentifier')->willReturnCallback(
            function (string $identifier) use ($entry) {
                $this->assertSame('special-offers-catalogue', $identifier);
                return $entry;
            }
        );

        $vm = $this->viewModel(false, $cms, $entryRepo);
        $this->assertTrue($vm->isVirtualCataloguesPage());
        $this->assertSame(
            'https://elme.test/virtual-special-offers-catalogue',
            $vm->getCanonicalUrl()
        );

        $graph = $vm->getJsonLdGraph();
        $json = (string) json_encode($graph);
        $this->assertStringNotContainsString('issuu', $json);
        $this->assertStringNotContainsString('secret', $json);
        $this->assertSame('Special Offers Catalogue', $graph['@graph'][1]['itemListElement'][1]['item']['name']);
        $this->assertSame('Elme Living special offers catalogue', $graph['@graph'][2]['name']);
    }

    private function viewModel(
        bool $loggedIn,
        ?CmsPage $cmsPage = null,
        ?EntryRepositoryInterface $entryRepository = null
    ): VirtualCatalogues {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static function (string $type = '') {
                if ($type === UrlInterface::URL_TYPE_MEDIA) {
                    return VirtualCataloguesTest::MEDIA_URL;
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
        if ($cmsPage === null) {
            $cms->method('getIdentifier')->willReturn('virtual-catalogues');
        }

        $repo = $entryRepository ?? $this->createMock(EntryRepositoryInterface::class);

        return new VirtualCatalogues($storeManager, $filesystem, $loginState, $cms, $repo);
    }
}
