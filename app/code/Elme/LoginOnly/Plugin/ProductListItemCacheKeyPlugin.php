<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Elme\LoginOnly\Model\LoginState;
use Hyva\Theme\ViewModel\ProductListItem;

/**
 * Hyvä caches every product tile in the block cache. Guest and customer tiles differ (sign in text vs price,
 * no add to cart), so the login state is part of the cache key.
 */
class ProductListItemCacheKeyPlugin
{
    public function __construct(
        private readonly LoginState $loginState
    ) {
    }

    public function afterGetItemCacheKeyInfo(ProductListItem $subject, array $result): array
    {
        $result[] = $this->loginState->isLoggedIn() ? 'elme_logged_in' : 'elme_guest';
        return $result;
    }
}
