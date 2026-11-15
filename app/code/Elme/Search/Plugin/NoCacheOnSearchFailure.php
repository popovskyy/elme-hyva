<?php

declare(strict_types=1);

namespace Elme\Search\Plugin;

use Elme\Search\Model\Storefront\CurrentSearch;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Layout;

/**
 * When ProductSearchInterface failed, the page was built from Magento's search instead (Plugin\UseProductSearch):
 * no facets, and the filters in the URL are not applied. That page must not go into Varnish or the built-in page
 * cache, or every visitor gets the degraded listing on that URL until the TTL runs out.
 *
 * Runs once the page is rendered, so it also covers a search made while the layout was generated, after which
 * Magento_PageCache sets the public cache headers.
 */
class NoCacheOnSearchFailure
{
    public function __construct(
        private readonly CurrentSearch $currentSearch,
        private readonly ResponseInterface $response
    ) {
    }

    /**
     * @param Layout $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetOutput(Layout $subject, $result)
    {
        if ($this->currentSearch->hasFailed() && $this->response instanceof HttpResponse) {
            $this->response->setNoCacheHeaders();
        }
        return $result;
    }
}
