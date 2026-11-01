<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Validator\DeliveryMethodSelected;
use Elme\Checkout\Model\Validator\PaymentMethodSelected;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class MethodSelectedTest extends TestCase
{
    public function testNoDeliveryMethodBlocksTheOrder(): void
    {
        $shippingMethod = $this->createMock(QuoteShippingMethod::class);
        $shippingMethod->expects($this->once())->method('refresh');
        $shippingMethod->method('getSelected')->willReturn('');

        try {
            (new DeliveryMethodSelected($shippingMethod))->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteShippingMethod::SECTION, $exception->getSection());
        }
    }

    public function testNoPaymentMethodBlocksTheOrder(): void
    {
        $paymentMethod = $this->createMock(QuotePaymentMethod::class);
        $paymentMethod->expects($this->once())->method('refresh');
        $paymentMethod->method('getSelected')->willReturn('');

        try {
            (new PaymentMethodSelected($paymentMethod))->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuotePaymentMethod::SECTION, $exception->getSection());
        }
    }

    #[DoesNotPerformAssertions]
    public function testChosenMethodsPass(): void
    {
        $shippingMethod = $this->createStub(QuoteShippingMethod::class);
        $shippingMethod->method('getSelected')->willReturn('flatrate_flatrate');
        $paymentMethod = $this->createStub(QuotePaymentMethod::class);
        $paymentMethod->method('getSelected')->willReturn('checkmo');

        (new DeliveryMethodSelected($shippingMethod))->validate($this->createStub(Quote::class));
        (new PaymentMethodSelected($paymentMethod))->validate($this->createStub(Quote::class));
    }
}
