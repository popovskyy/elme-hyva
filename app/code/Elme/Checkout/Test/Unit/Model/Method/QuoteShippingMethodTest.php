<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Method;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Method\ShippingRatesFreshness;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Magento\Quote\Api\Data\ShippingMethodInterface;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
class QuoteShippingMethodTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private QuoteAddress&MockObject $shipping;
    private Quote&MockObject $quote;
    private ShippingInterface&MockObject $assignedShipping;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);

        $this->shipping = $this->getMockBuilder(QuoteAddress::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getGroupedAllShippingRates'])
            ->getMock();
        $this->shipping->setData('firstname', 'Shop');

        $this->quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getShippingAddress', 'getExtensionAttributes', 'collectTotals', 'getId'])
            ->getMock();
        $this->quote->method('getId')->willReturn(1);
        $this->quote->method('getShippingAddress')->willReturn($this->shipping);
        $this->quote->method('collectTotals')->willReturnSelf();

        $this->assignedShipping = $this->createMock(ShippingInterface::class);
        $assignment = $this->createStub(ShippingAssignmentInterface::class);
        $assignment->method('getShipping')->willReturn($this->assignedShipping);
        $extension = $this->createStub(CartExtensionInterface::class);
        $extension->method('getShippingAssignments')->willReturn([$assignment]);
        $this->quote->method('getExtensionAttributes')->willReturn($extension);
    }

    public function testASingleRateIsChosen(): void
    {
        $this->givenRates(['flatrate_flatrate']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('flatrate_flatrate', $this->shipping->getShippingMethod());
    }

    public function testFreshRatesSkipAskingTheCarriers(): void
    {
        $this->shipping->setShippingMethod('flatrate_flatrate');
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->quote->expects($this->never())->method('collectTotals');
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->refresh($this->quote, true);

        $this->assertSame('flatrate_flatrate', $this->shipping->getShippingMethod());
    }

    public function testSelectMarksRatesFreshSoALaterSaveDoesNotAskAgain(): void
    {
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $freshness = new ShippingRatesFreshness();

        $this->service(null, $freshness)->select($this->quote, 'freeshipping', 'freeshipping');

        $this->assertTrue($freshness->isFresh());
        $this->assertSame('freeshipping_freeshipping', $this->shipping->getShippingMethod());
    }

    public function testTwoRatesWaitForTheCustomer(): void
    {
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertEmpty($this->shipping->getShippingMethod());
    }

    public function testAChosenMethodStillOnOfferStays(): void
    {
        $this->shipping->setShippingMethod('freeshipping_freeshipping');
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('freeshipping_freeshipping', $this->shipping->getShippingMethod());
    }

    public function testAMethodThatIsGoneIsCleared(): void
    {
        $this->shipping->addData(['shipping_method' => 'gone_gone', 'shipping_description' => 'Gone - Gone']);
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('', $this->shipping->getShippingMethod());
        $this->assertSame('', $this->shipping->getShippingDescription());
    }

    public function testANewPriceOfTheChosenMethodIsSaved(): void
    {
        $this->shipping->addData(['shipping_method' => 'flatrate_flatrate', 'base_shipping_amount' => 10]);
        $this->givenRates(['flatrate_flatrate'], [], 20.0);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('flatrate_flatrate', $this->shipping->getShippingMethod());
    }

    /**
     * Core's save handler applies the assignment's method over the address, whatever the address says.
     */
    public function testClearingAlsoClearsTheShippingAssignment(): void
    {
        $this->shipping->setShippingMethod('gone_gone');
        $this->givenRates([]);
        $this->assignedShipping->expects($this->once())->method('setMethod')->with('');

        $this->service()->refresh($this->quote);
    }

    public function testSelectAlsoWritesTheShippingAssignment(): void
    {
        $this->shipping->setShippingMethod('flatrate_flatrate');
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->assignedShipping->expects($this->once())->method('setMethod')->with('freeshipping_freeshipping');

        $this->service()->select($this->quote, 'freeshipping', 'freeshipping');
    }

    public function testAMethodThatIsGoneGivesWayToASingleRate(): void
    {
        $this->shipping->setShippingMethod('gone_gone');
        $this->givenRates(['flatrate_flatrate']);

        $this->service()->refresh($this->quote);

        $this->assertSame('flatrate_flatrate', $this->shipping->getShippingMethod());
    }

    public function testNoRatesLeaveNothingChosen(): void
    {
        $this->shipping->setShippingMethod('gone_gone');
        $this->givenRates([]);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->refresh($this->quote);

        $this->assertSame('', $this->shipping->getShippingMethod());
    }

    public function testARateWithOnlyAnErrorIsNotOnOffer(): void
    {
        $this->givenRates(['flatrate_flatrate', 'broken_broken'], ['broken_broken']);

        $service = $this->service();

        $this->assertCount(1, $service->getRates($this->quote));
    }

    public function testNoAddressLocksAndClears(): void
    {
        $this->shipping->addData(['firstname' => null, 'shipping_method' => 'flatrate_flatrate']);
        $this->shipping->expects($this->never())->method('getGroupedAllShippingRates');

        $service = $this->service();
        $service->refresh($this->quote);

        $this->assertSame('', $this->shipping->getShippingMethod());
        $this->assertNotNull($service->getLockReason($this->quote));
    }

    public function testACarrierFailureIsLoggedNotThrown(): void
    {
        $this->shipping->method('getGroupedAllShippingRates')->willThrowException(new RuntimeException('Carrier down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->service($logger)->refresh($this->quote);
    }

    public function testSelectWritesARateOnOffer(): void
    {
        $this->givenRates(['flatrate_flatrate', 'freeshipping_freeshipping']);
        $this->cartRepository->expects($this->once())->method('save');

        $this->service()->select($this->quote, 'freeshipping', 'freeshipping');

        $this->assertSame('freeshipping_freeshipping', $this->shipping->getShippingMethod());
    }

    public function testSelectRefusesARateNotOnOffer(): void
    {
        $this->givenRates(['flatrate_flatrate']);
        $this->cartRepository->expects($this->never())->method('save');

        $this->expectException(CheckoutInputException::class);

        $this->service()->select($this->quote, 'tablerate', 'bestway');
    }

    public function testSelectRefusesWithoutAnAddress(): void
    {
        $this->shipping->setData('firstname', null);

        try {
            $this->service()->select($this->quote, 'flatrate', 'flatrate');
            $this->fail('Expected a refusal');
        } catch (CheckoutInputException $exception) {
            $this->assertSame(QuoteShippingMethod::SECTION, $exception->getSection());
        }
    }

    /**
     * @param string[] $codes
     * @param string[] $withError
     * @param float $price
     * @return void
     */
    private function givenRates(array $codes, array $withError = [], float $price = 0.0): void
    {
        $rates = [];
        foreach ($codes as $code) {
            [$carrier, $method] = explode('_', $code);
            $rates[$carrier][] = new DataObject([
                'carrier' => $carrier,
                'method' => $method,
                'error_message' => in_array($code, $withError, true) ? 'Not here' : '',
                'price' => $price,
            ]);
        }
        $this->shipping->method('getGroupedAllShippingRates')->willReturn($rates);
    }

    private function service(
        ?LoggerInterface $logger = null,
        ?ShippingRatesFreshness $ratesFreshness = null
    ): QuoteShippingMethod {
        $converter = $this->createStub(ShippingMethodConverter::class);
        $converter->method('modelToDataObject')->willReturnCallback(function (DataObject $rate) {
            $method = $this->createStub(ShippingMethodInterface::class);
            $method->method('getCarrierCode')->willReturn($rate->getData('carrier'));
            $method->method('getMethodCode')->willReturn($rate->getData('method'));
            $method->method('getAvailable')->willReturn(!$rate->getData('error_message'));
            $method->method('getBaseAmount')->willReturn($rate->getData('price'));

            return $method;
        });

        return new QuoteShippingMethod(
            $converter,
            $this->cartRepository,
            $logger ?? $this->createStub(LoggerInterface::class),
            $ratesFreshness ?? new ShippingRatesFreshness()
        );
    }
}
