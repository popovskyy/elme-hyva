<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-92: seed /virtual-special-offers-catalogue CMS page + Content Studio catalogue entry.
 *
 * Reuses type `virtual_catalogues` (same template as FE-50). Guest message + trade CTAs when logged
 * out; flipbook open_url only when signed in. Open URL is editable in Content Studio admin.
 */
class CreateSpecialOffersCataloguePage implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe92';

    private const ENTRY_IDENTIFIER = 'special-offers-catalogue';

    private const PAGE_IDENTIFIER = 'virtual-special-offers-catalogue';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        self::syncImagesToMedia();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->ensureEntry($mediaBase);
        $this->upsertPage();

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        $from = $sourceDir . '/virtual-catalogues-cover.jpg';
        $to = $targetDir . '/special-offers-cover.jpg';
        if (is_file($from)) {
            copy($from, $to);
        }
    }

    private function ensureEntry(string $mediaBase): void
    {
        $data = [
            'intro' => '',
            'guest_message' => 'Sign in to see our brand new Special Offers Catalogue!',
            'catalogues' => [
                [
                    'title' => 'Special Offers',
                    'description' => 'Best-value wholesale fills for your floor',
                    'image' => $mediaBase . 'special-offers-cover.jpg',
                    'image_alt' => 'Elme Living Special Offers Catalogue cover',
                    'open_url' => 'https://e.issuu.com/embed.html?d=elme_living_clearance_catalogue_2026&u=elmeliving',
                    'shop_url' => '',
                    'shop_label' => 'Shop this range',
                ],
            ],
        ];

        try {
            $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier(self::ENTRY_IDENTIFIER)
            ->setType('virtual_catalogues')
            ->setTitle('Special Offers Catalogue')
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function upsertPage(): void
    {
        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="'
            . self::ENTRY_IDENTIFIER . '"}}</div>'
            . '</div></div>';

        try {
            $page = $this->getPageByIdentifier->execute(self::PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::PAGE_IDENTIFIER);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $page->setTitle('Special Offers Catalogue')
            ->setContentHeading('Special Offers Catalogue')
            ->setMetaTitle('Special Offers Catalogue | Elme Living Product Showcase')
            ->setMetaDescription(
                'Browse Elme Living\'s special offers catalogue for our best value offer.'
                . ' Explore styles, view products, and plan your perfect space.'
            )
            ->setPageLayout('1column')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [CreateVirtualCataloguesPage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
