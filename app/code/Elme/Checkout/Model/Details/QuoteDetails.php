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
     * @param CartInterface $quote
     * @param mixed[] $details Field => value; a field that is not given stays as it is
     * @return void
     * @throws CheckoutInputException
     */
    public function save(CartInterface $quote, array $details): void
    {
        /** @var Quote $quote */
        $field = OrderDetailsInterface::DELIVERY_INSTRUCTIONS;
        if (!isset($details[$field]) || !is_string($details[$field])) {
            return;
        }

        $value = $this->normalize($details[$field]);
        $maxLength = OrderDetailsInterface::DELIVERY_INSTRUCTIONS_MAX_LENGTH;
        if (mb_strlen($value) > $maxLength) {
            throw new CheckoutInputException(__('Please check your delivery instructions.'), self::SECTION, [
                $field => (string) __('Delivery instructions can have up to %1 characters.', $maxLength),
            ]);
        }

        if ($value !== (string) $quote->getData($field)) {
            $quote->setData($field, $value === '' ? null : $value);
            $this->cartRepository->save($quote);
        }
    }

    /**
     * Instructions keep their line breaks, in one form: the browser counts them as one character.
     */
    private function normalize(string $value): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }
}
