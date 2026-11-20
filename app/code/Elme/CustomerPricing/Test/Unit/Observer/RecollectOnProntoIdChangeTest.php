<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Observer;

use Elme\CustomerPricing\Model\QuoteRecollect;
use Elme\CustomerPricing\Observer\RecollectOnProntoIdChange;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see RecollectOnProntoIdChange
 */
#[AllowMockObjectsWithoutExpectations]
class RecollectOnProntoIdChangeTest extends TestCase
{
    private QuoteRecollect&MockObject $quoteRecollect;
    private RecollectOnProntoIdChange $observer;

    protected function setUp(): void
    {
        $this->quoteRecollect = $this->createMock(QuoteRecollect::class);
        $this->observer = new RecollectOnProntoIdChange($this->quoteRecollect);
    }

    public function testChangedProntoIdFlagsTheCustomersCarts(): void
    {
        $this->quoteRecollect->expects($this->once())->method('byCustomerIds')->with([7]);

        $this->observer->execute($this->event($this->customer('C200'), $this->customer('C100')));
    }

    public function testRemovedProntoIdFlagsTheCustomersCarts(): void
    {
        $this->quoteRecollect->expects($this->once())->method('byCustomerIds')->with([7]);

        $this->observer->execute($this->event($this->customer(null), $this->customer('C100')));
    }

    public function testUnchangedProntoIdFlagsNothing(): void
    {
        $this->quoteRecollect->expects($this->never())->method('byCustomerIds');

        $this->observer->execute($this->event($this->customer('C100'), $this->customer(' C100')));
    }

    public function testNewCustomerFlagsNothing(): void
    {
        $this->quoteRecollect->expects($this->never())->method('byCustomerIds');

        $this->observer->execute($this->event($this->customer('C100'), null));
    }

    private function customer(?string $prontoId): CustomerInterface&MockObject
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getCustomAttribute')->willReturn(
            $prontoId === null ? null : (new AttributeValue())->setValue($prontoId)
        );

        return $customer;
    }

    private function event(CustomerInterface $customer, ?CustomerInterface $original): Observer
    {
        return new Observer(['event' => new Event([
            'customer_data_object' => $customer,
            'orig_customer_data_object' => $original,
        ])]);
    }
}
