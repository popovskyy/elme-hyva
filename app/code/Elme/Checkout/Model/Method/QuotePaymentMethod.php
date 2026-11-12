<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Method;

use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Framework\Phrase;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the payment method of the quote. The list is Magento's, so a method limited to some customers (a plugin on
 * the method list, or the method's own isAvailable) needs no change here.
 */
class QuotePaymentMethod
{
    public const SECTION = 'payment';

    /**
     * The checks core runs when a method is set through the API.
     */
    private const CHECKS = [
        AbstractMethod::CHECK_USE_CHECKOUT,
        AbstractMethod::CHECK_USE_FOR_COUNTRY,
        AbstractMethod::CHECK_USE_FOR_CURRENCY,
        AbstractMethod::CHECK_ORDER_TOTAL_MIN_MAX,
    ];

    public function __construct(
        private readonly PaymentMethodManagementInterface $paymentMethodManagement,
        private readonly QuoteShippingMethod $shippingMethod,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger,
        private readonly ShippingRatesFreshness $ratesFreshness
    ) {
    }

    /**
     * Brings the chosen method in line with what the quote allows now: one that is gone is cleared, and a single
     * method is chosen for the customer. A locked section is left alone, its choice counts again once it opens.
     */
    public function refresh(Quote $quote): void
    {
        try {
            if ($this->getLockReason($quote) !== null) {
                return;
            }

            $current = $this->getSelected($quote);
            $codes = array_map(
                static fn (MethodInterface $method): string => (string) $method->getCode(),
                $this->getMethods($quote)
            );
            if (in_array($current, $codes, true)) {
                return;
            }

            $next = count($codes) === 1 ? $codes[0] : '';
            if ($next !== $current) {
                $this->write($quote, $next);
            }
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf('Checkout payment method of quote %d: %s', $quote->getId(), $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }

    /**
     * @param Quote $quote
     * @param string $code
     * @return void
     * @throws CheckoutInputException
     */
    public function select(Quote $quote, string $code): void
    {
        $lockReason = $this->getLockReason($quote);
        if ($lockReason !== null) {
            throw new CheckoutInputException($lockReason, self::SECTION);
        }

        foreach ($this->getMethods($quote) as $method) {
            if ($method->getCode() === $code) {
                $this->write($quote, $code);
                return;
            }
        }

        throw new CheckoutInputException(
            __('This payment method is no longer available. Please refresh the page.'),
            self::SECTION
        );
    }

    /**
     * @param Quote $quote
     * @return MethodInterface[]
     */
    public function getMethods(Quote $quote): array
    {
        return array_values($this->paymentMethodManagement->getList((int) $quote->getId()));
    }

    public function getSelected(Quote $quote): string
    {
        return (string) $quote->getPayment()->getMethod();
    }

    /**
     * Why the section cannot be used yet, null when it can.
     */
    public function getLockReason(Quote $quote): ?Phrase
    {
        $lockReason = $this->shippingMethod->getLockReason($quote);
        if ($lockReason !== null) {
            return $lockReason;
        }

        return $this->shippingMethod->getSelected($quote) !== '' ? null : __('Choose a delivery method first');
    }

    private function write(Quote $quote, string $code): void
    {
        $payment = $quote->getPayment();
        $payment->unsMethodInstance();

        if ($code === '') {
            $payment->setMethod(null);
        } else {
            $payment->importData(['method' => $code, 'checks' => self::CHECKS]);
        }
        $quote->getShippingAddress()->setPaymentMethod($code);

        // Payment does not change delivery rates; a leftover collect_shipping_rates from a prior save must not ask
        $this->ratesFreshness->mark();
        $quote->getShippingAddress()->setCollectShippingRates(false);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($quote);
    }
}
