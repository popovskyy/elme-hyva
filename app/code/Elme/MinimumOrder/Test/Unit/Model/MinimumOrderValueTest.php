<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Test\Unit\Model;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MinimumOrderValueTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private PriceCurrencyInterface&MockObject $priceCurrency;
    private MinimumOrderValue $service;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->priceCurrency->method('format')->willReturnCallback(
            static fn (float $amount): string => '$' . number_format($amount, 2)
        );

        $this->service = new MinimumOrderValue(
            $this->scopeConfig,
            $this->priceCurrency,
            $this->createStub(CustomerSession::class),
            $this->createStub(CheckoutSession::class)
        );
    }

    public function testRemainingWhenBelowCustomerMinimum(): void
    {
        $this->enableWithConfigAmount(500.0);
        $customer = $this->customerWithMinimum(500.0);

        $status = $this->service->evaluate(420.0, $customer);

        $this->assertTrue($status['enabled']);
        $this->assertSame(500.0, $status['minimum']);
        $this->assertSame(420.0, $status['subtotal']);
        $this->assertSame(80.0, $status['remaining']);
        $this->assertFalse($status['met']);
        $this->assertSame('$80.00', $status['remaining_formatted']);
    }

    public function testMetWhenSubtotalEqualsMinimum(): void
    {
        $this->enableWithConfigAmount(500.0);
        $customer = $this->customerWithMinimum(500.0);

        $status = $this->service->evaluate(500.0, $customer);

        $this->assertSame(0.0, $status['remaining']);
        $this->assertTrue($status['met']);
    }

    public function testMetWhenSubtotalAboveMinimum(): void
    {
        $this->enableWithConfigAmount(500.0);
        $customer = $this->customerWithMinimum(500.0);

        $status = $this->service->evaluate(620.0, $customer);

        $this->assertSame(0.0, $status['remaining']);
        $this->assertTrue($status['met']);
    }

    public function testEmptyAttributeUsesConfigDefault(): void
    {
        $this->enableWithConfigAmount(500.0);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getCustomAttribute')->with('minimum_order_value')->willReturn(null);

        $status = $this->service->evaluate(420.0, $customer);

        $this->assertSame(500.0, $status['minimum']);
        $this->assertSame(80.0, $status['remaining']);
        $this->assertFalse($status['met']);
    }

    public function testBlankAttributeUsesConfigDefault(): void
    {
        $this->enableWithConfigAmount(750.0);
        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getValue')->willReturn('');
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getCustomAttribute')->willReturn($attribute);

        $this->assertSame(750.0, $this->service->getMinimum($customer));
    }

    public function testDisabledMeansMetWithZeroRemaining(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('sales/minimum_order/active', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $status = $this->service->evaluate(10.0, $this->customerWithMinimum(500.0));

        $this->assertFalse($status['enabled']);
        $this->assertSame(0.0, $status['minimum']);
        $this->assertSame(0.0, $status['remaining']);
        $this->assertTrue($status['met']);
    }

    public function testEvaluateQuoteUsesBaseSubtotal(): void
    {
        $this->enableWithConfigAmount(500.0);
        $customer = $this->customerWithMinimum(500.0);
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomer', 'getStoreId', 'getData'])
            ->getMock();
        $quote->method('getData')->with('base_subtotal')->willReturn(420.0);
        $quote->method('getCustomer')->willReturn($customer);
        $quote->method('getStoreId')->willReturn(1);

        $status = $this->service->evaluateQuote($quote);

        $this->assertSame(80.0, $status['remaining']);
        $this->assertFalse($status['met']);
    }

    private function enableWithConfigAmount(float $amount): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('sales/minimum_order/active', ScopeInterface::SCOPE_STORE, $this->anything())
            ->willReturn(true);
        $this->scopeConfig->method('getValue')
            ->with('sales/minimum_order/amount', ScopeInterface::SCOPE_STORE, $this->anything())
            ->willReturn($amount);
    }

    private function customerWithMinimum(float $amount): CustomerInterface
    {
        $attribute = $this->createMock(AttributeInterface::class);
        $attribute->method('getValue')->willReturn($amount);
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getCustomAttribute')
            ->with(MinimumOrderValue::ATTRIBUTE_CODE)
            ->willReturn($attribute);

        return $customer;
    }
}
