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
 * FE-50: seed /virtual-catalogues CMS page + Content Studio catalogue list entry.
 */
class CreateVirtualCataloguesPage implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe50';

    private const ENTRY_IDENTIFIER = 'virtual-catalogues-list';

    private const PAGE_IDENTIFIER = 'virtual-catalogues';

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

        $map = [
            'lookbook-cover.jpg' => 'ss27-cover.jpg',
            'virtual-catalogues-cover.jpg' => 'aw26-cover.jpg',
        ];

        foreach ($map as $fromName => $toName) {
            $from = $sourceDir . '/' . $fromName;
            $to = $targetDir . '/' . $toName;
            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    private function ensureEntry(string $mediaBase): void
    {
        $data = [
            'intro' => 'Elme Living wholesale seasonal catalogues for Australian retailers.'
                . ' Covers show the ranges; sign in to open flipbooks and shop matching categories.',
            'catalogues' => [
                [
                    'title' => 'SS27',
                    'description' => 'New season lookbook and range edit',
                    'image' => $mediaBase . 'ss27-cover.jpg',
                    'image_alt' => 'Elme Living SS27 catalogue cover',
                    'open_url' => 'https://e.issuu.com/embed.html?d=el_new_season_lookbook_ss27&u=elmeliving',
                    'shop_url' => '',
                    'shop_label' => 'Shop this range',
                ],
                [
                    'title' => 'AW26',
                    'description' => 'Seasonal wholesale catalogue',
                    'image' => $mediaBase . 'aw26-cover.jpg',
                    'image_alt' => 'Elme Living AW26 catalogue cover',
                    'open_url' => 'https://e.issuu.com/embed.html?d=elme_catalogue_new_season_2026&u=elmeliving',
                    'shop_url' => '',
                    'shop_label' => 'Shop this range',
                ],
                [
                    'title' => 'Clearance 2026',
                    'description' => 'Clearance fills for your floor',
                    'image' => $mediaBase . 'aw26-cover.jpg',
                    'image_alt' => 'Elme Living Clearance 2026 catalogue cover',
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
            ->setTitle('Virtual Catalogues')
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

        $page->setTitle('Virtual Catalogues')
            ->setContentHeading('Virtual Catalogues')
            ->setMetaTitle('Virtual Catalogues | Elme Living Wholesale Ranges')
            ->setMetaDescription(
                'Browse Elme Living seasonal wholesale catalogues for Australian retailers.'
                . ' See range covers, then sign in to open flipbooks and shop matching categories.'
            )
            ->setPageLayout('1column')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [SeedHomepageContentSections::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
