<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\ViewModel;

use Elme\Checkout\Api\OrderDetailsInterface;
use Elme\Checkout\Model\Address\AddressFormatter;
use Elme\Checkout\ViewModel\OrderDetails;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use PHPUnit\Framework\TestCase;

class OrderDetailsTest extends TestCase
{
    public function testInstructionsComeFromTheOrderOnThePage(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getData')
            ->with(OrderDetailsInterface::DELIVERY_INSTRUCTIONS)
            ->willReturn('Leave at dock');

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_order')->willReturn($order);

        $viewModel = new OrderDetails(
            $registry,
            $this->createStub(CheckoutSession::class),
            new AddressFormatter()
        );

        $this->assertSame('Leave at dock', $viewModel->getDeliveryInstructions());
    }

    public function testShippingAddressUsesTheFormatter(): void
    {
        $address = $this->createStub(OrderAddress::class);
        $address->method('getFirstname')->willReturn('E2E');
        $address->method('getLastname')->willReturn('Checkout');
        $address->method('getCompany')->willReturn('Elme E2E');
        $address->method('getStreet')->willReturn(['1 Test Street']);
        $address->method('getCity')->willReturn('Melbourne');
        $address->method('getRegionCode')->willReturn('VIC');
        $address->method('getPostcode')->willReturn('3000');
        $address->method('getTelephone')->willReturn('0312345678');

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getShippingAddress')->willReturn($address);

        $session = $this->createStub(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn(null);

        $viewModel = new OrderDetails($registry, $session, new AddressFormatter());
        $shipping = $viewModel->getShippingAddress();

        $this->assertNotNull($shipping);
        $this->assertSame('E2E Checkout', $shipping['name']);
        $this->assertSame('1 Test Street', $shipping['street']);
        $this->assertSame('Melbourne VIC 3000', $shipping['locality']);
        $this->assertStringContainsString('1 Test Street', $shipping['line']);
    }

    public function testEmptyWhenThereIsNoOrder(): void
    {
        $session = $this->createStub(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn(null);

        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturn(null);

        $viewModel = new OrderDetails($registry, $session, new AddressFormatter());

        $this->assertSame('', $viewModel->getDeliveryInstructions());
        $this->assertNull($viewModel->getShippingAddress());
    }
}
