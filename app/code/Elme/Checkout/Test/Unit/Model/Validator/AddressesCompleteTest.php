<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Address\AddressValidator;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\Validator\AddressesComplete;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AddressesCompleteTest extends TestCase
{
    public function testNoDeliveryAddressIsRefused(): void
    {
        $this->assertRefused(
            $this->quote($this->address(''), $this->address('Bill')),
            [],
            'Please add a delivery address.'
        );
    }

    public function testIncompleteDeliveryAddressIsRefused(): void
    {
        $shipping = $this->address('Ship');

        $this->assertRefused(
            $this->quote($shipping, $this->address('Bill')),
            [[$shipping, ['postcode' => 'Enter']]],
            'Your delivery address is missing some details. Please check it.'
        );
    }

    public function testIncompleteBillingAddressIsRefused(): void
    {
        $billing = $this->address('Bill');

        $this->assertRefused(
            $this->quote($this->address('Ship'), $billing),
            [[$billing, ['city' => 'Enter']]],
            'Your billing address is missing some details. Please check it.'
        );
    }

    #[DoesNotPerformAssertions]
    public function testCompleteAddressesPass(): void
    {
        $validator = $this->createStub(AddressValidator::class);
        $validator->method('getErrors')->willReturn([]);

        (new AddressesComplete($validator))->validate($this->quote($this->address('Ship'), $this->address('Bill')));
    }

    /**
     * @param Quote $quote
     * @param array $errors [address, errors]
     * @param string $message
     */
    private function assertRefused(Quote $quote, array $errors, string $message): void
    {
        $validator = $this->createStub(AddressValidator::class);
        $validator->method('getErrors')->willReturnCallback(static function (Address $address) use ($errors): array {
            foreach ($errors as [$invalid, $messages]) {
                if ($invalid === $address) {
                    return $messages;
                }
            }
            return [];
        });

        try {
            (new AddressesComplete($validator))->validate($quote);
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteAddresses::SECTION, $exception->getSection());
            $this->assertSame($message, (string) $exception->getMessage());
        }
    }

    private function quote(Address $shipping, Address $billing): Quote
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);

        return $quote;
    }

    private function address(string $firstname): Address
    {
        $address = $this->createStub(Address::class);
        $address->method('getFirstname')->willReturn($firstname);

        return $address;
    }
}
