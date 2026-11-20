<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

/**
 * Everything that can price one SKU for one customer, ex GST. Null = that source has nothing for the SKU.
 */
class PriceSources
{
    /**
     * @param float|null $listPrice Pronto price list (pronto_elme_price_list.price), or Magento's price without a row
     * @param float|null $listPromo Active Pronto price list promo, for everyone
     * @param float|null $regionPrice Price of the customer's region group (pronto_elme_price_list_mapping)
     * @param float|null $regionPromo Active promo of the customer's region group
     * @param float|null $customerPrice Discount type A1: fixed price for the Pronto customer id
     * @param float|null $marketingPrice Discount type 20 with a price: fixed price for the marketing flag
     * @param float|null $marketingDiscount Discount type 20 with a discount: percent off the list price
     * @param float[] $groupDiscounts Discount type 25: percent off the list price for the SKU's item groups
     */
    public function __construct(
        public readonly ?float $listPrice = null,
        public readonly ?float $listPromo = null,
        public readonly ?float $regionPrice = null,
        public readonly ?float $regionPromo = null,
        public readonly ?float $customerPrice = null,
        public readonly ?float $marketingPrice = null,
        public readonly ?float $marketingDiscount = null,
        public readonly array $groupDiscounts = []
    ) {
    }

    /**
     * The SKU has no Pronto price list row: Magento's own price stands in for the list price and its promo.
     */
    public function withListPrice(float $listPrice, ?float $listPromo): self
    {
        return new self(
            $listPrice,
            $listPromo,
            $this->regionPrice,
            $this->regionPromo,
            $this->customerPrice,
            $this->marketingPrice,
            $this->marketingDiscount,
            $this->groupDiscounts
        );
    }
}
