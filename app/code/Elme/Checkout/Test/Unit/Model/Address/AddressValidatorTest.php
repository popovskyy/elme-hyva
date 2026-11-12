<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Address;

use Elme\Checkout\Model\Address\AddressValidator;
use Magento\Directory\Model\Country\Postcode\ValidatorInterface as PostcodeValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Address\Validator\AddressAttributeValidatorInterface;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AddressValidatorTest extends TestCase
{
    public function testMagentoErrorsLandOnTheirFormFields(): void
    {
        $errors = $this->validator()->getErrors($this->address([
            new Phrase('"%fieldName" is required. Enter and try again.', ['fieldName' => 'firstname']),
            new Phrase('"%fieldName" is required. Enter and try again.', ['fieldName' => 'street']),
            new Phrase('"%fieldName" is required. Enter and try again.', ['fieldName' => 'regionId']),
        ]));

        $this->assertSame(['firstname', 'street_1', 'region_id'], array_keys($errors));
        $this->assertStringNotContainsString('fieldName', implode(' ', $errors));
    }

    public function testErrorWithoutAFieldIsGeneral(): void
    {
        $errors = $this->validator()->getErrors($this->address([new Phrase('Somebody else\'s address.')]));

        $this->assertSame([AddressValidator::GENERAL => 'Somebody else\'s address.'], $errors);
    }

    public function testPostcodeFormatIsAFieldError(): void
    {
        $errors = $this->validator(postcodeValid: false)->getErrors($this->address(true));

        $this->assertSame(['postcode'], array_keys($errors));
    }

    public function testAttributeRulesOnlyRunOnAnOtherwiseValidAddress(): void
    {
        $attributeValidator = $this->createMock(AddressAttributeValidatorInterface::class);
        $attributeValidator->expects($this->once())
            ->method('validate')
            ->willThrowException(new InputException(new Phrase('First Name is not valid!')));

        $this->assertSame(
            [AddressValidator::GENERAL => 'First Name is not valid!'],
            $this->validator(attributeValidator: $attributeValidator)->getErrors($this->address(true))
        );

        $untouched = $this->createMock(AddressAttributeValidatorInterface::class);
        $untouched->expects($this->never())->method('validate');
        $this->validator(attributeValidator: $untouched)->getErrors($this->address([
            new Phrase('"%fieldName" is required. Enter and try again.', ['fieldName' => 'city']),
        ]));
    }

    public function testValidAddressHasNoErrors(): void
    {
        $this->assertSame([], $this->validator()->getErrors($this->address(true)));
    }

    private function validator(
        bool $postcodeValid = true,
        ?AddressAttributeValidatorInterface $attributeValidator = null
    ): AddressValidator {
        $postcodeValidator = $this->createStub(PostcodeValidator::class);
        $postcodeValidator->method('validate')->willReturn($postcodeValid);

        return new AddressValidator(
            $postcodeValidator,
            $attributeValidator ?? $this->createStub(AddressAttributeValidatorInterface::class)
        );
    }

    /**
     * @param true|mixed[] $validateResult
     * @return Address
     */
    private function address(bool|array $validateResult): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['validate'])
            ->getMock();
        $address->method('validate')->willReturn($validateResult);

        return $address;
    }
}
