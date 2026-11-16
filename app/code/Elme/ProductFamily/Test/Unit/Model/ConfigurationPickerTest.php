<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\ConfigurationPicker;
use Elme\ProductFamily\Model\SiblingOrder;
use PHPUnit\Framework\TestCase;

class ConfigurationPickerTest extends TestCase
{
    private ConfigurationPicker $picker;

    protected function setUp(): void
    {
        $this->picker = new ConfigurationPicker(new SiblingOrder());
    }

    /**
     * Range "Riley": three-seater in Flax (1) and Slate (2); chaise module in Flax (3) and Slate (4);
     * ottoman in Slate only (5); a product with no family in Flax (6).
     */
    private function range(): array
    {
        return [
            1 => ['sku' => 'RIL3-FLAX', 'family' => 'RILEY-3S', 'colour' => '10', 'sort' => null],
            2 => ['sku' => 'RIL3-SLATE', 'family' => 'RILEY-3S', 'colour' => '11', 'sort' => null],
            3 => ['sku' => 'RILCH-FLAX', 'family' => 'RILEY-CHAISE', 'colour' => '10', 'sort' => null],
            4 => ['sku' => 'RILCH-SLATE', 'family' => 'RILEY-CHAISE', 'colour' => '11', 'sort' => null],
            5 => ['sku' => 'RILOT-SLATE', 'family' => 'RILEY-OTTOMAN', 'colour' => '11', 'sort' => null],
            6 => ['sku' => 'RILAC-FLAX', 'family' => '', 'colour' => '10', 'sort' => null],
        ];
    }

    public function testOtherFamiliesOfTheRangeInTheCurrentColour(): void
    {
        // current: three-seater in Flax. Own family is not a configuration of itself.
        $picked = $this->picker->pick($this->range(), 1, 'RILEY-3S', '10');

        // chaise in Flax; ottoman only exists in Slate so it falls back to it; the product without a
        // family is a configuration of its own; fixed order: families by code, then single products
        self::assertSame([3, 5, 6], $picked);
    }

    public function testOtherColourPrefersThatColour(): void
    {
        $picked = $this->picker->pick($this->range(), 2, 'RILEY-3S', '11');

        self::assertSame([4, 5, 6], $picked);
    }

    public function testCurrentProductWithoutFamilyIsNotListedAgain(): void
    {
        $picked = $this->picker->pick($this->range(), 6, '', '10');

        self::assertSame([1, 3, 5], $picked);
    }

    public function testNoCurrentColourTakesTheFirstMemberOfEachFamily(): void
    {
        $range = $this->range();
        $range[4]['sort'] = 1;

        $picked = $this->picker->pick($range, 1, 'RILEY-3S', '');

        // chaise: member with family_sort_order 1 (Slate, id 4) comes before the unsorted one
        self::assertSame([4, 5, 6], $picked);
    }

    public function testRangeWithOnlyTheOwnFamilyHasNoConfigurations(): void
    {
        $range = [
            1 => ['sku' => 'A', 'family' => 'F', 'colour' => '1', 'sort' => null],
            2 => ['sku' => 'B', 'family' => 'F', 'colour' => '2', 'sort' => null],
        ];

        self::assertSame([], $this->picker->pick($range, 1, 'F', '1'));
    }
}
