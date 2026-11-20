<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Template;

use Magento\Email\Model\Template\Filter;

/**
 * Template filter for entry content: keeps `{{media url="..."}}`,
 * `{{store ...}}` and `{{config ...}}` working (so exported/re-imported
 * entries resolve media across environments - see plan section 9) but disables
 * `{{block class="..."}}` and `{{layout ...}}`, which would let a Content
 * Studio editor instantiate an arbitrary block or run layout XML.
 *
 * CMS blocks accept that risk deliberately (trusted admin content editors);
 * Content Studio may end up with a wider pool of editors, so the narrower
 * default here is intentional.
 */
class SafeFilter extends Filter
{
    /**
     * @param string $construction
     * @return string
     */
    public function blockDirective($construction): string
    {
        return '';
    }

    /**
     * @param string $construction
     * @return string
     */
    public function layoutDirective($construction): string
    {
        return '';
    }
}
