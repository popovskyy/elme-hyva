<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\CheckoutManagement;
use Elme\Checkout\Model\Details\QuoteDetails;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Method\ShippingRatesFreshness;
use Elme\Checkout\Model\PlaceOrderValidatorPool;
use Elme\Checkout\Model\PlacedOrderRecovery;
use Elme\Checkout\Model\StateBuilder;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CheckoutManagementRatesFreshTest extends TestCase
{
    private const CART_ID = 11;

    private Quote&MockObject $quote;
    private QuoteShippingMethod&MockObject $shippingMethod;
    private QuoteAddresses&MockObject $quoteAddresses;
    private CheckoutManagement $management;

    protected function setUp(): void
    {
        $this->quote = $this->createMock(Quote::class);
        $cartRepository = $this->createStub(CartRepositoryInterface::class);
        $cartRepository->method('getActive')->willReturn($this->quote);

        $this->shippingMethod = $this->createMock(QuoteShippingMethod::class);
        $this->quoteAddresses = $this->createMock(QuoteAddresses::class);

        $state = $this->createStub(CheckoutStateInterface::class);
        $stateBuilder = $this->createStub(StateBuilder::class);
        $stateBuilder->method('build')->willReturn($state);

        $this->management = new CheckoutManagement(
            $cartRepository,
            $stateBuilder,
            $this->createStub(PlaceOrderValidatorPool::class),
            $this->quoteAddresses,
            $this->shippingMethod,
            $this->createStub(QuotePaymentMethod::class),
            $this->createStub(CartManagementInterface::class),
            $this->createStub(LockManagerInterface::class),
            $this->createStub(QuoteFactory::class),
            $this->createStub(QuoteDetails::class),
            $this->createStub(PlacedOrderRecovery::class),
            new ShippingRatesFreshness()
        );
    }

    public function testPageLoadAsksTheCarriers(): void
    {
        $this->quoteAddresses->expects($this->once())->method('initialize')->with($this->quote);
        $this->shippingMethod->expects($this->once())->method('refresh')->with($this->quote, false);

        $this->management->getState(self::CART_ID);
    }

    public function testAddressChangeReusesRates(): void
    {
        $this->quoteAddresses->expects($this->once())->method('selectShipping')->with($this->quote, 5);
        $this->shippingMethod->expects($this->once())->method('refresh')->with($this->quote, true);

        $this->management->setShippingAddress(self::CART_ID, 5);
    }

    public function testDeliveryPickReusesRates(): void
    {
        $this->shippingMethod->expects($this->once())->method('select')->with($this->quote, 'flatrate', 'flatrate');
        $this->shippingMethod->expects($this->once())->method('refresh')->with($this->quote, true);

        $this->management->setShippingMethod(self::CART_ID, 'flatrate', 'flatrate');
    }

    public function testPaymentPickReusesRates(): void
    {
        $paymentMethod = $this->createMock(QuotePaymentMethod::class);
        $paymentMethod->expects($this->once())->method('select')->with($this->quote, 'checkmo');

        $cartRepository = $this->createStub(CartRepositoryInterface::class);
        $cartRepository->method('getActive')->willReturn($this->quote);
        $stateBuilder = $this->createStub(StateBuilder::class);
        $stateBuilder->method('build')->willReturn($this->createStub(CheckoutStateInterface::class));

        $management = new CheckoutManagement(
            $cartRepository,
            $stateBuilder,
            $this->createStub(PlaceOrderValidatorPool::class),
            $this->createStub(QuoteAddresses::class),
            $this->shippingMethod,
            $paymentMethod,
            $this->createStub(CartManagementInterface::class),
            $this->createStub(LockManagerInterface::class),
            $this->createStub(QuoteFactory::class),
            $this->createStub(QuoteDetails::class),
            $this->createStub(PlacedOrderRecovery::class),
            new ShippingRatesFreshness()
        );

        $this->shippingMethod->expects($this->once())->method('refresh')->with($this->quote, true);

        $management->setPaymentMethod(self::CART_ID, 'checkmo');
    }
}
