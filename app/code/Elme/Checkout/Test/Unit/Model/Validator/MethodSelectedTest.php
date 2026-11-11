<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Validator\DeliveryMethodSelected;
use Elme\Checkout\Model\Validator\PaymentMethodSelected;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class MethodSelectedTest extends TestCase
{
    /**
     * @param string[] $selected What the quote has before and after the refresh
     * @param float[] $prices The same for the saved delivery price
     */
    #[DataProvider('refusedDelivery')]
    public function testDeliveryIsRefused(array $selected, array $prices): void
    {
        $shippingMethod = $this->createMock(QuoteShippingMethod::class);
        $shippingMethod->expects($this->once())->method('refresh');
        $shippingMethod->method('getSelected')->willReturnOnConsecutiveCalls(...$selected);
        $shippingMethod->method('getSavedPrice')->willReturnOnConsecutiveCalls(...$prices);

        try {
            (new DeliveryMethodSelected($shippingMethod))->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteShippingMethod::SECTION, $exception->getSection());
        }
    }

    public static function refusedDelivery(): array
    {
        return [
            'nothing chosen' => [['', ''], [0.0, 0.0]],
            'carrier gone' => [['flatrate_flatrate', ''], [10.0, 0.0]],
            'other rate swapped in' => [['flatrate_flatrate', 'tablerate_bestway'], [10.0, 20.0]],
            'new price' => [['flatrate_flatrate', 'flatrate_flatrate'], [10.0, 15.0]],
        ];
    }

    /**
     * @param string[] $selected What the quote has before and after the refresh
     */
    #[DataProvider('refusedPayment')]
    public function testPaymentIsRefused(array $selected): void
    {
        $paymentMethod = $this->createMock(QuotePaymentMethod::class);
        $paymentMethod->expects($this->once())->method('refresh');
        $paymentMethod->method('getSelected')->willReturnOnConsecutiveCalls(...$selected);

        try {
            (new PaymentMethodSelected($paymentMethod))->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuotePaymentMethod::SECTION, $exception->getSection());
        }
    }

    public static function refusedPayment(): array
    {
        return [
            'nothing chosen' => [['', '']],
            'method gone' => [['checkmo', '']],
            'other method swapped in' => [['checkmo', 'banktransfer']],
        ];
    }

    #[DoesNotPerformAssertions]
    public function testChosenMethodsPass(): void
    {
        $shippingMethod = $this->createStub(QuoteShippingMethod::class);
        $shippingMethod->method('getSelected')->willReturn('flatrate_flatrate');
        $shippingMethod->method('getSavedPrice')->willReturn(10.0);
        $paymentMethod = $this->createStub(QuotePaymentMethod::class);
        $paymentMethod->method('getSelected')->willReturn('checkmo');

        (new DeliveryMethodSelected($shippingMethod))->validate($this->createStub(Quote::class));
        (new PaymentMethodSelected($paymentMethod))->validate($this->createStub(Quote::class));
    }

    public function testDeliveryValidatorAsksTheCarriersThroughRefresh(): void
    {
        $shippingMethod = $this->createMock(QuoteShippingMethod::class);
        $shippingMethod->method('getSelected')->willReturn('flatrate_flatrate');
        $shippingMethod->method('getSavedPrice')->willReturn(10.0);
        $shippingMethod->expects($this->once())->method('refresh')->with($this->isInstanceOf(Quote::class));

        (new DeliveryMethodSelected($shippingMethod))->validate($this->createStub(Quote::class));
    }
}
