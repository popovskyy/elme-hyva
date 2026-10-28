<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-13 reusable CMS blocks: our_difference, need_assistance.
 */
class CreateSharedCmsBlocks implements DataPatchInterface
{
    public function __construct(
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier
    ) {
    }

    public function apply(): self
    {
        $this->createIfMissing(
            'our_difference',
            'Our Difference',
            $this->ourDifferenceContent()
        );
        $this->createIfMissing(
            'need_assistance',
            'Need assistance?',
            $this->needAssistanceContent()
        );

        return $this;
    }

    private function createIfMissing(string $identifier, string $title, string $content): void
    {
        try {
            $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $block = $this->blockFactory->create();
        $block->setIdentifier($identifier)
            ->setTitle($title)
            ->setContent($content)
            ->setIsActive(true);
        $block->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->blockRepository->save($block);
    }

    private function ourDifferenceContent(): string
    {
        return <<<'HTML'
<!--
  FE-13 Our Difference (identifier: our_difference)

  Contract — theme CSS (.elme-our-difference) styles this markup:
  - h2 = section title (never h1)
  - .elme-our-difference__body > p = brand blurb (left-aligned plain text)
  Place via CMS Block widget / Page Builder / layout (block_id=our_difference).
-->
<section class="elme-our-difference" aria-labelledby="elme-our-difference-heading">
  <h2 id="elme-our-difference-heading" class="elme-our-difference__title">Our Difference</h2>
  <div class="elme-our-difference__body">
    <p>Elme Living is Australia's newest wholesaler of artificial flowers, plants, and homewares. Our extensive 'Flora' range is designed to suit all your needs and includes Orchids, Fiddle Leaf Fig Trees, Areca Palms, Bird of Paradise, and Native Botanicals alongside complimentary Vases and Pots of every shape and size. Our 'Decorator' range includes Wall Art, Mirrors, Cushions, Clocks, Rugs, Decorative Items, Prints, and Elme Living exclusives that will add a touch of style to any home or office. We pride ourselves on our commitment to sourcing the very best products at affordable prices, to help your customers create their dream spaces.</p>
  </div>
</section>
HTML;
    }

    private function needAssistanceContent(): string
    {
        // NAP (phone / email / showroom) comes from Magento store config via the theme template
        // so FE-55 Organization / LocalBusiness cannot drift from this block.
        return <<<'HTML'
<!--
  FE-13 Need assistance? (identifier: need_assistance)

  Renders Magento_Theme::html/need-assistance.phtml (StoreContact ViewModel).
  Edit phone / email / showroom in Stores > Configuration > General > Store Information
  and Store Email Addresses > General Contact — not in this CMS HTML.
-->
{{block class="Magento\Framework\View\Element\Template" template="Magento_Theme::html/need-assistance.phtml"}}
HTML;
    }

    public static function getDependencies(): array
    {
        return [SetElmeStoreContactConfig::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
