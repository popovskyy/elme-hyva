<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Model;

use Elme\Search\Model\SkuQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkuQueryTest extends TestCase
{
    #[DataProvider('looksLikeSkuProvider')]
    public function testLooksLikeSku(string $value, bool $expected): void
    {
        $this->assertSame($expected, SkuQuery::looksLikeSku($value));
    }

    public static function looksLikeSkuProvider(): array
    {
        return [
            'dotted' => ['VG.228.ML', true],
            'digit prefix' => ['218AREDIN', true],
            'hash' => ['107BRE#', true],
            'Z-family' => ['Z-ARDTBL', true],
            'Z-prefix' => ['Z-ARD', true],
            'hyphenated word' => ['l-shaped', true],
            'digit hyphen word' => ['3-seater', true],
            'plain word' => ['vase', false],
            'two words' => ['dining table', false],
            'empty' => ['', false],
            'one letter' => ['Z', false],
        ];
    }

    public function testCompactStripsPunctuation(): void
    {
        $this->assertSame('zardtbl', SkuQuery::compact('Z-ARDTBL'));
        $this->assertSame('vg228ml', SkuQuery::compact('VG.228.ML'));
        $this->assertSame('218aredinw210b', SkuQuery::compact('218AREDINW210B#'));
        $this->assertSame('lshaped', SkuQuery::compact('l-shaped'));
    }
}
