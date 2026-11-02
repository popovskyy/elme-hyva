<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model\Details;

use Elme\Checkout\Exception\CheckoutInputException;
use Elme\Checkout\Model\Details\QuoteDetails;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteDetailsTest extends TestCase
{
    private CartRepositoryInterface&MockObject $cartRepository;
    private Quote&MockObject $quote;
    private DataObject $data;
    private QuoteDetails $details;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->details = new QuoteDetails($this->cartRepository);

        $this->data = new DataObject();
        $this->quote = $this->createMock(Quote::class);
        $this->quote->method('getData')->willReturnCallback(fn (string $key) => $this->data->getData($key));
        $this->quote->method('setData')->willReturnCallback(function (string $key, $value) {
            $this->data->setData($key, $value);
            return $this->quote;
        });
    }

    public function testSavesTrimmedValue(): void
    {
        $this->cartRepository->expects($this->once())->method('save')->with($this->quote);

        $this->details->save($this->quote, ['delivery_instructions' => " Front door\r\nRing twice "]);

        $this->assertSame("Front door\nRing twice", $this->data->getData('delivery_instructions'));
    }

    public function testEmptyValueClearsTheField(): void
    {
        $this->data->setData('delivery_instructions', 'Front door');

        $this->details->save($this->quote, ['delivery_instructions' => '   ']);

        $this->assertNull($this->data->getData('delivery_instructions'));
    }

    public function testNothingChangedIsNotSaved(): void
    {
        $this->data->setData('delivery_instructions', 'Front door');
        $this->cartRepository->expects($this->never())->method('save');

        $this->details->save($this->quote, ['delivery_instructions' => 'Front door']);
    }

    public function testFieldNotGivenStaysAsItIs(): void
    {
        $this->data->setData('delivery_instructions', 'Front door');
        $this->cartRepository->expects($this->never())->method('save');

        $this->details->save($this->quote, []);

        $this->assertSame('Front door', $this->data->getData('delivery_instructions'));
    }

    public function testValueThatIsNotTextIsIgnored(): void
    {
        $this->cartRepository->expects($this->never())->method('save');

        $this->details->save($this->quote, ['delivery_instructions' => ['Front door']]);
    }

    public function testLengthCountsCharactersNotBytes(): void
    {
        $this->details->save($this->quote, ['delivery_instructions' => str_repeat("\u{00e9}", 250)]);

        $this->assertSame(str_repeat("\u{00e9}", 250), $this->data->getData('delivery_instructions'));
    }

    public function testTooLongInstructionsAreRefused(): void
    {
        $this->cartRepository->expects($this->never())->method('save');

        try {
            $this->details->save($this->quote, ['delivery_instructions' => str_repeat('x', 251)]);
            $this->fail('Instructions of 251 characters must be refused');
        } catch (CheckoutInputException $exception) {
            $this->assertSame('details', $exception->getSection());
            $this->assertSame(['delivery_instructions'], array_keys($exception->getFieldErrors()));
        }

        $this->assertNull($this->data->getData('delivery_instructions'));
    }
}
