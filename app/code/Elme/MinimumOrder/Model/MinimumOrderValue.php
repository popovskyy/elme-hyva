<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Model;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 * The one place that knows a customer's minimum order value and how the current subtotal (ex GST)
 * stands against it. Templates, customer section data, cart proceed state and the place-order
 * validator all use this.
 *
 * @phpstan-type Status array{
 *     enabled: bool,
 *     minimum: float,
 *     subtotal: float,
 *     remaining: float,
 *     met: bool,
 *     minimum_formatted: string,
 *     subtotal_formatted: string,
 *     remaining_formatted: string
 * }
 */
class MinimumOrderValue implements ArgumentInterface
{
    public const ATTRIBUTE_CODE = 'minimum_order_value';

    private const XML_PATH_ACTIVE = 'sales/minimum_order/active';
    private const XML_PATH_AMOUNT = 'sales/minimum_order/amount';

    /** Same float tolerance Magento uses in Address::validateMinimumAmount(). */
    private const EPSILON = 0.0001;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Threshold for the customer: attribute when set, else sales/minimum_order/amount. 0 when off.
     */
    public function getMinimum(?CustomerInterface $customer = null, ?int $storeId = null): float
    {
        if (!$this->isEnabled($storeId)) {
            return 0.0;
        }

        $fromAttribute = $this->getAttributeMinimum($customer);
        if ($fromAttribute !== null) {
            return $fromAttribute;
        }

        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * How a subtotal (ex GST) stands against the customer's minimum.
     *
     * @return Status
     */
    public function evaluate(
        float $subtotalExGst,
        ?CustomerInterface $customer = null,
        ?int $storeId = null
    ): array {
        $enabled = $this->isEnabled($storeId);
        $minimum = $enabled ? $this->getMinimum($customer, $storeId) : 0.0;
        $subtotal = round($subtotalExGst, 2);
        $remaining = $enabled ? max(0.0, round($minimum - $subtotal, 2)) : 0.0;
        $met = !$enabled || $remaining <= self::EPSILON;

        return [
            'enabled' => $enabled,
            'minimum' => $minimum,
            'subtotal' => $subtotal,
            'remaining' => $met ? 0.0 : $remaining,
            'met' => $met,
            'minimum_formatted' => $this->format($minimum),
            'subtotal_formatted' => $this->format($subtotal),
            'remaining_formatted' => $this->format($met ? 0.0 : $remaining),
        ];
    }

    /**
     * Status for a quote: base subtotal is ex GST when catalog prices are ex tax.
     *
     * @return Status
     */
    public function evaluateQuote(CartInterface $quote): array
    {
        // base_subtotal is the ex-GST merchandise total Magento stores on the quote.
        $subtotal = $quote instanceof Quote ? (float) $quote->getData('base_subtotal') : 0.0;

        return $this->evaluate(
            $subtotal,
            $quote->getCustomer(),
            (int) $quote->getStoreId() ?: null
        );
    }

    /**
     * Status for the logged-in customer's current quote (cart page / templates). Empty cart = subtotal 0.
     *
     * @return Status
     */
    public function getStatus(): array
    {
        $customer = null;
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomerDataObject();
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote->getId()) {
                return $this->evaluateQuote($quote);
            }
        } catch (NoSuchEntityException | LocalizedException) {
            // fall through with subtotal 0
        }

        return $this->evaluate(0.0, $customer);
    }

    public function format(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Attribute value when present and non-empty; null means use the config default.
     */
    private function getAttributeMinimum(?CustomerInterface $customer): ?float
    {
        if ($customer === null || !$customer->getId()) {
            return null;
        }

        $attribute = $customer->getCustomAttribute(self::ATTRIBUTE_CODE);
        if ($attribute === null) {
            return null;
        }

        $value = $attribute->getValue();
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
