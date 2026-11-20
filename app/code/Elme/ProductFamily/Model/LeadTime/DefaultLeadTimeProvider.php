<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model\LeadTime;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Today's lead time: one value for every made-to-order product, as on the old site
 * (config elme_product_family/lead_time/default_weeks, 14 weeks).
 */
class DefaultLeadTimeProvider implements LeadTimeProviderInterface
{
    public const XML_PATH_DEFAULT_WEEKS = 'elme_product_family/lead_time/default_weeks';

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

        return new LeadTime($weeks, (string) __('With you in %1', $text));
    }
}
