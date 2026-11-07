<?php

declare(strict_types=1);

namespace Elme\RecentlyViewed\Controller\Products;

use Elme\RecentlyViewed\Block\Cards as CardsBlock;
use Elme\RecentlyViewed\ViewModel\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * GET /recently_viewed/products/cards?ids=1,2,3 — FE-19 card HTML for the strip (private, no FPC).
 */
class Cards implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly PageFactory $pageFactory,
        private readonly Config $config
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        if (!$this->config->isEnabled()) {
            return $result->setData(['html' => '', 'count' => 0]);
        }

        $ids = array_slice(array_values(array_unique(array_filter(array_map(
            'intval',
            explode(',', (string) $this->request->getParam('ids', ''))
        )))), 0, $this->config->getCapacity());

        if ($ids === []) {
            return $result->setData(['html' => '', 'count' => 0]);
        }

        // PageFactory applies the storefront theme so catalog_list_item / product_list_item resolve.
        $page = $this->pageFactory->create();
        $page->addHandle('catalog_list_item');
        $page->addHandle('elme_recently_viewed_cards');
        $layout = $page->getLayout();

        /** @var CardsBlock|false $block */
        $block = $layout->getBlock('elme.recently.viewed.cards');
        if (!$block instanceof CardsBlock) {
            return $result->setData(['html' => '', 'count' => 0]);
        }

        $block->setOrderedIds($ids);
        $html = $block->toHtml();

        return $result->setData([
            'html' => $html,
            'count' => substr_count($html, 'elme-rv-products__slide'),
        ]);
    }
}
