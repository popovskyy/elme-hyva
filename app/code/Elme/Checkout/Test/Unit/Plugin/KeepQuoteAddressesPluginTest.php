<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Plugin;

use Elme\Checkout\Plugin\KeepQuoteAddressesPlugin;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class KeepQuoteAddressesPluginTest extends TestCase
{
    public function testAddressPickedEarlierSurvivesInitCheckout(): void
    {
        $shipping = $this->address(['firstname' => 'Picked', 'customer_address_id' => 2]);
        $billing = $this->address(['firstname' => 'Billing', 'customer_address_id' => 3]);

        $quote = $this->createStub(Quote::class);
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getBillingAddress')->willReturn($billing);

        $onepage = $this->createStub(Onepage::class);
        $onepage->method('getQuote')->willReturn($quote);

        $result = (new KeepQuoteAddressesPlugin())->aroundInitCheckout(
            $onepage,
            static function () use ($onepage, $shipping, $billing): Onepage {
                // What core's assignCustomer() does
                $shipping->addData(['firstname' => 'Default', 'customer_address_id' => 1]);
                $billing->addData(['firstname' => 'Default', 'customer_address_id' => 1]);

                return $onepage;
            }
        );

        $this->assertSame($onepage, $result);
        $this->assertSame(['firstname' => 'Picked', 'customer_address_id' => 2], $shipping->getData());
        $this->assertSame(['firstname' => 'Billing', 'customer_address_id' => 3], $billing->getData());
    }

    /**
     * @param mixed[] $data
     * @return Address
     */
    private function address(array $data): Address
    {
        $address = $this->getMockBuilder(Address::class)->disableOriginalConstructor()->onlyMethods([])->getMock();

        return $address->setData($data);
    }
}
