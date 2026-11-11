<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\CheckoutManagement;
use Elme\Checkout\Model\Details\QuoteDetails;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\PlaceOrderValidatorPool;
use Elme\Checkout\Model\PlacedOrderRecovery;
use Elme\Checkout\Model\StateBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckoutManagementPlaceOrderTest extends TestCase
{
    private const CART_ID = 7;

    private LockManagerInterface&MockObject $lockManager;
    private CartManagementInterface&MockObject $cartManagement;
    private PlaceOrderValidatorPool&MockObject $validators;
    private PlacedOrderRecovery&MockObject $placedOrderRecovery;
    private Quote&MockObject $freshQuote;
    private CheckoutManagement $management;

    protected function setUp(): void
    {
        $this->lockManager = $this->createMock(LockManagerInterface::class);
        $this->cartManagement = $this->createMock(CartManagementInterface::class);
        $this->validators = $this->createMock(PlaceOrderValidatorPool::class);
        $this->placedOrderRecovery = $this->createMock(PlacedOrderRecovery::class);

        $this->freshQuote = $this->createMock(Quote::class);
        $this->freshQuote->method('loadActive')->with(self::CART_ID)->willReturnSelf();
        $quoteFactory = $this->createStub(QuoteFactory::class);
        $quoteFactory->method('create')->willReturn($this->freshQuote);

        $cartRepository = $this->createStub(CartRepositoryInterface::class);
        $cartRepository->method('getActive')->willReturn($this->createStub(Quote::class));

        $this->management = new CheckoutManagement(
            $cartRepository,
            $this->createStub(StateBuilder::class),
            $this->validators,
            $this->createStub(QuoteAddresses::class),
            $this->createStub(QuoteShippingMethod::class),
            $this->createStub(QuotePaymentMethod::class),
            $this->cartManagement,
            $this->lockManager,
            $quoteFactory,
            $this->createStub(QuoteDetails::class),
            $this->placedOrderRecovery
        );
    }

    public function testPlacesTheOrderAfterTheValidators(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock');
        $this->freshQuote->method('getId')->willReturn(self::CART_ID);
        $this->validators->expects($this->once())->method('validate')->with($this->anything(), ['agreements' => [1]]);
        $this->cartManagement->expects($this->once())->method('placeOrder')->with(self::CART_ID)->willReturn(42);

        $this->assertSame(42, $this->management->placeOrder(self::CART_ID, ['agreements' => [1]]));
    }

    public function testRequestThatFindsTheLockTakenIsRefused(): void
    {
        $this->lockManager->method('lock')->willReturn(false);
        $this->lockManager->expects($this->never())->method('unlock');
        $this->validators->expects($this->never())->method('validate');
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(LocalizedException::class);

        $this->management->placeOrder(self::CART_ID);
    }

    public function testQuoteThatIsAlreadyAnOrderIsRefused(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock');
        $this->freshQuote->method('getId')->willReturn(null);
        $this->placedOrderRecovery->method('getOrderIdForCart')->with(self::CART_ID)->willReturn(null);
        $this->validators->expects($this->never())->method('validate');
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(LocalizedException::class);

        $this->management->placeOrder(self::CART_ID);
    }

    public function testQuoteThatIsAlreadyAnOrderReturnsSessionOrderWhenItBelongsToTheCart(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock');
        $this->freshQuote->method('getId')->willReturn(null);
        $this->placedOrderRecovery->method('getOrderIdForCart')->with(self::CART_ID)->willReturn(42);
        $this->validators->expects($this->never())->method('validate');
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->assertSame(42, $this->management->placeOrder(self::CART_ID));
    }

    public function testFailedValidatorStopsTheOrderAndReleasesTheLock(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock');
        $this->freshQuote->method('getId')->willReturn(self::CART_ID);
        $this->validators->method('validate')->willThrowException(new CheckoutInputException(__('No')));
        $this->cartManagement->expects($this->never())->method('placeOrder');

        $this->expectException(CheckoutInputException::class);

        $this->management->placeOrder(self::CART_ID);
    }

    public function testFailedOrderReleasesTheLock(): void
    {
        $this->lockManager->method('lock')->willReturn(true);
        $this->lockManager->expects($this->once())->method('unlock');
        $this->freshQuote->method('getId')->willReturn(self::CART_ID);
        $this->cartManagement->method('placeOrder')->willThrowException(new LocalizedException(__('Out of stock')));

        $this->expectException(LocalizedException::class);

        $this->management->placeOrder(self::CART_ID);
    }
}
