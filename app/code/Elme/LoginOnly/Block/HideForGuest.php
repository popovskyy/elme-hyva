<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Block;

/**
 * Renders only for logged-in customers.
 */
class HideForGuest extends AbstractHide
{
    protected function shouldShowBlock(): bool
    {
        return $this->loginState->isLoggedIn();
    }
}
