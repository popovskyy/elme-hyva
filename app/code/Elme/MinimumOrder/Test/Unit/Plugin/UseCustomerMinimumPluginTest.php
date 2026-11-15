<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Test\Unit\Plugin;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Elme\MinimumOrder\Plugin\UseCustomerMinimumPlugin;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UseCustomerMinimumPluginTest extends TestCase
{
    /**
     * @return array<string, array{bool}>
     */
    public static function answers(): array
    {
        return ['met' => [true], 'not met' => [false]];
    }

    #[DataProvider('answers')]
    public function testCoreGetsTheCustomersAnswerNotTheStoreDefault(bool $met): void
    {
        $quote = $this->createStub(Quote::class);
        $service = $this->createMock(MinimumOrderValue::class);
        $service->expects($this->once())->method('evaluateQuote')->with($quote)->willReturn(['met' => $met]);
        $core = function (): bool {
            $this->fail('The store default must not be asked.');
        };

        $this->assertSame($met, (new UseCustomerMinimumPlugin($service))->aroundValidateMinimumAmount($quote, $core));
    }
}
