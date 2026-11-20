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
 * FE-10 footer CMS blocks: footer_links, footer_social, footer_payment_block.
 */
class CreateFooterCmsBlocks implements DataPatchInterface
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
            'footer_links',
            'Footer links (Shop / Service / Help)',
            $this->linksContent()
        );
        $this->createIfMissing(
            'footer_social',
            'Footer social links',
            $this->socialContent()
        );
        $this->createIfMissing(
            'footer_payment_block',
            'Footer payment icons',
            $this->paymentContent()
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

    private function linksContent(): string
    {
        return <<<'HTML'
<!--
  FE-10 footer link columns (identifier: footer_links)

  Contract — each [data-footer-column] is one column / mobile accordion:
  - first heading (h3) = column title
  - each <a> in the list = a footer link (href + label)
  External hosts get target="_blank" rel="noopener noreferrer" in the theme if missing.
-->
<div data-footer-links>
  <section data-footer-column>
    <h3>Shop</h3>
    <ul>
      <li><a href="{{store direct_url='quick-order'}}">Quick Order</a></li>
      <li><a href="{{store direct_url='furniture.html'}}">Furniture</a></li>
      <li><a href="{{store direct_url='flowers-plants.html'}}">Plants</a></li>
      <li><a href="{{store direct_url='homewares.html'}}">Homewares</a></li>
      <li><a href="{{store direct_url='shop-the-looks'}}">Shop the Looks</a></li>
      <li><a href="{{store direct_url='virtual-catalogues'}}">Virtual Catalogues</a></li>
      <li><a href="{{store direct_url='shoppable-instagram'}}">Shop Instagram</a></li>
      <li><a href="{{store direct_url='style-sourcebook'}}">Style Sourcebook</a></li>
      <li><a href="{{store direct_url='outlet-and-clearance-store'}}">Outlet &amp; Clearance Store</a></li>
    </ul>
  </section>
  <section data-footer-column>
    <h3>Service</h3>
    <ul>
      <li><a href="{{store direct_url='about-us'}}">About Us</a></li>
      <li><a href="{{store direct_url='meet-the-team'}}">Meet the Team</a></li>
      <li><a href="{{store direct_url='showroom-locator'}}">Showroom Locator</a></li>
      <li><a href="{{store direct_url='customer/account/create'}}">Become a Stockist</a></li>
      <li><a href="{{store direct_url='fairs-events'}}">Fairs &amp; Events</a></li>
      <li><a href="{{store direct_url='career-opportunities'}}">Careers</a></li>
    </ul>
  </section>
  <section data-footer-column>
    <h3>Help</h3>
    <ul>
      <li><a href="{{store direct_url='contact-us'}}">Contact</a></li>
      <li><a href="{{store direct_url='shipping-and-returns'}}">Shipping &amp; Returns</a></li>
      <li><a href="{{store direct_url='website-terms-conditions'}}">Terms &amp; Conditions</a></li>
      <li><a href="{{store direct_url='privacy-policy'}}">Privacy Policy</a></li>
      <li><a href="{{store direct_url='faqs'}}">FAQs</a></li>
      <li><a href="https://help.elmeliving.com/hc/en-au/requests/new?ticket_form_id=14703613453199" target="_blank" rel="noopener noreferrer">Submit a Product Claim</a></li>
    </ul>
  </section>
</div>
HTML;
    }

    private function socialContent(): string
    {
        return <<<'HTML'
<!--
  FE-10 footer social (identifier: footer_social)

  Contract — each <a data-network="…"> supplies href (+ optional label text).
  Supported networks: facebook, instagram, pinterest, linkedin, tiktok.
  Theme renders the icon and accessible name "Elme Living on {Network}".
-->
<ul data-footer-social>
  <li><a data-network="facebook" href="https://www.facebook.com/profile.php?id=100086007120442" target="_blank" rel="noopener noreferrer">Facebook</a></li>
  <li><a data-network="instagram" href="https://www.instagram.com/elmeliving/" target="_blank" rel="noopener noreferrer">Instagram</a></li>
  <li><a data-network="pinterest" href="https://www.pinterest.com.au/elmeliving/" target="_blank" rel="noopener noreferrer">Pinterest</a></li>
  <li><a data-network="linkedin" href="https://www.linkedin.com/company/elme-living/" target="_blank" rel="noopener noreferrer">LinkedIn</a></li>
  <li><a data-network="tiktok" href="https://www.tiktok.com/@elmeliving/" target="_blank" rel="noopener noreferrer">TikTok</a></li>
</ul>
HTML;
    }

    private function paymentContent(): string
    {
        return <<<'HTML'
<!--
  FE-10 footer payment icons (identifier: footer_payment_block)

  Contract — optional list of <img src="…" alt="…"> (media URLs).
  Leave empty to use the theme Visa + Mastercard SVGs.
-->
<ul data-footer-payments></ul>
HTML;
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
