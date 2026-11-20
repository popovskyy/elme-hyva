<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml;

use Magento\Backend\Block\Template;

/**
 * The branded overlay shown on arriving in Content Studio.
 *
 * Rendered on every Studio page so it can cover whatever the page is still
 * assembling underneath; splash.js decides whether to actually play it, and
 * only does so once per browser session.
 */
class Splash extends Template
{
    /**
     * @return string
     */
    public function getTagline(): string
    {
        return (string)__('Schema-driven content for storefront pages.');
    }
}
