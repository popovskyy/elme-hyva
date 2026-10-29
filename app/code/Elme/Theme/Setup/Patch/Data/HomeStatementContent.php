<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Content for the home "Statement" run and the quote look of Our Difference
 * (docs/design/home-middle-options.html, 1 and B):
 * - welcome heading with "home decor" marked for the italic accent;
 * - the Outlet promo as a dark band with "Up to 80% off" as its big line and a ticker; the offer
 *   moves out of the text so it is not said twice;
 * - Our Difference: its first sentence becomes the heading (the quote), the rest stays as the text.
 */
class HomeStatementContent implements DataPatchInterface
{
    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    public function apply(): self
    {
        $this->updateEntry('home-welcome', function (array $data): array {
            $data['heading'] = 'Wholesale artificial flowers, plants and *home decor* for Australian retailers';

            return $data;
        });

        $this->updateEntry('home-outlet-promo', function (array $data): array {
            $data['tone'] = 'dark';
            $data['highlight'] = 'Up to 80% off';
            $data['ticker'] = "End-of-lines\nSamples\nOne-off pieces\nUp to 80% off\nMelbourne outlet";
            $data['text'] = '<p>End-of-lines, samples and one-off pieces for your floor at our Melbourne outlet.</p>'
                . '<p>Stockists use it for samples and clearance fills; the public is welcome too.</p>';

            return $data;
        });

        try {
            $block = $this->getBlockByIdentifier->execute('our_difference', Store::DEFAULT_STORE_ID);
            $block->setContent($this->ourDifference());
            $this->blockRepository->save($block);
        } catch (NoSuchEntityException) {
            // Block not created on this instance; CreateSharedCmsBlocks owns it.
        }

        return $this;
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

    private function ourDifference(): string
    {
        return <<<'HTML'
<section class="elme-our-difference" aria-labelledby="elme-our-difference-heading">
  <h2 id="elme-our-difference-heading" class="elme-our-difference__title">Australia's newest wholesaler of artificial flowers, plants and <em>homewares</em>.</h2>
  <div class="elme-our-difference__body">
    <p>Our extensive 'Flora' range is designed to suit all your needs and includes Orchids, Fiddle Leaf Fig Trees, Areca Palms, Bird of Paradise, and Native Botanicals alongside complementary Vases and Pots of every shape and size. Our 'Decorator' range includes Wall Art, Mirrors, Cushions, Clocks, Rugs, Decorative Items, Prints, and Elme Living exclusives that will add a touch of style to any home or office. We pride ourselves on our commitment to sourcing the very best products at affordable prices, to help your customers create their dream spaces.</p>
  </div>
</section>
HTML;
    }

    public static function getDependencies(): array
    {
        return [CinemaHomeHeroAndCollage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
