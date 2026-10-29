<?php

declare(strict_types=1);

namespace Elme\Theme\Plugin;

use Bart\ContentStudio\Block\Entry;
use Elme\Theme\ViewModel\Events;

/**
 * FE-66: key the events Content Studio block HTML cache by Melbourne calendar day.
 */
class EventsEntryCacheKeyPlugin
{
    /**
     * @param array<int|string, mixed> $result
     * @return array<int|string, mixed>
     */
    public function afterGetCacheKeyInfo(Entry $subject, array $result): array
    {
        $entry = $subject->getEntry();
        if ($entry === null || $entry->getType() !== 'events') {
            return $result;
        }

        $result[] = Events::CONTEXT_MELBOURNE_DAY;
        $result[] = (new \DateTimeImmutable('now', new \DateTimeZone(Events::TIMEZONE)))->format('Y-m-d');

        return $result;
    }
}
