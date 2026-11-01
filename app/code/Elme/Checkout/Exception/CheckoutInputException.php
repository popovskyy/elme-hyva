<?php

declare(strict_types=1);

namespace Elme\Checkout\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * A rule the customer can fix. The frontend shows it in the given section and next to the given fields;
 * without a section it is the message at the top of the page.
 */
class CheckoutInputException extends LocalizedException
{
    /**
     * @param Phrase $phrase
     * @param string $section Section code, empty for the top message
     * @param string[] $fieldErrors Field name => message
     */
    public function __construct(
        Phrase $phrase,
        private readonly string $section = '',
        private readonly array $fieldErrors = []
    ) {
        parent::__construct($phrase);
    }

    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * @return string[]
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
