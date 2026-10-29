<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-64: /style-sourcebook on the banner + breadcrumbs layout — Content Studio `hero` (page H1) plus
 * Page Builder HTML body (new Elme-only copy) and FE-13 Need assistance via the page layout.
 * Existing entries and an existing page are left alone.
 */
class CreateStyleSourcebookPage implements DataPatchInterface
{
    private const IDENTIFIER = 'style-sourcebook';

    private const MEDIA_DIR = 'bart_content_studio/fe64';

    private const HERO_ENTRY = 'style-sourcebook-hero';

    private const IMAGE_MAP = [
        'hero-desktop.jpg' => 'hero-desktop.jpg',
        'hero-mobile.jpg' => 'hero-mobile.jpg',
        'moodboard-ui.jpg' => 'moodboard-ui.jpg',
        'room-styled.jpg' => 'room-styled.jpg',
        'room-board.png' => 'room-board.png',
    ];

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
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

        $this->ensureEntry(self::HERO_ENTRY, 'hero', 'Style Sourcebook: hero', [
            'is_page_title' => true,
            'autoplay' => false,
            'interval' => 7,
            'slides' => [
                [
                    'image_desktop' => $mediaBase . 'hero-desktop.jpg',
                    'image_mobile' => $mediaBase . 'hero-mobile.jpg',
                    'image_alt' => 'White orchid stems in ceramic vessels on a light table',
                    'heading' => "Elme Living x Style Sourcebook",
                    'tone' => 'light',
                    'text' => 'Interactive mood boards with real Elme Living furniture, flora and homewares'
                        . ' for retailers and stylists.',
                ],
            ],
        ]);

        $this->ensurePage();

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/fe64';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (self::IMAGE_MAP as $fromName => $toName) {
            $from = $sourceDir . '/' . $fromName;
            $to = $targetDir . '/' . $toName;
            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureEntry(string $identifier, string $type, string $title, array $data): void
    {
        try {
            $this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier($identifier)
            ->setType($type)
            ->setTitle($title)
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function ensurePage(): void
    {
        try {
            $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $bodyPath = BP . '/app/code/Elme/Theme/Setup/Patch/Data/files/style-sourcebook.html';
        $body = is_file($bodyPath) ? (string)file_get_contents($bodyPath) : '';

        $heroWidget = '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="' . self::HERO_ENTRY . '"}}</div>';

        $page = $this->pageFactory->create();
        $page->setIdentifier(self::IDENTIFIER)
            ->setTitle('Style Sourcebook')
            ->setPageLayout('banner-breadcrumbs')
            ->setMetaTitle('Elme Living | Style Sourcebook | Create Your Dream Space')
            ->setMetaDescription(
                'Build interactive mood boards with Elme Living furniture, flora and homewares on'
                . ' Style Sourcebook. Drag and drop real wholesale products to plan retail floors'
                . ' and styled spaces.'
            )
            ->setContent(
                '<div data-content-type="row" data-appearance="contained" data-element="main">'
                . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
                . ' data-element="inner" style="display:flex;flex-direction:column;">'
                . $heroWidget
                . $body
                . '</div></div>'
            )
            ->setIsActive(true);
        $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [CreateSharedCmsBlocks::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
