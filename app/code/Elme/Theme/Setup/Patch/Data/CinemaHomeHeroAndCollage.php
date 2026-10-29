<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Home "Cinema" hero and Become a Stockist collage (docs/design/hero-wow-options.html, 1 and 3).
 * - Hero slides get tall photos (2:1 wide, 4:5 phone) and headings broken into the lines that rise
 *   one by one. The flowers slide is dark, the showroom slide (orchids, mirrored so the copy sits on
 *   the plain wall) is light. The vases slide is dropped: its photo is too small for a full-screen
 *   banner; an editor can add it back with a 2400x1200 photo.
 * - The Become a Stockist strip gets two more photos, which turn its image into the collage.
 * Originals live in the theme (web/images/fe14, fe16) and are copied to pub/media.
 */
class CinemaHomeHeroAndCollage implements DataPatchInterface
{
    private const IMAGES = [
        'fe14' => ['stems-wide.jpg', 'stems-tall.jpg', 'orchids-wide.jpg', 'orchids-tall.jpg'],
        'fe16' => ['collage-vases.jpg', 'collage-arrangements.jpg'],
    ];

    private const SLIDES = [
        '/flowers-plants.html' => [
            'photo' => 'stems',
            'tone' => 'dark',
            'heading' => "Artificial Flowers\n& Plants for\nYour Store",
            'image_alt' => 'Artificial peonies, dahlias and garden stems laid out on a plum background',
        ],
        '/showroom-locator' => [
            'photo' => 'orchids',
            'tone' => 'light',
            'heading' => "See the Range in\nOur Melbourne\nShowroom",
            'image_alt' => 'White artificial cymbidium and phalaenopsis orchids in pots against a white panelled wall',
        ],
    ];

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        $this->syncImages();
        $media = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
            . '/bart_content_studio/';

        $this->updateEntry('home-hero', function (array $data) use ($media): array {
            $slides = [];

            foreach ((array)($data['slides'] ?? []) as $slide) {
                $link = is_array($slide) ? (string)($slide['button_link'] ?? '') : '';

                if (!isset(self::SLIDES[$link])) {
                    continue;
                }

                $look = self::SLIDES[$link];
                $slide['image_desktop'] = $media . 'fe14/' . $look['photo'] . '-wide.jpg';
                $slide['image_mobile'] = $media . 'fe14/' . $look['photo'] . '-tall.jpg';
                $slide['image_alt'] = $look['image_alt'];
                $slide['heading'] = $look['heading'];
                $slide['tone'] = $look['tone'];
                $slides[] = $slide;
            }

            if ($slides) {
                $data['slides'] = $slides;
            }

            return $data;
        });

        $this->updateEntry('home-stockist-usp', function (array $data) use ($media): array {
            $data['image_2'] = $media . 'fe16/collage-vases.jpg';
            $data['image_3'] = $media . 'fe16/collage-arrangements.jpg';

            return $data;
        });

        return $this;
    }

    private function syncImages(): void
    {
        foreach (self::IMAGES as $folder => $files) {
            $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/' . $folder;
            $targetDir = BP . '/pub/media/bart_content_studio/' . $folder;

            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                continue;
            }

            foreach ($files as $file) {
                if (is_file($sourceDir . '/' . $file)) {
                    copy($sourceDir . '/' . $file, $targetDir . '/' . $file);
                }
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

    public static function getDependencies(): array
    {
        return [RefreshHomepageLookAndCopy::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
