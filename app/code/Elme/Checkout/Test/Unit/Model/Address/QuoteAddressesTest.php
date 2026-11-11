<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Address;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Address\AddressBook;
use Elme\Checkout\Model\Address\AddressFormatter;
use Elme\Checkout\Model\Address\AddressValidator;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\Method\ShippingRatesFreshness;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddress;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\AddressFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class QuoteAddressesTest extends TestCase
{
    private const CUSTOMER_ID = 7;

    private AddressBook&MockObject $addressBook;
    private AddressValidator&MockObject $validator;
    private CartRepositoryInterface&MockObject $cartRepository;
    private QuoteAddress $shipping;
    private QuoteAddress $billing;
    private Quote&MockObject $quote;

    protected function setUp(): void
    {
        $this->addressBook = $this->createMock(AddressBook::class);
        $this->validator = $this->createMock(AddressValidator::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->shipping = $this->quoteAddress();
        $this->billing = $this->quoteAddress();

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getBillingAddress', 'collectTotals', 'getId'])
            ->getMock();
        $this->quote->setData(['customer_id' => self::CUSTOMER_ID, 'customer_email' => 'shop@example.com']);
        $this->quote->method('getId')->willReturn(1);
        $this->quote->method('getShippingAddress')->willReturn($this->shipping);
        $this->quote->method('getBillingAddress')->willReturn($this->billing);
        $this->quote->method('collectTotals')->willReturnSelf();
    }

    public function testFirstLoadWritesBothDefaults(): void
    {
        $this->addressBook->method('getDefaultShipping')->willReturn($this->saved(1));
        $this->addressBook->method('getDefaultBilling')->willReturn($this->saved(2));
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->initialize($this->quote);

        $this->assertSame(1, $this->shipping->getCustomerAddressId());
        $this->assertSame(2, $this->billing->getCustomerAddressId());
        $this->assertSame(0, $this->shipping->getSameAsBilling());
        $this->assertTrue($this->shipping->getCollectShippingRates());
    }

    public function testNoDefaultAddressesLeavesNamesEmpty(): void
    {
        $this->addressBook->method('getDefaultShipping')->willReturn(null);
        $this->addressBook->method('getDefaultBilling')->willReturn(null);

        $this->service()->initialize($this->quote);

        $this->assertNull($this->shipping->getFirstname());
        $this->assertNull($this->billing->getFirstname());
    }

    public function testDefaultShippingOnlyFollowsDeliveryOntoBilling(): void
    {
        $this->addressBook->method('getDefaultShipping')->willReturn($this->saved(1));
        $this->addressBook->method('getDefaultBilling')->willReturn(null);

        $this->service()->initialize($this->quote);

        $this->assertSame(1, $this->shipping->getCustomerAddressId());
        $this->assertSame(1, $this->shipping->getSameAsBilling());
        $this->assertSame(1, $this->billing->getData('customer_address_id'));
        $this->assertSame('Saved', $this->billing->getData('firstname'));
    }

    public function testAQuoteThatIsUpToDateIsNotSaved(): void
    {
        $this->shipping->addData(['firstname' => 'Picked', 'same_as_billing' => 0]);
        $this->billing->addData(['firstname' => 'Billing']);
        $this->addressBook->expects($this->never())->method('getDefaultShipping');
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->initialize($this->quote);

        $this->assertSame('Picked', $this->shipping->getFirstname());
    }

    public function testFollowingBillingThatMatchesIsNotSavedOnEveryLoad(): void
    {
        $this->shipping->addData(['firstname' => 'Same', 'same_as_billing' => 1]);
        $this->billing->addData(['firstname' => 'Same']);
        foreach (['customer_address_id', 'lastname', 'company', 'street', 'city', 'region', 'region_id', 'postcode',
                     'country_id', 'telephone'] as $field) {
            $this->billing->setData($field, $this->shipping->getData($field));
        }
        $this->addressBook->method('getDefaultBilling')->willReturn(null);
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->initialize($this->quote);
    }

    public function testDefaultWithMissingDetailsIsNotPreselected(): void
    {
        $this->addressBook->method('getDefaultShipping')->willReturn($this->saved(1));
        $this->addressBook->method('getDefaultBilling')->willReturn(null);
        $this->validator->method('getErrors')->willReturn(['postcode' => 'Enter a 4-digit postcode.']);

        $this->service()->initialize($this->quote);

        $this->assertNull($this->shipping->getFirstname());
    }

    public function testAddressDeletedInTheAccountKeepsTheCopyAndDropsTheLink(): void
    {
        $this->shipping->addData(['firstname' => 'Copy', 'customer_address_id' => 5, 'same_as_billing' => 0]);
        $this->billing->addData(['firstname' => 'Billing']);
        $this->addressBook->method('getAddress')->willThrowException(new NoSuchEntityException(new Phrase('Gone')));
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->initialize($this->quote);

        $this->assertNull($this->shipping->getCustomerAddressId());
        $this->assertSame('Copy', $this->shipping->getFirstname());
    }

    public function testSavedAddressChangedInTheAccountIsRefreshed(): void
    {
        $this->shipping->addData(['firstname' => 'Old', 'customer_address_id' => 5, 'same_as_billing' => 0]);
        $this->billing->addData(['firstname' => 'Billing']);
        $this->addressBook->method('getAddress')->willReturn($this->saved(5));
        $this->cartRepository->expects($this->once())->method('save');

        $formatter = $this->createStub(AddressFormatter::class);
        $formatter->method('fromCustomerAddress')->willReturn(['line' => 'new']);
        $formatter->method('fromQuoteAddress')->willReturn(['line' => 'old']);

        $factory = $this->createStub(AddressFactory::class);
        $factory->method('create')->willReturnCallback(fn (): QuoteAddress => $this->quoteAddress());

        $service = new QuoteAddresses(
            $this->addressBook,
            $this->validator,
            $formatter,
            $factory,
            $this->createStub(RegionFactory::class),
            $this->cartRepository,
            $this->createStub(LoggerInterface::class),
            new ShippingRatesFreshness()
        );
        $service->initialize($this->quote);

        $this->assertSame('Saved', $this->shipping->getFirstname());
        $this->assertSame(5, $this->shipping->getCustomerAddressId());
    }

    public function testIncompleteSavedAddressIsRefused(): void
    {
        $this->addressBook->method('getAddress')->willReturn($this->saved(9));
        $this->validator->method('getErrors')->willReturn(['postcode' => 'Enter a 4-digit postcode.']);
        $this->cartRepository->expects($this->never())->method('save');

        try {
            $this->service()->selectShipping($this->quote, 9);
            $this->fail('Expected a CheckoutInputException');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteAddresses::SECTION, $exception->getSection());
            $this->assertStringContainsString('incomplete', strtolower((string) $exception->getMessage()));
        }
        $this->assertNull($this->shipping->getFirstname());
    }

    public function testFailureDoesNotCostTheCustomerThePage(): void
    {
        $this->addressBook->method('getDefaultShipping')->willThrowException(new \RuntimeException('Boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with($this->stringContains('Boom'));

        $this->service($logger)->initialize($this->quote);
    }

    public function testAddressOfAnotherCustomerIsRefused(): void
    {
        $this->addressBook->method('getAddress')->with(self::CUSTOMER_ID, 99)
            ->willThrowException(new NoSuchEntityException(new Phrase('Not yours')));
        $this->cartRepository->expects($this->never())->method('save');

        try {
            $this->service()->selectShipping($this->quote, 99);
            $this->fail('Expected a CheckoutInputException');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteAddresses::SECTION, $exception->getSection());
        }
        $this->assertNull($this->shipping->getFirstname());
    }

    public function testPickingACustomerBillingAddressStopsFollowingDelivery(): void
    {
        $this->shipping->addData(['firstname' => 'Delivery', 'same_as_billing' => 1]);
        $this->addressBook->method('getAddress')->willReturn($this->saved(3));
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->selectBilling($this->quote, 3);

        $this->assertSame(0, $this->shipping->getSameAsBilling());
        $this->assertSame(3, $this->billing->getCustomerAddressId());
    }

    public function testChangingDeliveryLeavesAPickedBillingAddressAlone(): void
    {
        $this->shipping->addData(['firstname' => 'Old', 'same_as_billing' => 0]);
        $this->billing->addData(['firstname' => 'Picked', 'customer_address_id' => 3]);
        $this->addressBook->method('getAddress')->willReturn($this->saved(1));

        $this->service()->selectShipping($this->quote, 1);

        $this->assertSame(1, $this->shipping->getCustomerAddressId());
        $this->assertSame('Picked', $this->billing->getFirstname());
        $this->assertTrue($this->shipping->getCollectShippingRates());
    }

    public function testInvalidFormChangesNothingAndCarriesTheFieldErrors(): void
    {
        $this->validator->method('getErrors')->willReturn(['postcode' => 'Enter a 4-digit postcode.']);
        $this->addressBook->expects($this->never())->method('add');
        $this->cartRepository->expects($this->never())->method('save');

        try {
            $this->service()->setNewShipping($this->quote, ['firstname' => 'New', 'postcode' => '12'], true);
            $this->fail('Expected a CheckoutInputException');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(['postcode' => 'Enter a 4-digit postcode.'], $exception->getFieldErrors());
        }
        $this->assertNull($this->shipping->getFirstname());
    }

    public function testNewAddressSavedToTheBookIsLinkedAndNotSavedAgainAtPlaceOrder(): void
    {
        $this->addressBook->expects($this->once())->method('add')->willReturn($this->saved(8));

        $this->service()->setNewShipping($this->quote, ['firstname' => ' New ', 'street_1' => '9 Road'], true);

        $this->assertSame(8, $this->shipping->getCustomerAddressId());
        $this->assertSame(0, $this->shipping->getSaveInAddressBook());
        $this->assertSame('New', $this->shipping->getFirstname());
        $this->assertSame('AU', $this->shipping->getCountryId());
    }

    public function testNewAddressNotSavedToTheBookHasNoLink(): void
    {
        $this->shipping->addData(['customer_address_id' => 4]);
        $this->addressBook->expects($this->never())->method('add');

        $this->service()->setNewShipping($this->quote, ['firstname' => 'Once'], false);

        $this->assertNull($this->shipping->getCustomerAddressId());
    }

    public function testSaveToBookIsRemovedWhenQuoteSaveFails(): void
    {
        $this->addressBook->expects($this->once())->method('add')->willReturn($this->saved(8));
        $this->addressBook->expects($this->once())->method('delete')->with(self::CUSTOMER_ID, 8);
        $this->cartRepository->method('save')->willThrowException(new \RuntimeException('Save failed'));

        try {
            $this->service()->setNewShipping($this->quote, ['firstname' => 'New', 'street_1' => '9 Road'], true);
            $this->fail('Expected the quote save failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Save failed', $exception->getMessage());
        }
    }

    private function service(?LoggerInterface $logger = null): QuoteAddresses
    {
        $factory = $this->createStub(AddressFactory::class);
        $factory->method('create')->willReturnCallback(fn (): QuoteAddress => $this->quoteAddress());

        return new QuoteAddresses(
            $this->addressBook,
            $this->validator,
            $this->formatterStub(),
            $factory,
            $this->createStub(RegionFactory::class),
            $this->cartRepository,
            $logger ?? $this->createStub(LoggerInterface::class),
            new ShippingRatesFreshness()
        );
    }

    private function formatterStub(): AddressFormatter
    {
        $formatter = $this->createStub(AddressFormatter::class);
        $formatter->method('fromCustomerAddress')->willReturn(['line' => 'same']);
        $formatter->method('fromQuoteAddress')->willReturn(['line' => 'same']);

        return $formatter;
    }

    private function saved(int $id): CustomerAddress
    {
        $saved = $this->createStub(CustomerAddress::class);
        $saved->method('getId')->willReturn($id);

        return $saved;
    }

    /**
     * An address whose import stands in for the core fieldset copy.
     */
    private function quoteAddress(): QuoteAddress
    {
        $address = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['importCustomerAddressData', 'setStreet', 'getStreet'])
            ->getMock();
        $address->method('importCustomerAddressData')
            ->willReturnCallback(fn () => $address->setData('firstname', 'Saved'));
        $address->method('setStreet')->willReturnSelf();
        $address->method('getStreet')->willReturn([]);

        return $address;
    }
}
