<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Method;

use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the delivery method of the quote. Rates come from whatever Magento carriers are active, so a new carrier
 * shows up here with no change.
 */
class QuoteShippingMethod
{
    public const SECTION = 'delivery';

    private const PRICE_PRECISION = 0.0001;

    public function __construct(
        private readonly ShippingMethodConverter $converter,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Asks the carriers again and brings the chosen method in line: one that is gone is cleared, a single rate is chosen
     * for the customer, and a new price is saved (the order takes the saved one). A carrier problem must not cost the
     * customer the checkout page.
     */
    public function refresh(Quote $quote): void
    {
        try {
            $shipping = $quote->getShippingAddress();
            $current = (string) $shipping->getShippingMethod();

            if ($this->getLockReason($quote) !== null) {
                if ($current !== '') {
                    $this->write($quote, $shipping, '');
                }
                return;
            }

            $savedPrice = (float) $shipping->getBaseShippingAmount();
            $this->collectRates($quote);
            $rates = $this->getRates($quote);
            foreach ($rates as $rate) {
                if ($this->getCode($rate) === $current) {
                    if (abs((float) $rate->getBaseAmount() - $savedPrice) > self::PRICE_PRECISION) {
                        $this->write($quote, $shipping, $current);
                    }
                    return;
                }
            }

            $next = count($rates) === 1 ? $this->getCode($rates[0]) : '';
            if ($next !== $current) {
                $this->write($quote, $shipping, $next);
            }
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf('Checkout delivery method of quote %d: %s', $quote->getId(), $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }

    /**
     * @param Quote $quote
     * @param string $carrierCode
     * @param string $methodCode
     * @return void
     * @throws CheckoutInputException
     */
    public function select(Quote $quote, string $carrierCode, string $methodCode): void
    {
        $lockReason = $this->getLockReason($quote);
        if ($lockReason !== null) {
            throw new CheckoutInputException($lockReason, self::SECTION);
        }

        $shipping = $quote->getShippingAddress();
        $this->collectRates($quote);

        foreach ($this->getRates($quote) as $rate) {
            if ($rate->getCarrierCode() === $carrierCode && $rate->getMethodCode() === $methodCode) {
                $this->write($quote, $shipping, $this->getCode($rate));
                return;
            }
        }

        throw new CheckoutInputException(
            __('This delivery method is no longer available. Please refresh the page.'),
            self::SECTION
        );
    }

    /**
     * The rates the customer can choose from, in carrier sort order. A carrier that only reports an error is left out.
     *
     * @param Quote $quote
     * @return ShippingMethodInterface[]
     */
    public function getRates(Quote $quote): array
    {
        $rates = [];
        foreach ($quote->getShippingAddress()->getGroupedAllShippingRates() as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $method = $this->converter->modelToDataObject($rate, $quote->getQuoteCurrencyCode());
                if ($method->getAvailable()) {
                    $rates[] = $method;
                }
            }
        }

        return $rates;
    }

    public function getCode(ShippingMethodInterface $rate): string
    {
        return $rate->getCarrierCode() . '_' . $rate->getMethodCode();
    }

    public function getSelected(Quote $quote): string
    {
        return (string) $quote->getShippingAddress()->getShippingMethod();
    }

    public function getSavedPrice(Quote $quote): float
    {
        return (float) $quote->getShippingAddress()->getBaseShippingAmount();
    }

    /**
     * Why the section cannot be used yet, null when it can.
     */
    public function getLockReason(Quote $quote): ?Phrase
    {
        return $quote->getShippingAddress()->getFirstname() ? null : __('Add a delivery address first');
    }

    /**
     * Through the totals, as core does: the shipping total gives the address the qty and weight that carriers charge by,
     * and these are not stored on a loaded address.
     */
    private function collectRates(Quote $quote): void
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
    }

    private function write(Quote $quote, QuoteAddress $shipping, string $code): void
    {
        $shipping->setShippingMethod($code);
        if ($code === '') {
            $shipping->setShippingDescription('');
        }

        // Core keeps the method the quote was loaded with in a shipping assignment; saving the cart would put it back
        foreach ($quote->getExtensionAttributes()?->getShippingAssignments() ?: [] as $assignment) {
            $assignment->getShipping()->setMethod($code);
        }

        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($quote);
    }
}
