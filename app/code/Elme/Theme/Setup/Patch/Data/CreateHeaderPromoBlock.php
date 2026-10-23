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
 * Creates CMS block header_promo (FE-05 top bar) with the documented slot contract.
 */
class CreateHeaderPromoBlock implements DataPatchInterface
{
    private const IDENTIFIER = 'header_promo';

    public function __construct(
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier
    ) {
    }

    public function apply(): self
    {
        try {
            $this->getBlockByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return $this;
        } catch (NoSuchEntityException) {
            // create below
        }

        $content = <<<'HTML'
<!--
  FE-05 top bar (identifier: header_promo)

  Slots — edit text and hrefs; the theme shows exactly one link by login state (FPC-safe):
  - data-header-promo="usp" — always visible; plain text / <p> / <span> only (never h1–h6)
  - data-header-promo="stockist" — guests only → Become a Stockist
  - data-header-promo="inspiration" — logged-in only → Inspiration

  Do not paste both links expecting both to show. Visibility is theme/login-driven.
-->
<p data-header-promo="usp">Australia's leading wholesale supplier of artificial flora and home decor</p>
<a data-header-promo="stockist" href="{{store direct_url="become-a-stockist"}}">Become a Stockist</a>
<a data-header-promo="inspiration" href="{{store direct_url="shop-the-looks"}}">Inspiration</a>
HTML;

        $block = $this->blockFactory->create();
        $block->setIdentifier(self::IDENTIFIER)
            ->setTitle('Header promo (top bar USP + links)')
            ->setContent($content)
            ->setIsActive(true);
        $block->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->blockRepository->save($block);

        return $this;
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
