<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\ProductGroupBuilder;
use PHPUnit\Framework\TestCase;

class ProductGroupBuilderTest extends TestCase
{
    private function variants(): array
    {
        return [
            ['sku' => 'A', 'name' => 'Sofa Natural Left', 'url' => 'https://x/a', 'image' => 'https://x/a.jpg',
                'values' => ['color' => 'Natural', 'chaise_side' => 'Left']],
            ['sku' => 'B', 'name' => 'Sofa Black Right', 'url' => 'https://x/b', 'image' => '',
                'values' => ['color' => 'Black', 'chaise_side' => 'Right']],
        ];
    }

    public function testProductGroupWithVariants(): void
    {
        $group = (new ProductGroupBuilder())->build(
            'RILEY-3S',
            'Riley Sofa',
            $this->variants(),
            ['color' => 'Colour', 'chaise_side' => 'Chaise side']
        );

        self::assertSame('ProductGroup', $group['@type']);
        self::assertSame('RILEY-3S', $group['productGroupID']);
        // only axes with a schema.org property go to variesBy
        self::assertSame(['https://schema.org/color'], $group['variesBy']);
        self::assertCount(2, $group['hasVariant']);

        $first = $group['hasVariant'][0];
        self::assertSame('Product', $first['@type']);
        self::assertSame('A', $first['sku']);
        self::assertSame('Natural', $first['color']);
        self::assertSame('https://x/a.jpg', $first['image']);
        self::assertSame(
            [['@type' => 'PropertyValue', 'name' => 'Chaise side', 'value' => 'Left']],
            $first['additionalProperty']
        );
        self::assertArrayNotHasKey('image', $group['hasVariant'][1]);
    }

    public function testFewerThanTwoVariantsGivesNothing(): void
    {
        $builder = new ProductGroupBuilder();

        self::assertNull($builder->build('X', 'X', [], ['color' => 'Colour']));
        self::assertNull($builder->build('X', 'X', [$this->variants()[0]], ['color' => 'Colour']));
    }

    public function testAxisThatDoesNotVaryIsNotInVariesBy(): void
    {
        $variants = $this->variants();
        $variants[1]['values']['color'] = 'Natural';

        $group = (new ProductGroupBuilder())->build('X', 'X', $variants, ['color' => 'Colour']);

        self::assertSame([], $group['variesBy']);
    }
}
