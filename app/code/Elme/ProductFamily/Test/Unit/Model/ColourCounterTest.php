<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\ColourCounter;
use PHPUnit\Framework\TestCase;

class ColourCounterTest extends TestCase
{
    public function testCountsDifferentColoursNotMembers(): void
    {
        // three sizes in Natural, two in Black
        self::assertSame(2, (new ColourCounter())->count(['10', '10', '10', '11', '11']));
    }

    public function testMembersWithoutColourAreIgnored(): void
    {
        self::assertSame(1, (new ColourCounter())->count(['10', '', ' ', '10']));
        self::assertSame(0, (new ColourCounter())->count(['', '']));
        self::assertSame(0, (new ColourCounter())->count([]));
    }
}
