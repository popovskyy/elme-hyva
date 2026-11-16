<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\SiblingOrder;
use PHPUnit\Framework\TestCase;

class SiblingOrderTest extends TestCase
{
    public function testSortOrderFirstThenSkuNaturalAndMembersWithoutSortLast(): void
    {
        $members = [
            5 => ['sku' => 'RILEY-10', 'sort' => null],
            6 => ['sku' => 'RILEY-2', 'sort' => null],
            7 => ['sku' => 'RILEY-Z', 'sort' => 2],
            8 => ['sku' => 'RILEY-A', 'sort' => 1],
        ];

        self::assertSame([8, 7, 6, 5], (new SiblingOrder())->sort($members));
    }

    public function testSameInputGivesSameOrderWhateverTheInputOrder(): void
    {
        $a = [1 => ['sku' => 'B', 'sort' => null], 2 => ['sku' => 'A', 'sort' => null]];
        $b = [2 => ['sku' => 'A', 'sort' => null], 1 => ['sku' => 'B', 'sort' => null]];

        self::assertSame((new SiblingOrder())->sort($a), (new SiblingOrder())->sort($b));
    }

    public function testEmptyFamily(): void
    {
        self::assertSame([], (new SiblingOrder())->sort([]));
    }
}
