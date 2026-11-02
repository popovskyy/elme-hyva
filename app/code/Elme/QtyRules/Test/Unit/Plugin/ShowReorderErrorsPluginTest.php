<?php

declare(strict_types=1);

namespace Elme\QtyRules\Test\Unit\Plugin;

use Elme\QtyRules\Plugin\ShowReorderErrorsPlugin;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Reorder\Data\Error;
use Magento\Sales\Model\Reorder\Data\ReorderOutput;
use Magento\Sales\Model\Reorder\Reorder;
use PHPUnit\Framework\TestCase;

/**
 * @see ShowReorderErrorsPlugin
 */
class ShowReorderErrorsPluginTest extends TestCase
{
    public function testEveryErrorBecomesAMessage(): void
    {
        $messages = $this->createMock(ManagerInterface::class);
        $messages->expects($this->once())->method('addErrorMessage')->with('This product is sold in multiples of 6');
        $output = new ReorderOutput(
            $this->createStub(CartInterface::class),
            [new Error('This product is sold in multiples of 6', 'UNDEFINED')]
        );

        $this->assertSame(
            $output,
            (new ShowReorderErrorsPlugin($messages))->afterExecute($this->createStub(Reorder::class), $output)
        );
    }
}
