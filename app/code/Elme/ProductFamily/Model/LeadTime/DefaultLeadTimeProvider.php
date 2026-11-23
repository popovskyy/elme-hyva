<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model\LeadTime;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Today's lead time: one value for every made-to-order product, as on the old site. Weeks and the
 * wording are set in Stores > Configuration > Elme > Catalog > Product family (14 weeks, "With you in
 * 14 weeks").
 */
class DefaultLeadTimeProvider implements LeadTimeProviderInterface
{
    public const XML_PATH_DEFAULT_WEEKS = 'elme_catalog/product_family/lead_time_weeks';
    public const XML_PATH_LABEL = 'elme_catalog/product_family/lead_time_label';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getLeadTime(Product $product): ?LeadTime
    {
        if (!(bool) $product->getData('made_to_order')) {
            return null;
        }
        $weeks = (int) $this->scopeConfig->getValue(self::XML_PATH_DEFAULT_WEEKS, ScopeInterface::SCOPE_STORE);
        if ($weeks < 1) {
            return null;
        }
        $text = $weeks === 1 ? (string) __('1 week') : (string) __('%1 weeks', $weeks);

        return new LeadTime($weeks, $this->label($text));
    }

    /**
     * The admin wording with %1 replaced by "14 weeks"; the translated default when it is empty.
     */
    private function label(string $weeksText): string
    {
        $template = trim((string) $this->scopeConfig->getValue(self::XML_PATH_LABEL, ScopeInterface::SCOPE_STORE));
        if ($template === '') {
            return (string) __('With you in %1', $weeksText);
        }

        return str_replace('%1', $weeksText, $template);
    }
}
