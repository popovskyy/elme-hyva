<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Observer;

use Bart\ContentStudio\Model\Preview\DraftContext;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\PageCache\Model\Cache\Type as PageCacheType;

/**
 * Keeps a preview render out of every cache, and out of the index.
 *
 * Without this the first person to open a draft preview of a product page
 * writes that draft into the full page cache under the previewed URL, and
 * every subsequent visitor - Varnish included, since Magento only emits
 * cacheable headers while the cache type is on - is served unpublished
 * content. Switching the cache type off at runtime is what makes Magento skip
 * both storing and the cacheable headers; `setNoCacheHeaders()` says the same
 * thing to anything in front of it.
 *
 * The switch is deliberately behind {@see DraftContext::isPreview()}, which
 * requires a live token, not merely the presence of the query parameter -
 * otherwise appending `?cs_preview=x` to any URL would be a free way to force
 * uncached renders of the whole catalogue.
 *
 * It also pins `Referrer-Policy: no-referrer`. The token lives in the query
 * string, and a product page is full of third parties - analytics, tag
 * manager, Nosto, image CDNs. Every one of them would otherwise receive the
 * full preview URL in a `Referer` header and, for the analytics ones, record
 * it against a page view. Fifteen minutes bounds how long such a copy is worth
 * anything, but not handing it out at all is cheaper than relying on that.
 */
class PreviewCacheBypass implements ObserverInterface
{
    /**
     * @param DraftContext $draftContext
     * @param StateInterface $cacheState
     * @param HttpResponse $response
     * @param PageConfig $pageConfig
     */
    public function __construct(
        private readonly DraftContext $draftContext,
        private readonly StateInterface $cacheState,
        private readonly HttpResponse $response,
        private readonly PageConfig $pageConfig
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->draftContext->isPreview()) {
            return;
        }

        // Runtime only - StateInterface::persist() is never called, so nothing
        // here survives the request or touches the admin cache management page.
        $this->cacheState->setEnabled(PageCacheType::TYPE_IDENTIFIER, false);
        $this->response->setNoCacheHeaders();
        $this->response->setHeader('Referrer-Policy', 'no-referrer', true);
        $this->pageConfig->setRobots('NOINDEX,NOFOLLOW');
    }
}
