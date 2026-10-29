<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Homepage rework picked in docs/design/homepage-options.html (A2, B1, C3, D1):
 * - hero photos shown whole: a sharp floral photo on slide 1, the orchids on the showroom slide
 *   (the old sideboard photo was too small for a full-width band); originals live in the theme
 *   (web/images/fe14) and are copied to pub/media;
 * - short lines under the catalogue names;
 * - customer copy: "trade prices" instead of the ERP name, no developer words, the
 *   "complementary" typo, and no internal HTML comments in the shared CMS blocks.
 * Only touches entries and blocks that exist; other fields an editor changed are kept.
 */
class RefreshHomepageLookAndCopy implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe14';

    private const HERO_IMAGES = [
        'stems-desktop.jpg',
        'stems-mobile.jpg',
        'orchids-desktop.jpg',
        'orchids-mobile.jpg',
        'vases-desktop.jpg',
        'vases-mobile.jpg',
    ];

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        $this->syncHeroImages();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->updateEntry('home-hero', function (array $data) use ($mediaBase): array {
            $slides = array_values((array)($data['slides'] ?? []));
            $photos = [
                0 => [
                    'stems',
                    'Artificial peonies, dahlias and garden stems laid out on a plum background',
                ],
                2 => [
                    'orchids',
                    'White artificial cymbidium and phalaenopsis orchids in pots against a white panelled wall',
                ],
            ];

            foreach ($slides as $index => $slide) {
                if (!is_array($slide)) {
                    continue;
                }
                unset($slide['panel_position']);

                if (isset($photos[$index])) {
                    [$name, $alt] = $photos[$index];
                    $slide['image_desktop'] = $mediaBase . $name . '-desktop.jpg';
                    $slide['image_mobile'] = $mediaBase . $name . '-mobile.jpg';
                    $slide['image_alt'] = $alt;
                }
                $slides[$index] = $slide;
            }
            $data['slides'] = $slides;

            return $data;
        });

        $this->updateEntry('home-welcome', function (array $data): array {
            $data['text'] = 'Elme Living is a wholesale trade site. Log in to see your trade prices (ex GST),'
                . ' or apply for a trade account with your ABN.';

            return $data;
        });

        $this->updateEntry('home-catalogue-covers', function (array $data): array {
            $data['intro'] = 'Plan your next buy with our Lookbook and Virtual Catalogues: style edits and'
                . ' full ranges built for Australian retailers.';
            $lines = [
                'Lookbook' => 'Styled rooms and seasonal edits',
                'Virtual Catalogues' => 'Full ranges to browse page by page',
            ];

            foreach ((array)($data['cards'] ?? []) as $index => $card) {
                $title = is_array($card) ? (string)($card['title'] ?? '') : '';

                if (isset($lines[$title]) && trim((string)($card['text'] ?? '')) === '') {
                    $data['cards'][$index]['text'] = $lines[$title];
                }
            }

            return $data;
        });

        $this->updateEntry('home-stockist-usp', function (array $data): array {
            $data['intro'] = 'Apply with your ABN for a wholesale trade account. Once approved, you shop'
                . ' your own trade prices online, built for Australian retailers.';
            $texts = [
                'Prices ex GST' => 'After login you see your own trade prices ex GST, not a public retail list.',
                'Australia-wide delivery' => 'Order online for delivery across Australia. Some lines have a'
                    . ' minimum order quantity.',
            ];

            foreach ((array)($data['items'] ?? []) as $index => $item) {
                $title = is_array($item) ? (string)($item['title'] ?? '') : '';

                if (isset($texts[$title])) {
                    $data['items'][$index]['text'] = $texts[$title];
                }
            }

            return $data;
        });

        $this->updateBlock('our_difference', $this->ourDifferenceContent());
        $this->updateBlock(
            'need_assistance',
            '{{block class="Magento\Framework\View\Element\Template"'
            . ' template="Magento_Theme::html/need-assistance.phtml"}}'
        );

        return $this;
    }

    private function syncHeroImages(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/fe14';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (self::HERO_IMAGES as $file) {
            if (is_file($sourceDir . '/' . $file)) {
                copy($sourceDir . '/' . $file, $targetDir . '/' . $file);
            }
        }
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $change
     */
    private function updateEntry(string $identifier, callable $change): void
    {
        try {
            $entry = $this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $entry->setEntryData($change((array)$entry->getEntryData()));
        $this->entryRepository->save($entry);
    }

    private function updateBlock(string $identifier, string $content): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $block->setContent($content);
        $this->blockRepository->save($block);
    }

    private function ourDifferenceContent(): string
    {
        return <<<'HTML'
<section class="elme-our-difference" aria-labelledby="elme-our-difference-heading">
  <h2 id="elme-our-difference-heading" class="elme-our-difference__title">Our Difference</h2>
  <div class="elme-our-difference__body">
    <p>Elme Living is Australia's newest wholesaler of artificial flowers, plants, and homewares. Our extensive 'Flora' range is designed to suit all your needs and includes Orchids, Fiddle Leaf Fig Trees, Areca Palms, Bird of Paradise, and Native Botanicals alongside complementary Vases and Pots of every shape and size. Our 'Decorator' range includes Wall Art, Mirrors, Cushions, Clocks, Rugs, Decorative Items, Prints, and Elme Living exclusives that will add a touch of style to any home or office. We pride ourselves on our commitment to sourcing the very best products at affordable prices, to help your customers create their dream spaces.</p>
  </div>
</section>
HTML;
    }

    public static function getDependencies(): array
    {
        return [
            SeedHomepageContentSections::class,
            CreateSharedCmsBlocks::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
