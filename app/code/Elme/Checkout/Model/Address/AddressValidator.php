<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Address;

use InvalidArgumentException;
use Magento\Directory\Model\Country\Postcode\ValidatorInterface as PostcodeValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Address\Validator\AddressAttributeValidatorInterface;
use Magento\Quote\Model\Quote\Address as QuoteAddress;

/**
 * Runs Magento's address validators and sorts their answers per field of the checkout address form.
 */
class AddressValidator
{
    /**
     * Key in getErrors() of a message that belongs to no field.
     */
    public const GENERAL = '';

    private const FORM_FIELDS = [
        'firstname' => 'firstname',
        'lastname' => 'lastname',
        'company' => 'company',
        'street' => 'street_1',
        'city' => 'city',
        'region' => 'region_id',
        'regionId' => 'region_id',
        'postcode' => 'postcode',
        'telephone' => 'telephone',
    ];

    public function __construct(
        private readonly PostcodeValidator $postcodeValidator,
        private readonly AddressAttributeValidatorInterface $attributeValidator
    ) {
    }

    /**
     * @param QuoteAddress $address
     * @return array<string, string> Form field name, or self::GENERAL, => message
     */
    public function getErrors(QuoteAddress $address): array
    {
        $errors = [];
        $result = $address->validate();
        foreach ($result === true ? [] : $result as $error) {
            $code = $error instanceof Phrase ? (string) ($error->getArguments()['fieldName'] ?? '') : '';
            $field = self::FORM_FIELDS[$code] ?? self::GENERAL;
            $errors[$field] = $field === self::GENERAL ? (string) $error : $this->fieldMessage($field);
        }

        if (!isset($errors['postcode']) && !$this->isPostcodeValid($address)) {
            $errors['postcode'] = (string) __('Enter a 4-digit postcode.');
        }

        // The name, street and phone format rules only say something once the required fields are there
        if (!$errors) {
            try {
                $this->attributeValidator->validate($address, 'shipping');
            } catch (LocalizedException $exception) {
                $errors[self::GENERAL] = $exception->getMessage();
            }
        }

        return $errors;
    }

    private function isPostcodeValid(QuoteAddress $address): bool
    {
        try {
            return $this->postcodeValidator->validate((string) $address->getPostcode(), (string) $address->getCountryId());
        } catch (InvalidArgumentException) {
            return true;
        }
    }

    private function fieldMessage(string $field): string
    {
        return (string) match ($field) {
            'firstname' => __('Enter a first name.'),
            'lastname' => __('Enter a last name.'),
            'company' => __('Enter a company.'),
            'street_1' => __('Enter a street address.'),
            'city' => __('Enter a suburb.'),
            'region_id' => __('Select a state.'),
            'postcode' => __('Enter a 4-digit postcode.'),
            default => __('Enter a phone number.'),
        };
    }
}
