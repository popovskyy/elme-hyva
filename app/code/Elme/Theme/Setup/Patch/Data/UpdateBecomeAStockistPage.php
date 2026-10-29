<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-67: refresh /become-a-stockist meta description, mount usp_strip benefits (no strip button),
 * and leave How it works / facts / CTAs to the theme layout block.
 */
class UpdateBecomeAStockistPage implements DataPatchInterface
{
    private const PAGE_ID = 'become-a-stockist';

    private const USP_ID = 'become-a-stockist-usp';

    private const MEDIA_DIR = 'bart_content_studio/fe16';

    private const META_DESCRIPTION = 'Apply for an Elme Living trade account with your ABN.'
        . ' Wholesale artificial flora and homewares for Australian retailers — prices after approval.';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        SeedHomepageContentSections::syncImagesToMedia();
        $this->ensureUspEntry();
        $this->updatePage();

        return $this;
    }

    private function ensureUspEntry(): void
    {
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $data = $this->uspDataFromHome($mediaBase);

        try {
            $entry = $this->entryRepository->getByIdentifier(self::USP_ID, Store::DEFAULT_STORE_ID);
            $entry->setEntryData($data);
            $this->entryRepository->save($entry);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier(self::USP_ID)
            ->setType('usp_strip')
            ->setTitle('Become a Stockist: benefits')
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    /**
     * Same three FE-16 benefits as home; button fields empty so the page keeps one primary CTA.
     *
     * @return array<string, mixed>
     */
    private function uspDataFromHome(string $mediaBase): array
    {
        $fallback = [
            'heading' => 'Why stock with Elme Living',
            'intro' => 'Wholesale artificial flora and homewares built for Australian retailers —'
                . ' not a consumer gift shop.',
            'image' => $mediaBase . 'become-stockist.jpg',
            'image_alt' => 'Styled Elme Living homewares and artificial flora display',
            'image_2' => $mediaBase . 'collage-vases.jpg',
            'image_3' => $mediaBase . 'collage-arrangements.jpg',
            'items' => [
                [
                    'title' => 'ABN trade account',
                    'text' => 'Register with a valid Australian Business Number. We review applications'
                        . ' and open wholesale access for approved retailers.',
                ],
                [
                    'title' => 'Prices ex GST',
                    'text' => 'After login you see your own trade prices ex GST, not a public retail list.',
                ],
                [
                    'title' => 'Australia-wide delivery',
                    'text' => 'Order online for delivery across Australia. Some lines have a'
                        . ' minimum order quantity.',
                ],
            ],
            'button_label' => '',
            'button_link' => '',
        ];

        try {
            $home = $this->entryRepository->getByIdentifier('home-stockist-usp', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $fallback;
        }

        $homeData = $home->getEntryData();
        if (!is_array($homeData) || $homeData === []) {
            return $fallback;
        }

        return [
            'heading' => 'Why stock with Elme Living',
            'intro' => (string) ($homeData['intro'] ?? $fallback['intro']),
            'image' => (string) ($homeData['image'] ?? $fallback['image']),
            'image_alt' => (string) ($homeData['image_alt'] ?? $fallback['image_alt']),
            'image_2' => (string) ($homeData['image_2'] ?? $fallback['image_2']),
            'image_3' => (string) ($homeData['image_3'] ?? $fallback['image_3']),
            'items' => is_array($homeData['items'] ?? null) ? $homeData['items'] : $fallback['items'],
            'button_label' => '',
            'button_link' => '',
        ];
    }

    private function updatePage(): void
    {
        $path = __DIR__ . '/files/help/become-a-stockist.html';
        $content = is_readable($path) ? trim((string) file_get_contents($path)) : '';
        if ($content === '') {
            return;
        }

        try {
            $page = $this->getPageByIdentifier->execute(self::PAGE_ID, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $page->setMetaDescription(self::META_DESCRIPTION)
            ->setMetaTitle('Become a Stockist | Elme Living - Wholesale Decor Partner')
            ->setContent($content);
        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [
            CreateHelpCmsPages::class,
            SeedHomepageContentSections::class,
            CinemaHomeHeroAndCollage::class,
            RefreshHomepageLookAndCopy::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
