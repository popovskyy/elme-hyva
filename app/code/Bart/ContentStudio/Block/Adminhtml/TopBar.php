<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml;

use Magento\Backend\Block\Template;

/**
 * Minimal top bar for the full-bleed Content Studio pages (plan section 3a) - stands
 * in for the Magento admin chrome removed by bart_content_studio_full_bleed.xml,
 * and is the only way back out of a chrome-less page.
 */
class TopBar extends Template
{
    private const LISTING_ACTION = 'bart_content_studio_entry_index';

    /**
     * @return string
     */
    public function getListUrl(): string
    {
        return $this->getUrl('bart_content_studio/entry/index');
    }

    /**
     * @return string
     */
    public function getDashboardUrl(): string
    {
        return $this->getUrl('adminhtml/dashboard');
    }

    /**
     * The listing is where the back link points, so showing it there would be
     * a link to the current page.
     *
     * @return bool
     */
    public function isBackLinkVisible(): bool
    {
        return !$this->isEmbedded() && $this->getRequest()->getFullActionName() !== self::LISTING_ACTION;
    }

    /**
     * Framed inside another admin form (`?embedded=1`, e.g. a modal opened
     * from another entity's edit page). Both of this bar's links navigate the
     * *frame*, so inside a modal they strand the author on the entry grid or
     * the admin dashboard rendered in a slide-out panel, with no way back and
     * the unsaved edit gone. The wordmark stays: it identifies the panel and
     * navigates nowhere.
     *
     * Same request parameter {@see Editor} reads for the same reason.
     *
     * @return bool
     */
    public function isEmbedded(): bool
    {
        return (bool)$this->getRequest()->getParam('embedded');
    }
}
