<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Method;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Magento\Framework\Phrase;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\PaymentMethodManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class QuotePaymentMethodTest extends TestCase
{
    private PaymentMethodManagementInterface&MockObject $management;
    private QuoteShippingMethod&MockObject $shippingMethod;
    private CartRepositoryInterface&MockObject $cartRepository;
    private Payment&MockObject $payment;
    private QuoteAddress&MockObject $shipping;
    private Quote&MockObject $quote;

    protected function setUp(): void
    {
        $this->management = $this->createMock(PaymentMethodManagementInterface::class);
        $this->shippingMethod = $this->createMock(QuoteShippingMethod::class);
        $this->shippingMethod->method('getSelected')->willReturn('flatrate_flatrate');
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);

        $this->payment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['importData'])
            ->getMock();
        $this->payment->method('importData')->willReturnCallback(
            fn (array $data) => $this->payment->setData('method', $data['method'])
        );

        $this->shipping = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPayment', 'getShippingAddress', 'collectTotals', 'getId'])
            ->getMock();
        $this->quote->method('getId')->willReturn(1);
        $this->quote->method('getPayment')->willReturn($this->payment);
        $this->quote->method('getShippingAddress')->willReturn($this->shipping);
        $this->quote->method('collectTotals')->willReturnSelf();
    }

    public function testASingleMethodIsChosen(): void
    {
        $this->givenMethods(['checkmo']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('checkmo', $this->payment->getMethod());
        $this->assertSame('checkmo', $this->shipping->getPaymentMethod());
    }

    public function testTwoMethodsWaitForTheCustomer(): void
    {
        $this->givenMethods(['checkmo', 'banktransfer']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertNull($this->payment->getMethod());
    }

    public function testAChosenMethodStillOnOfferStays(): void
    {
        $this->payment->setData('method', 'banktransfer');
        $this->givenMethods(['checkmo', 'banktransfer']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('banktransfer', $this->payment->getMethod());
    }

    public function testAMethodThatIsGoneIsCleared(): void
    {
        $this->payment->setData('method', 'gone');
        $this->givenMethods(['checkmo', 'banktransfer']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertNull($this->payment->getMethod());
    }

    public function testALockedSectionKeepsItsChoice(): void
    {
        $this->shippingMethod->method('getLockReason')->willReturn(new Phrase('Add a delivery address first'));
        $this->payment->setData('method', 'banktransfer');
        $this->management->expects($this->never())->method('getList');

        $this->service()->refresh($this->quote);

        $this->assertSame('banktransfer', $this->payment->getMethod());
    }

    public function testNoDeliveryMethodLocksTheSection(): void
    {
        $shippingMethod = $this->createStub(QuoteShippingMethod::class);
        $shippingMethod->method('getSelected')->willReturn('');

        $service = new QuotePaymentMethod(
            $this->management,
            $shippingMethod,
            $this->cartRepository,
            $this->createStub(LoggerInterface::class)
        );

        $this->assertNotNull($service->getLockReason($this->quote));
    }

    public function testAMethodListFailureIsLoggedNotThrown(): void
    {
        $this->management->method('getList')->willThrowException(new RuntimeException('Broken'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->service($logger)->refresh($this->quote);
    }

    public function testSelectWritesAMethodOnOffer(): void
    {
        $this->givenMethods(['checkmo', 'banktransfer']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->select($this->quote, 'banktransfer');

        $this->assertSame('banktransfer', $this->payment->getMethod());
    }

    public function testSelectRefusesAMethodNotOnOffer(): void
    {
        $this->givenMethods(['checkmo']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CheckoutInputException::class);

        $this->service()->select($this->quote, 'banktransfer');
    }

    public function testSelectRefusesWhileLocked(): void
    {
        $this->shippingMethod->method('getLockReason')->willReturn(new Phrase('Add a delivery address first'));

        try {
            $this->service()->select($this->quote, 'checkmo');
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuotePaymentMethod::SECTION, $exception->getSection());
        }
    }

    /**
     * @param string[] $codes
     * @return void
     */
    private function givenMethods(array $codes): void
    {
        $this->management->method('getList')->willReturn(array_map(function (string $code) {
            $method = $this->createStub(MethodInterface::class);
            $method->method('getCode')->willReturn($code);

            return $method;
        }, $codes));
    }

    private function service(?LoggerInterface $logger = null): QuotePaymentMethod
    {
        return new QuotePaymentMethod(
            $this->management,
            $this->shippingMethod,
            $this->cartRepository,
            $logger ?? $this->createStub(LoggerInterface::class)
        );
    }
}
