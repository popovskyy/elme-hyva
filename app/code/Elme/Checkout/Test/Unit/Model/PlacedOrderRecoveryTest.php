<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model;

use Elme\Checkout\Model\PlacedOrderRecovery;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlacedOrderRecoveryTest extends TestCase
{
    private CheckoutSession&MockObject $checkoutSession;
    private CustomerSession&MockObject $customerSession;
    private SuccessValidator&MockObject $successValidator;
    private UrlInterface&MockObject $urlBuilder;
    private PlacedOrderRecovery $recovery;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->successValidator = $this->createMock(SuccessValidator::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->recovery = new PlacedOrderRecovery(
            $this->checkoutSession,
            $this->customerSession,
            $this->successValidator,
            $this->urlBuilder
        );
    }

    public function testSuccessUrlWhenSessionHoldsThisCustomersOrder(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(9);
        $this->successValidator->method('isValid')->willReturn(true);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getCustomerId')->willReturn(9);
        $order->method('getQuoteId')->willReturn(7);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);

        $this->urlBuilder->method('getUrl')->with('checkout/onepage/success')->willReturn(
            'https://elme.test/checkout/onepage/success/'
        );

        $this->assertSame('https://elme.test/checkout/onepage/success/', $this->recovery->getSuccessUrl());
        $this->assertSame(42, $this->recovery->getOrderIdForCart(7));
        $this->assertNull($this->recovery->getOrderIdForCart(8));
    }

    public function testNoSuccessUrlWhenSessionHasNoValidOrder(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->successValidator->method('isValid')->willReturn(false);

        $this->assertNull($this->recovery->getSuccessUrl());
        $this->assertNull($this->recovery->getOrderIdForCart(7));
    }

    public function testNoSuccessUrlWhenOrderBelongsToAnotherCustomer(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $this->customerSession->method('getCustomerId')->willReturn(9);
        $this->successValidator->method('isValid')->willReturn(true);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getCustomerId')->willReturn(3);
        $this->checkoutSession->method('getLastRealOrder')->willReturn($order);

        $this->assertNull($this->recovery->getSuccessUrl());
    }
}
