<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\MinimumOrder\Model\MinimumOrderValue as MinimumOrderValueService;
use Elme\MinimumOrder\Model\Validator\MinimumOrderValue;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MinimumOrderValueTest extends TestCase
{
    public function testBelowMinimumIsRefusedWithRemainingAmount(): void
    {
        $service = $this->createMock(MinimumOrderValueService::class);
        $service->method('evaluateQuote')->willReturn([
            'enabled' => true,
            'minimum' => 500.0,
            'subtotal' => 420.0,
            'remaining' => 80.0,
            'met' => false,
            'minimum_formatted' => '$500.00',
            'subtotal_formatted' => '$420.00',
            'remaining_formatted' => '$80.00',
        ]);

        $quote = $this->createStub(Quote::class);

        try {
            (new MinimumOrderValue($service))->validate($quote);
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame('Add $80.00 more to place your order.', (string) $exception->getMessage());
        }
    }

    public function testMetAllowsPlaceOrder(): void
    {
        $service = $this->createMock(MinimumOrderValueService::class);
        $service->method('evaluateQuote')->willReturn([
            'enabled' => true,
            'minimum' => 500.0,
            'subtotal' => 500.0,
            'remaining' => 0.0,
            'met' => true,
            'minimum_formatted' => '$500.00',
            'subtotal_formatted' => '$500.00',
            'remaining_formatted' => '$0.00',
        ]);

        (new MinimumOrderValue($service))->validate($this->createStub(Quote::class));
        $this->addToAssertionCount(1);
    }
}
