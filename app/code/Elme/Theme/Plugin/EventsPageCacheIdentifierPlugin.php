<?php

declare(strict_types=1);

namespace Elme\Theme\Plugin;

use Elme\Theme\ViewModel\Events;
use Magento\Framework\App\PageCache\Identifier;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * FE-66: vary FPC for /fairs-events by Melbourne calendar day so an event whose end date has passed
 * leaves the cached HTML and JSON-LD without cache:flush.
 */
class EventsPageCacheIdentifierPlugin
{
    public function __construct(
        private readonly HttpRequest $request
    ) {
    }

    public function afterGetValue(Identifier $subject, string $result): string
    {
        if (!$this->isFairsEventsPath()) {
            return $result;
        }

        $day = (new \DateTimeImmutable('now', new \DateTimeZone(Events::TIMEZONE)))->format('Y-m-d');

        return sha1($result . '|' . Events::CONTEXT_MELBOURNE_DAY . '=' . $day);
    }

    private function isFairsEventsPath(): bool
    {
        $path = trim($this->request->getPathInfo(), '/');

        return $path === Events::PAGE_IDENTIFIER
            || str_ends_with($path, '/' . Events::PAGE_IDENTIFIER);
    }
}
