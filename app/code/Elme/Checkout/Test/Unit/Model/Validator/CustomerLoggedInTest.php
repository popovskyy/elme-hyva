<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Validator\CustomerLoggedIn;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class CustomerLoggedInTest extends TestCase
{
    public function testGuestIsRefused(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $quote = $this->createStub(Quote::class);
        $quote->method('getCustomer')->willReturn($customer);
        $quote->method('getCustomerIsGuest')->willReturn(true);

        try {
            (new CustomerLoggedIn())->validate($quote);
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame('Please login to access this page.', (string) $exception->getMessage());
        }
    }

    public function testMissingCustomerIdIsRefused(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn(null);
        $quote = $this->createStub(Quote::class);
        $quote->method('getCustomer')->willReturn($customer);
        $quote->method('getCustomerIsGuest')->willReturn(false);

        try {
            (new CustomerLoggedIn())->validate($quote);
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame('Please login to access this page.', (string) $exception->getMessage());
        }
    }

    public function testLoggedInCustomerPasses(): void
    {
        $customer = $this->createStub(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $quote = $this->createStub(Quote::class);
        $quote->method('getCustomer')->willReturn($customer);
        $quote->method('getCustomerIsGuest')->willReturn(false);

        (new CustomerLoggedIn())->validate($quote);
        $this->addToAssertionCount(1);
    }
}
