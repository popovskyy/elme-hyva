<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Section;

use Elme\Checkout\Api\SectionProviderInterface;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * The payment section: the methods the quote can be paid with.
 */
class PaymentProvider implements SectionProviderInterface
{
    public function __construct(
        private readonly QuotePaymentMethod $paymentMethod
    ) {
    }

    public function getData(CartInterface $quote): array
    {
        /** @var Quote $quote */
        $lockReason = $this->paymentMethod->getLockReason($quote);
        if ($lockReason !== null) {
            return ['locked' => (string) $lockReason, 'methods' => [], 'selected' => ''];
        }

        $methods = [];
        foreach ($this->paymentMethod->getMethods($quote) as $method) {
            $methods[] = [
                'code' => (string) $method->getCode(),
                'title' => (string) $method->getTitle(),
                'instructions' => $this->getInstructions($method),
            ];
        }

        return ['locked' => '', 'methods' => $methods, 'selected' => $this->paymentMethod->getSelected($quote)];
    }

    /**
     * Bank transfer and cash on delivery have an instructions field; check / money order has these two instead.
     */
    private function getInstructions(MethodInterface $method): string
    {
        $payableTo = trim((string) $method->getConfigData('payable_to'));
        $mailingAddress = trim((string) $method->getConfigData('mailing_address'));

        return implode("\n", array_filter([
            trim((string) $method->getConfigData('instructions')),
            $payableTo !== '' ? (string) __('Make check payable to: %1', $payableTo) : '',
            $mailingAddress !== '' ? (string) __('Send check to: %1', $mailingAddress) : '',
        ]));
    }
}
