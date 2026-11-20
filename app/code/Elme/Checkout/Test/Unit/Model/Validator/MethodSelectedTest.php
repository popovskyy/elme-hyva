<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Validator;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Validator\DeliveryMethodSelected;
use Elme\Checkout\Model\Validator\PaymentMethodSelected;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class MethodSelectedTest extends TestCase
{
    /**
     * @param string[] $selected What the quote has before and after the refresh
     * @param float[] $prices The same for the saved delivery price
     * @param string $message
     */
    #[DataProvider('refusedDelivery')]
    public function testDeliveryIsRefused(array $selected, array $prices, string $message): void
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
            $this->assertSame($message, (string) $exception->getMessage());
        }
    }

    public static function refusedDelivery(): array
    {
        return [
            'nothing chosen' => [
                ['', ''],
                [0.0, 0.0],
                'Please choose a delivery method.',
            ],
            'carrier gone' => [
                ['flatrate_flatrate', ''],
                [10.0, 0.0],
                'Your delivery method is no longer available. Please choose another one.',
            ],
            'other rate swapped in' => [
                ['flatrate_flatrate', 'tablerate_bestway'],
                [10.0, 20.0],
                'Your delivery options have changed. Please check them and place your order again.',
            ],
            'new price' => [
                ['flatrate_flatrate', 'flatrate_flatrate'],
                [10.0, 15.0],
                'Your delivery options have changed. Please check them and place your order again.',
            ],
        ];
    }

    /**
     * @param string[] $selected What the quote has before and after the refresh
     * @param string $message
     */
    #[DataProvider('refusedPayment')]
    public function testPaymentIsRefused(array $selected, string $message): void
    {
        $paymentMethod = $this->createMock(QuotePaymentMethod::class);
        $paymentMethod->expects($this->once())->method('refresh');
        $paymentMethod->method('getSelected')->willReturnOnConsecutiveCalls(...$selected);

        try {
            (new PaymentMethodSelected($paymentMethod))->validate($this->createStub(Quote::class));
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuotePaymentMethod::SECTION, $exception->getSection());
            $this->assertSame($message, (string) $exception->getMessage());
        }
    }

    public static function refusedPayment(): array
    {
        return [
            'nothing chosen' => [
                ['', ''],
                'Please choose a payment method.',
            ],
            'method gone' => [
                ['checkmo', ''],
                'Your payment method is no longer available. Please choose another one.',
            ],
            'other method swapped in' => [
                ['checkmo', 'banktransfer'],
                'Your payment options have changed. Please check them and place your order again.',
            ],
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
