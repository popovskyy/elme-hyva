<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\InstagramSlider;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see InstagramSlider
 */
class InstagramSliderTest extends TestCase
{
    private const MEDIA_URL = 'https://elme.test/media/';

    private string $mediaDir;

    private Registry $registry;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/elme-instagram-slider-test-' . uniqid();
        mkdir($this->mediaDir . '/posts', 0777, true);
        // Minimal valid PNG header with 800x800 IHDR (enough for getimagesize width/height).
        file_put_contents(
            $this->mediaDir . '/posts/post.png',
            "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . pack('NN', 800, 800) . "\x08\x02\x00\x00\x00" . pack('N', 0)
        );
        $this->registry = new Registry();
    }

    protected function tearDown(): void
    {
        @unlink($this->mediaDir . '/posts/post.png');
        @rmdir($this->mediaDir . '/posts');
        @rmdir($this->mediaDir);
    }

    public function testIncompletePostsAreDropped(): void
    {
        $posts = $this->viewModel()->getPosts(['posts' => [
            ['image' => '', 'image_alt' => 'A', 'post_url' => 'https://ig/a', 'credit_handle' => 'a'],
            [
                'image' => self::MEDIA_URL . 'posts/post.png',
                'image_alt' => 'Styled shelf',
                'post_url' => 'https://www.instagram.com/p/ok/',
                'credit_handle' => ' houseofsmith ',
                'credit_location' => 'Bendigo VIC',
                'skus' => 'FE53-VASE, FE53-LAMP',
            ],
            'not a row',
        ]]);

        $this->assertCount(1, $posts);
        $this->assertSame('houseofsmith', $posts[0]['credit_handle']);
        $this->assertSame(2, $posts[0]['sku_count']);
        $this->assertFalse($posts[0]['external']);
        $this->assertSame('/shoppable-instagram#FE53-VASE', $posts[0]['href']);
        $this->assertSame('View products', $posts[0]['action_label']);
    }

    public function testPostsWithoutSkusLinkExternally(): void
    {
        $post = $this->viewModel()->getPosts(['posts' => [[
            'image' => self::MEDIA_URL . 'posts/post.png',
            'image_alt' => 'Window display',
            'post_url' => 'https://www.instagram.com/p/external/',
            'credit_handle' => 'wildandfolk',
            'skus' => '',
        ]]])[0];

        $this->assertTrue($post['external']);
        $this->assertSame('https://www.instagram.com/p/external/', $post['href']);
        $this->assertSame('View on Instagram', $post['action_label']);
        $this->assertSame(0, $post['sku_count']);
    }

    public function testPdpFilterKeepsMatchingSkusOnly(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getSku')->willReturn('FE53-VASE-SAGE');
        $this->registry->register('current_product', $product);

        $posts = $this->viewModel()->getPosts(['posts' => [
            $this->row('a', 'FE53-VASE-SAGE, OTHER'),
            $this->row('b', 'OTHER'),
            $this->row('c', ''),
        ]], true);

        $this->assertCount(1, $posts);
        $this->assertSame('a', $posts[0]['credit_handle']);
        $this->assertSame(InstagramSlider::PDP_HEADING, $this->viewModel()->getHeading([], true));
    }

    public function testPdpFilterWithNoProductReturnsEmpty(): void
    {
        $this->assertSame([], $this->viewModel()->getPosts(['posts' => [
            $this->row('a', 'FE53-VASE'),
        ]], true));
    }

    public function testEmptyPostsReturnEmpty(): void
    {
        $this->assertSame([], $this->viewModel()->getPosts(['posts' => []]));
        $this->assertSame([], $this->viewModel()->getPosts([]));
    }

    public function testProfileDefaultsToElmeliving(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('elmeliving', $vm->getHandle([]));
        $this->assertSame('https://www.instagram.com/elmeliving/', $vm->getProfileUrl([]));
        $this->assertSame('As styled by you', $vm->getHeading([], false));
        $this->assertSame('Follow Us', $vm->getFollowLabel([]));
    }

    /**
     * @return array{image: string, image_alt: string, post_url: string, credit_handle: string, skus: string}
     */
    private function row(string $handle, string $skus): array
    {
        return [
            'image' => self::MEDIA_URL . 'posts/post.png',
            'image_alt' => 'Alt ' . $handle,
            'post_url' => 'https://www.instagram.com/p/' . $handle . '/',
            'credit_handle' => $handle,
            'skus' => $skus,
        ];
    }

    private function viewModel(): InstagramSlider
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

        return new InstagramSlider($storeManager, $filesystem, $this->registry);
    }
}
