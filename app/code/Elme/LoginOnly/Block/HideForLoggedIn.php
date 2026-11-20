<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Block;

/**
 * Renders only for guests.
 */
class HideForLoggedIn extends AbstractHide
{
    protected function shouldShowBlock(): bool
    {
        return $this->loginState->isGuest();
    }
}
