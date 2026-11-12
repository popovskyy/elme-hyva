<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

/**
 * The Elme price of one SKU for one customer, ported from Bart\Pronto\Model\Price\ElmePriceService.
 *
 * Every source that has a value is a candidate and the lowest one wins: list price, list promo, region price and
 * promo, A1 customer price, type 20 marketing price or percent off the list price, type 25 item group percent off the
 * list price. Percent discounts need a list price. Prices that are not above zero are ignored.
 *
 * `promo` is true only when a list or region promo is among the winners (same final). Contract, A1, marketing and
 * group discounts are not sales: the storefront shows those as a plain "Your price".
 */
class PriceCalculator
{
    /**
     * @return array{final: float, regular: float, promo: bool}|null Null when no source prices the SKU.
     */
    public function calculate(PriceSources $sources): ?array
    {
        $list = $sources->listPrice !== null && $sources->listPrice > 0 ? $sources->listPrice : null;

        $candidates = [
            'list' => $list,
            'list_promo' => $sources->listPromo,
            'region' => $sources->regionPrice,
            'region_promo' => $sources->regionPromo,
            'customer' => $sources->customerPrice,
            'marketing' => $sources->marketingPrice,
        ];
        if ($list !== null) {
            $discounts = $sources->groupDiscounts;
            if ($sources->marketingDiscount !== null) {
                $discounts[] = $sources->marketingDiscount;
            }
            foreach ($discounts as $i => $discount) {
                $candidates['discount_' . $i] = $list * (1 - $discount / 100);
            }
        }

        $candidates = array_filter(
            $candidates,
            static fn (?float $price): bool => $price !== null && $price > 0
        );
        if (!$candidates) {
            return null;
        }

        $final = round(min($candidates), 2);
        $promo = false;
        foreach ($candidates as $key => $price) {
            if (round((float) $price, 2) !== $final) {
                continue;
            }
            if ($key === 'list_promo' || $key === 'region_promo') {
                $promo = true;
                break;
            }
        }

        return [
            'final' => $final,
            'regular' => $list !== null ? max(round($list, 2), $final) : $final,
            'promo' => $promo && $list !== null && round($list, 2) > $final,
        ];
    }
}
