<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model\LeadTime;

use Magento\Catalog\Model\Product;

/**
 * The one place that answers "when does a made-to-order product reach the customer". The lead time
 * comes from the Shipment Service later: change the preference in etc/di.xml, nothing else moves.
 */
interface LeadTimeProviderInterface
{
    /**
     * Null when there is no lead time to show (Quick Ship, or no value known).
     */
    public function getLeadTime(Product $product): ?LeadTime;
}
