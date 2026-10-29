<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\Theme\ViewModel\ContentPages;
use Elme\Theme\ViewModel\Hero;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see ContentPages
 */
class ContentPagesTest extends TestCase
{
    public function testBuildGraphEmitsAboutPageAndBreadcrumbList(): void
    {
        $pages = $this->pages();
        $graph = $pages->buildGraph(
            'AboutPage',
            'https://elme.test/about-us',
            'About Elme Living | Australian Wholesale Flora & Homewares',
            'Australian B2B wholesaler.',
            'About Us',
            '2026-10-29 10:00:00',
            ['url' => 'https://elme.test/media/hero.jpg', 'width' => 2400, 'height' => 1000]
        );

        $this->assertSame('https://schema.org', $graph['@context']);
        $this->assertCount(2, $graph['@graph']);

        $webpage = $graph['@graph'][0];
        $this->assertSame('AboutPage', $webpage['@type']);
        $this->assertSame('https://elme.test/about-us#webpage', $webpage['@id']);
        $this->assertSame(['@id' => 'https://elme.test/#website'], $webpage['isPartOf']);
        $this->assertSame(['@id' => 'https://elme.test/#organization'], $webpage['about']);
        $this->assertSame('2026-10-29T10:00:00', $webpage['dateModified']);
        $this->assertSame('ImageObject', $webpage['primaryImageOfPage']['@type']);
        $this->assertSame(2400, $webpage['primaryImageOfPage']['width']);

        $crumbs = $graph['@graph'][1];
        $this->assertSame('BreadcrumbList', $crumbs['@type']);
        $this->assertSame('About Us', $crumbs['itemListElement'][1]['item']['name']);
    }

    public function testEncodeGraphUsesUnescapedSlashesAndHexEscapes(): void
    {
        $json = $this->pages()->encodeGraph(['a' => 'https://elme.test/x', 'b' => '<tag>&']);
        $this->assertStringContainsString('https://elme.test/x', $json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringContainsString('\u003C', $json);
    }

    public function testAboutUsIsRegisteredAsAboutPage(): void
    {
        $cms = $this->createMock(CmsPage::class);
        $cms->method('getIdentifier')->willReturn('about-us');

        $pages = new ContentPages(
            $cms,
            $this->storeManager(),
            $this->createMock(EntryRepositoryInterface::class),
            $this->createMock(Hero::class)
        );

        $this->assertTrue($pages->isContentPage());
        $this->assertSame('AboutPage', $pages->getSchemaType());
        $this->assertSame('About Us', $pages->getBreadcrumbLabel());
    }

    private function pages(): ContentPages
    {
        return new ContentPages(
            $this->createMock(CmsPage::class),
            $this->storeManager(),
            $this->createMock(EntryRepositoryInterface::class),
            $this->createMock(Hero::class)
        );
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://elme.test/');

        $manager = $this->createMock(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }
}
