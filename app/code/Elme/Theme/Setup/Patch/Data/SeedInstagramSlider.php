<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-12: seed the curated "As styled by you" Instagram slider entry and copy sample
 * images into pub/media. Placement is via layout (home + PDP), not this patch.
 */
class SeedInstagramSlider implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe12';

    private const IDENTIFIER = 'home-instagram-slider';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
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

        $this->ensureEntry(
            self::IDENTIFIER,
            'instagram_slider',
            'Home: As styled by you',
            [
                'heading' => 'As styled by you',
                'handle' => 'elmeliving',
                'invite' => 'Tag @elmeliving to be featured',
                'follow_label' => 'Follow Us',
                'posts' => [
                    [
                        'image' => $mediaBase . 'post-1.jpg',
                        'image_alt' => 'Elme vessels styled on a stockist shelf',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-1/',
                        'credit_handle' => 'houseofsmith',
                        'credit_location' => 'Bendigo VIC',
                        'skus' => 'FE53-VASE-SAGE, FE53-VASE-SAND',
                        'caption' => 'Vessels on the floor at House of Smith',
                    ],
                    [
                        'image' => $mediaBase . 'post-2.jpg',
                        'image_alt' => 'Decor and homewares display in a retail store',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-2/',
                        'credit_handle' => 'wildandfolk',
                        'credit_location' => 'Byron Bay NSW',
                        'skus' => '',
                        'caption' => 'Weekend window at Wild & Folk',
                    ],
                    [
                        'image' => $mediaBase . 'post-3.jpg',
                        'image_alt' => 'Floral stems in a boutique interior',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-3/',
                        'credit_handle' => 'bloomandco',
                        'credit_location' => 'Melbourne VIC',
                        'skus' => 'FE53-LAMP',
                        'caption' => 'Stems with lighting at Bloom & Co',
                    ],
                    [
                        'image' => $mediaBase . 'post-4.jpg',
                        'image_alt' => 'Arrangements on a timber console',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-4/',
                        'credit_handle' => 'thegrovehome',
                        'credit_location' => 'Adelaide SA',
                        'skus' => '',
                        'caption' => 'Console arrangement at The Grove',
                    ],
                    [
                        'image' => $mediaBase . 'post-5.jpg',
                        'image_alt' => 'Bouquets and bundles on a shop table',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-5/',
                        'credit_handle' => 'petalhouse',
                        'credit_location' => 'Sydney NSW',
                        'skus' => 'FE53-VASE',
                        'caption' => 'Bouquets ready for the weekend trade',
                    ],
                    [
                        'image' => $mediaBase . 'post-6.jpg',
                        'image_alt' => 'Wall decor in a showroom corner',
                        'post_url' => 'https://www.instagram.com/p/elme-sample-6/',
                        'credit_handle' => 'northsidehome',
                        'credit_location' => 'Brisbane QLD',
                        'skus' => '',
                        'caption' => 'Wall edit at Northside Home',
                    ],
                ],
            ]
        );

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/fe12';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (['post-1.jpg', 'post-2.jpg', 'post-3.jpg', 'post-4.jpg', 'post-5.jpg', 'post-6.jpg'] as $file) {
            $from = $sourceDir . '/' . $file;
            $to = $targetDir . '/' . $file;

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

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
