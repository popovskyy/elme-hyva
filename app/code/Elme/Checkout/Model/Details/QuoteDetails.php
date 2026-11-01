<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Details;

use Elme\Checkout\Api\OrderDetailsInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Writes the order details of the quote.
 */
class QuoteDetails
{
    public const SECTION = 'details';

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * A field that is too long is refused, the other one is still saved: an error in one must not lose the other.
     *
     * @param CartInterface $quote
     * @param mixed[] $details Field => value; a field that is not given stays as it is
     * @return void
     * @throws CheckoutInputException
     */
    public function save(CartInterface $quote, array $details): void
    {
        /** @var Quote $quote */
        $errors = [];
        $isChanged = false;
        foreach ($this->getRules() as $field => [$maxLength, $error]) {
            if (!isset($details[$field]) || !is_string($details[$field])) {
                continue;
            }

            $value = $this->normalize($field, $details[$field]);
            if (mb_strlen($value) > $maxLength) {
                $errors[$field] = (string) $error;
                continue;
            }

            if ($value !== (string) $quote->getData($field)) {
                $quote->setData($field, $value === '' ? null : $value);
                $isChanged = true;
            }
        }

        if ($isChanged) {
            $this->cartRepository->save($quote);
        }

        if ($errors) {
            throw new CheckoutInputException(__('Please check your order details.'), self::SECTION, $errors);
        }
    }

    /**
     * @return array<string, array{int, \Magento\Framework\Phrase}>
     */
    private function getRules(): array
    {
        $referenceLength = OrderDetailsInterface::CUSTOMER_REFERENCE_MAX_LENGTH;
        $instructionsLength = OrderDetailsInterface::DELIVERY_INSTRUCTIONS_MAX_LENGTH;

        return [
            OrderDetailsInterface::CUSTOMER_REFERENCE => [
                $referenceLength,
                __('Your order reference can have up to %1 characters.', $referenceLength),
            ],
            OrderDetailsInterface::DELIVERY_INSTRUCTIONS => [
                $instructionsLength,
                __('Delivery instructions can have up to %1 characters.', $instructionsLength),
            ],
        ];
    }

    /**
     * The reference is one line. Instructions keep their line breaks, in one form: the browser counts them as one
     * character.
     */
    private function normalize(string $field, string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($field === OrderDetailsInterface::CUSTOMER_REFERENCE) {
            $value = str_replace("\n", ' ', $value);
        }

        return trim($value);
    }
}
