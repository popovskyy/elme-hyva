<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\AxisBuilder;
use PHPUnit\Framework\TestCase;

class AxisBuilderTest extends TestCase
{
    private const COLOUR = [
        'code' => 'color',
        'label' => 'Colour',
        'visual' => true,
        'labels' => ['10' => 'Natural', '11' => 'Black', '12' => 'Amber'],
        'rank' => ['10' => 0, '11' => 1, '12' => 2],
    ];

    private const SIZE = [
        'code' => 'size',
        'label' => 'Size',
        'visual' => false,
        'labels' => ['20' => '240 cm', '21' => '210 cm'],
        // option order puts 240 first on purpose: text axes follow it, not the label
        'rank' => ['20' => 0, '21' => 1],
    ];

    private AxisBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new AxisBuilder();
    }

    /**
     * Natural 210 / 240, Black 210 only.
     */
    private function members(): array
    {
        return [
            1 => ['sku' => 'NAT210', 'sort' => null, 'values' => ['color' => '10', 'size' => '21']],
            2 => ['sku' => 'NAT240', 'sort' => null, 'values' => ['color' => '10', 'size' => '20']],
            3 => ['sku' => 'BLK210', 'sort' => null, 'values' => ['color' => '11', 'size' => '21']],
        ];
    }

    public function testCurrentValueIsSelectedAndOtherValueLinksToSiblingKeepingOtherAxes(): void
    {
        $axes = $this->builder->build([self::COLOUR, self::SIZE], $this->members(), 2);

        self::assertSame(['color', 'size'], array_column($axes, 'code'));
        $colour = $axes[0];
        self::assertSame('Natural', $colour['selectedLabel']);
        // colours sorted by name
        self::assertSame(['Black', 'Natural'], array_column($colour['options'], 'label'));

        $black = $colour['options'][0];
        self::assertFalse($black['selected']);
        // Black has no 240: crossed out, links to Black 210, size is the axis it does not keep
        self::assertFalse($black['available']);
        self::assertSame(3, $black['memberId']);
        self::assertSame(['size'], $black['missing']);

        $natural = $colour['options'][1];
        self::assertTrue($natural['selected']);
        self::assertSame(2, $natural['memberId']);
    }

    public function testUnavailableSizeIsKeptNotHidden(): void
    {
        $axes = $this->builder->build([self::COLOUR, self::SIZE], $this->members(), 3);
        $size = $axes[1];

        self::assertSame(['240 cm', '210 cm'], array_column($size['options'], 'label'));
        [$size240, $size210] = $size['options'];
        self::assertFalse($size240['available']);
        self::assertSame(2, $size240['memberId']);
        self::assertSame(['color'], $size240['missing']);
        self::assertTrue($size210['selected']);
        self::assertTrue($size210['available']);
    }

    public function testExactMatchIsAvailable(): void
    {
        $axes = $this->builder->build([self::COLOUR, self::SIZE], $this->members(), 1);
        $size240 = $axes[1]['options'][0];

        self::assertTrue($size240['available']);
        self::assertSame(2, $size240['memberId']);
        self::assertSame([], $size240['missing']);
        $black = $axes[0]['options'][0];
        self::assertTrue($black['available']);
        self::assertSame(3, $black['memberId']);
    }

    public function testAxisWithOneValueIsDropped(): void
    {
        $members = [
            1 => ['sku' => 'A', 'sort' => null, 'values' => ['color' => '10', 'size' => '21']],
            2 => ['sku' => 'B', 'sort' => null, 'values' => ['color' => '11', 'size' => '21']],
        ];

        $axes = $this->builder->build([self::COLOUR, self::SIZE], $members, 1);

        self::assertSame(['color'], array_column($axes, 'code'));
    }

    public function testFamilySortOrderWinsOverNamesAndBreaksTies(): void
    {
        $members = [
            1 => ['sku' => 'B', 'sort' => 3, 'values' => ['color' => '10', 'size' => '']],
            2 => ['sku' => 'A', 'sort' => 1, 'values' => ['color' => '11', 'size' => '']],
            3 => ['sku' => 'C', 'sort' => 2, 'values' => ['color' => '12', 'size' => '']],
            4 => ['sku' => 'D', 'sort' => 0, 'values' => ['color' => '12', 'size' => '']],
        ];

        $axes = $this->builder->build([self::COLOUR, self::SIZE], $members, 1);

        self::assertCount(1, $axes);
        self::assertSame(['Amber', 'Black', 'Natural'], array_column($axes[0]['options'], 'label'));
        // two Amber members keep the same axes: the lower family_sort_order wins
        self::assertSame(4, $axes[0]['options'][0]['memberId']);
    }

    public function testNoAxesWhenCurrentProductIsNotAMemberOrFamilyIsSingle(): void
    {
        self::assertSame([], $this->builder->build([self::COLOUR], $this->members(), 99));
        self::assertSame([], $this->builder->build(
            [self::COLOUR],
            [1 => ['sku' => 'A', 'sort' => null, 'values' => ['color' => '10']]],
            1
        ));
    }

    public function testCurrentProductWithoutValueOnAxisHasNothingSelected(): void
    {
        $members = [
            1 => ['sku' => 'A', 'sort' => null, 'values' => ['color' => '', 'size' => '21']],
            2 => ['sku' => 'B', 'sort' => null, 'values' => ['color' => '10', 'size' => '21']],
            3 => ['sku' => 'C', 'sort' => null, 'values' => ['color' => '11', 'size' => '20']],
        ];

        $axes = $this->builder->build([self::COLOUR, self::SIZE], $members, 1);
        $colour = $axes[0];

        self::assertSame('', $colour['selectedLabel']);
        self::assertNotContains(true, array_column($colour['options'], 'selected'));
        self::assertTrue($colour['options'][1]['available']);
        self::assertFalse($colour['options'][0]['available']);
    }
}
