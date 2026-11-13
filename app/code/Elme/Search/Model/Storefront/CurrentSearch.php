<?php

declare(strict_types=1);

namespace Elme\Search\Model\Storefront;

use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\Data\SearchResult;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The ProductSearchInterface answer behind the page's product listing (one per request), read by the facets
 * ViewModel. Shared instance.
 */
class CurrentSearch implements ResetAfterRequestInterface
{
    private ?SearchRequest $request = null;
    private ?SearchResult $result = null;
    private bool $failed = false;

    public function set(SearchRequest $request, SearchResult $result): void
    {
        $this->request = $request;
        $this->result = $result;
    }

    public function markFailed(): void
    {
        $this->failed = true;
    }

    /**
     * True once a search failed in this request: later searches go straight to Magento and log nothing more.
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    public function getRequest(): ?SearchRequest
    {
        return $this->request;
    }

    public function getResult(): ?SearchResult
    {
        return $this->result;
    }

    public function _resetState(): void
    {
        $this->request = null;
        $this->result = null;
        $this->failed = false;
    }
}
