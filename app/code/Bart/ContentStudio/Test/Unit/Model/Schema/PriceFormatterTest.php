<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\PriceFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the `$3,999` mask shared with `view/base/web/js/editor/lib/price.js`.
 *
 * The storefront prints whatever this returns; the admin input and live
 * preview print whatever that JS returns. A mismatch here is a visible
 * identity break, not a cosmetic drift.
 *
 * @see PriceFormatter
 */
class PriceFormatterTest extends TestCase
{
    /**
     * @param mixed $input
     * @param string $expected
     */
    #[DataProvider('formatProvider')]
    public function testFormat(mixed $input, string $expected): void
    {
        $this->assertSame($expected, PriceFormatter::format($input));
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function formatProvider(): array
    {
        return [
            'empty string' => ['', ''],
            'null' => [null, ''],
            'non-scalar' => [['3999'], ''],
            'plain thousands' => ['3999', '$3,999'],
            'already masked' => ['$3,999', '$3,999'],
            'cents on a whole dollar' => ['3999.00', '$3,999'],
            'masked with cents' => ['$3,999.00', '$3,999'],
            'one decimal place' => ['12.5', '$12'],
            'comma as decimal' => ['3999,00', '$3,999'],
            'leading zeros' => ['000', '$0'],
            'zero' => ['0', '$0'],
            'dollar only' => ['$', ''],
            'letters only' => ['abc', ''],
            'A$ prefix is not kept' => ['A$3,999', '$3,999'],
            'integer' => [3999, '$3,999'],
            // A masked value with its last digit just deleted. The comma is a
            // thousands separator mid-edit, not a decimal point, so the group
            // must survive - this used to collapse `$1,250` to `$1` on one
            // Backspace.
            'thousands group half-deleted' => ['$1,25', '$125'],
            'thousands group down to one digit' => ['$1,2', '$12'],
            'four-figure group half-deleted' => ['$12,34', '$1,234'],
            'second group half-deleted' => ['$1,234,56', '$123,456'],
            'masked value is untouched' => ['$1,250', '$1,250'],
        ];
    }
}
