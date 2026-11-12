<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Observer;

use Elme\Checkout\Observer\AddDetailsToOrderEmail;
use Elme\Checkout\Observer\CopyDetailsToOrder;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class OrderDetailsObserversTest extends TestCase
{
    public function testOrderTakesTheDetailsOfTheQuote(): void
    {
        $order = new DataObject();
        $quote = new DataObject(['delivery_instructions' => 'Front door']);
        $observer = new Observer(['event' => new Event(['order' => $order, 'quote' => $quote])]);

        (new CopyDetailsToOrder())->execute($observer);

        $this->assertSame('Front door', $order->getData('delivery_instructions'));
    }

    public function testEmailGetsTheDetailsOfTheOrder(): void
    {
        $order = new DataObject(['delivery_instructions' => 'Front door']);
        $transport = new DataObject(['order' => $order]);

        (new AddDetailsToOrderEmail())->execute(new Observer(['transportObject' => $transport]));

        $this->assertSame('Front door', $transport->getData('delivery_instructions'));
    }

    public function testEmailGetsEmptyTextWhenTheOrderHasNoDetails(): void
    {
        $transport = new DataObject(['order' => new DataObject()]);

        (new AddDetailsToOrderEmail())->execute(new Observer(['transportObject' => $transport]));

        $this->assertSame('', $transport->getData('delivery_instructions'));
    }
}
