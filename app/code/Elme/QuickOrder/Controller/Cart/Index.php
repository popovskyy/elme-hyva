<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * /quick-order/cart, the cart page (FE-33). Login only through Elme_LoginOnly; never full page cached
 * (cacheable="false" in quick_order_cart_index.xml). Head: title Cart, noindex, self canonical.
 */
class Index implements HttpGetActionInterface
{
    private const META_DESCRIPTION =
        'Your Elme Living wholesale cart. Review items, quantities and totals before checkout.';

    /**
     * `$view` is unused on purpose, as in Elme\StoreLocator\Controller\Index\Index: constructing App\View early
     * pins the shared Page\Config builder, or Hyva's head.additional scripts (hyva.js, variables) are dropped.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly UrlInterface $url,
        ViewInterface $view
    ) {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $config = $page->getConfig();
        $config->getTitle()->set(__('Cart'));
        $config->setDescription(__(self::META_DESCRIPTION));
        $config->setRobots('NOINDEX,NOFOLLOW');

        // Absolute self URL without the /index/ Magento appends for the default action.
        $canonical = rtrim($this->url->getBaseUrl(), '/') . '/quick-order/cart';
        $config->addRemotePageAsset(
            $canonical,
            'canonical',
            ['attributes' => ['rel' => 'canonical']]
        );
        $config->setMetadata('og:type', 'website');
        $config->setMetadata('og:title', (string) __('Cart'));
        $config->setMetadata('og:description', (string) __(self::META_DESCRIPTION));
        $config->setMetadata('og:url', $canonical);
        $config->setMetadata('og:locale', 'en_AU');

        return $page;
    }
}
