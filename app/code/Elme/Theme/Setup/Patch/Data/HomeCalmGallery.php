<?php
/**
 * Home "Calm gallery" (docs/design/homepage-review-options.html, picked 31 Oct 2026):
 * - the hero holds the page H1 and the Welcome block becomes a short intro with the three trade facts
 *   (copied from the Become a Stockist block, which then hides them);
 * - all category tiles in a grid;
 * - Outlet, Lookbook and Virtual Catalogues as three equal photo cards, "From the showroom"; the dark
 *   Outlet band with its ticker leaves the home page (the entry stays for other pages);
 * - Our Difference and the Need assistance? band leave the home page in layout
 *   (Magento_Cms/layout/cms_index_index.xml); the contacts are in the footer.
 * Only touches entries that exist; fields an editor changed and that this look does not need are kept.
 */

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class HomeCalmGallery implements DataPatchInterface
{
    private const FACT_MAX = 140;

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function apply(): self
    {
        $this->updateEntry('home-hero', static function (array $data): array {
            $data['is_page_title'] = '1';

            return $data;
        });

        $facts = [];
        $stockist = $this->entryData('home-stockist-usp');
        foreach ((array)($stockist['items'] ?? []) as $item) {
            $title = is_array($item) ? trim((string)($item['title'] ?? '')) : '';
            if ($title === '') {
                continue;
            }
            $text = trim((string)($item['text'] ?? ''));
            if (mb_strlen($text) > self::FACT_MAX) {
                $text = rtrim(mb_substr($text, 0, self::FACT_MAX - 1)) . '…';
            }
            $facts[] = ['title' => $title, 'text' => $text];
        }

        $this->updateEntry('home-welcome', static function (array $data) use ($facts): array {
            $data['look'] = 'intro';
            if ($facts !== []) {
                $data['facts'] = array_slice($facts, 0, 4);
            }

            return $data;
        });

        $this->updateEntry('home-stockist-usp', static function (array $data): array {
            $data['show_items'] = '0';

            return $data;
        });

        $this->updateEntry('home-category-tiles', static function (array $data): array {
            $data['layout'] = 'grid';

            return $data;
        });

        $outlet = $this->entryData('home-outlet-promo');
        $this->updateEntry('home-catalogue-covers', static function (array $data) use ($outlet): array {
            $data['look'] = 'cards';
            $data['heading'] = 'From the showroom';
            $data['intro'] = '';

            $cards = array_values(array_filter(
                (array)($data['cards'] ?? []),
                static fn ($card): bool => is_array($card) && ($card['title'] ?? '') !== 'Elme Outlet'
            ));

            $image = trim((string)($outlet['image'] ?? ''));
            if ($image !== '') {
                array_unshift($cards, [
                    'image' => $image,
                    'image_alt' => trim((string)($outlet['image_alt'] ?? '')) ?: 'Elme Outlet display of vases and homewares',
                    'title' => 'Elme Outlet',
                    'text' => 'End-of-lines and samples, up to 80% off',
                    'link' => trim((string)($outlet['button_link'] ?? '')) ?: '/outlet-and-clearance-store',
                ]);
            }
            $data['cards'] = array_slice($cards, 0, 4);

            return $data;
        });

        $this->removeOutletFromHomePage();

        return $this;
    }

    private function removeOutletFromHomePage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $content = (string)$page->getContent();
        $without = preg_replace(
            '#<div data-content-type="content_studio"[^>]*>\{\{widget [^}]*identifier="home-outlet-promo"[^}]*\}\}</div>#',
            '',
            $content
        );

        if (is_string($without) && $without !== $content) {
            $page->setContent($without);
            $this->pageRepository->save($page);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function entryData(string $identifier): array
    {
        try {
            return (array)$this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID)->getEntryData();
        } catch (NoSuchEntityException) {
            return [];
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

    public static function getDependencies(): array
    {
        return [HomeStatementContent::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
