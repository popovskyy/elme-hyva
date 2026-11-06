<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

/**
 * The Pronto side of a Magento customer: the id A1 prices are keyed by, and the marketing flag and region group from
 * pronto_elme_customer_mapping (null without a row).
 */
class ProntoCustomer
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $marketingFlag = null,
        public readonly ?string $regionGroup = null
    ) {
    }
}
