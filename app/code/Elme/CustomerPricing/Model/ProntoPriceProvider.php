<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

use Elme\CustomerPricing\Model\ResourceModel\ProntoPriceData;
use Elme\LoginOnly\Api\CustomerPriceProviderInterface;
use Elme\LoginOnly\Model\GroupPriceProvider;

/**
 * Customer prices from the Pronto price mappings (see PriceCalculator), used by the price endpoint and the cart.
 *
 * A SKU without a Pronto price list row takes Magento's price for the customer's group as its list price and promo,
 * so the catalogue keeps its prices until the Pronto feed fills the list. A configurable costs its cheapest enabled
 * simple product. Results are kept for the request: the cart collects totals several times per request.
 */
class ProntoPriceProvider implements CustomerPriceProviderInterface
{
    /**
     * @var array<string, array<int, array{final: float, regular: float}|null>>
     */
    private array $prices = [];

    public function __construct(
        private readonly PricedSkus $pricedSkus,
        private readonly ProntoCustomerResolver $customerResolver,
        private readonly ProntoPriceData $priceData,
        private readonly PriceCalculator $calculator,
        private readonly GroupPriceProvider $magentoPrices
    ) {
    }

    public function getPrices(array $productIds, int $customerId, int $customerGroupId): array
    {
        $key = $customerId . ':' . $customerGroupId;
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        $missing = array_diff($productIds, array_keys($this->prices[$key] ?? []));
        if ($missing) {
            $this->prices[$key] = ($this->prices[$key] ?? []) + $this->load($missing, $customerId, $customerGroupId);
        }

        return array_filter(array_intersect_key($this->prices[$key] ?? [], array_flip($productIds)));
    }

    /**
     * @param int[] $productIds
     * @return array<int, array{final: float, regular: float}|null> Every id asked for, null = no price
     */
    private function load(array $productIds, int $customerId, int $customerGroupId): array
    {
        $skusOf = $this->pricedSkus->get($productIds);
        $skus = array_replace([], ...array_values($skusOf));
        $sources = $this->priceData->getSources($skus, $this->customerResolver->get($customerId));

        $withoutList = array_keys(array_filter(
            $skus,
            static fn (string $sku): bool => ($sources[$sku] ?? null)?->listPrice === null
        ));
        $magentoPrices = $this->magentoPrices->getPrices($withoutList, $customerId, $customerGroupId);

        $skuPrices = [];
        foreach ($skus as $simpleId => $sku) {
            $skuSources = $sources[$sku] ?? new PriceSources();
            if ($skuSources->listPrice === null && isset($magentoPrices[$simpleId])) {
                $magento = $magentoPrices[$simpleId];
                $skuSources = $skuSources->withListPrice(
                    $magento['regular'],
                    $magento['final'] < $magento['regular'] ? $magento['final'] : null
                );
            }
            $skuPrices[$simpleId] = $this->calculator->calculate($skuSources);
        }

        $prices = array_fill_keys($productIds, null);
        foreach ($skusOf as $productId => $simpleSkus) {
            foreach (array_keys($simpleSkus) as $simpleId) {
                $price = $skuPrices[$simpleId] ?? null;
                if ($price !== null && ($prices[$productId] === null || $price['final'] < $prices[$productId]['final'])) {
                    $prices[$productId] = $price;
                }
            }
        }

        return $prices;
    }
}
