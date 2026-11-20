<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\ProductDetails;
use Magento\Catalog\Helper\Output;
use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetRepositoryInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * The PDP sections print these values as facts a retailer measures a room with (and FE-55 will reuse
 * them for JSON-LD), so a wrong unit, a ".0000" or a lost paragraph is a real defect.
 *
 * @see ProductDetails
 */
#[AllowMockObjectsWithoutExpectations]
class ProductDetailsTest extends TestCase
{
    private ProductDetails $details;

    protected function setUp(): void
    {
        $output = $this->createStub(Output::class);
        $output->method('productAttribute')->willReturnArgument(1);

        $set = $this->createStub(AttributeSetInterface::class);
        $set->method('getAttributeSetName')->willReturn('Default');
        $sets = $this->createStub(AttributeSetRepositoryInterface::class);
        $sets->method('get')->willReturn($set);

        $this->details = new ProductDetails($output, $sets);
    }

    private function product(array $data): Product
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResource', 'getAttributeText'])
            ->getMock();
        $product->setData($data + ['entity_id' => random_int(1, 99999), 'attribute_set_id' => 4]);

        return $product;
    }

    public function testDimensionsTrimDecimalsAndCarryUnits(): void
    {
        $rows = $this->details->getDimensionRows($this->product([
            'width' => '240.0000',
            'depth' => '110',
            'height' => '75.50',
            'weight' => '90.5000',
        ]));

        $this->assertSame(
            [
                ['code' => 'width', 'label' => 'Width', 'value' => '240', 'unit' => 'cm'],
                ['code' => 'depth', 'label' => 'Depth', 'value' => '110', 'unit' => 'cm'],
                ['code' => 'height', 'label' => 'Height', 'value' => '75.5', 'unit' => 'cm'],
                ['code' => 'weight', 'label' => 'Weight', 'value' => '90.5', 'unit' => 'kg'],
            ],
            $rows
        );
    }

    public function testEmptyOrZeroDimensionsAreLeftOut(): void
    {
        $this->assertSame([], $this->details->getDimensionRows($this->product([
            'width' => '',
            'depth' => null,
            'height' => '0',
            'weight' => '0.0000',
        ])));
    }

    public function testCartonsFromJson(): void
    {
        $product = $this->product([
            'cartons' => '[{"length":245,"width":115,"height":20,"weight":"98.0000"},{"length":"","width":0}]',
        ]);

        $cartons = $this->details->getCartons($product);

        $this->assertCount(1, $cartons);
        $this->assertSame('245 x 115 x 20 cm, 98 kg', $this->details->formatCarton($cartons[0]));
    }

    public function testBrokenJsonGivesNoRows(): void
    {
        $product = $this->product(['cartons' => '[{"length":', 'measurements' => 'n/a']);

        $this->assertSame([], $this->details->getCartons($product));
        $this->assertSame([], $this->details->getMeasurementRows($product));
    }

    public function testUnitsPerCartonOnlyAboveOne(): void
    {
        $this->assertSame(0, $this->details->getUnitsPerCarton($this->product(['units_per_ctn' => '1'])));
        $this->assertSame(12, $this->details->getUnitsPerCarton($this->product(['units_per_ctn' => '12'])));
    }

    public function testDescriptionHiddenWhenShortDescriptionAlreadySaysIt(): void
    {
        $product = $this->product(['description' => '<p>Solid oak  table.</p>']);

        $this->assertSame('', $this->details->getDescriptionHtml($product, 'Solid oak table.'));
    }

    public function testLongerDescriptionKeptWithHeadingsDemoted(): void
    {
        $product = $this->product(['description' => '<h1>Oak</h1><p>Solid oak table.</p><h2 class="x">Care</h2>']);

        $this->assertSame(
            '<h3>Oak</h3><p>Solid oak table.</p><h3 class="x">Care</h3>',
            $this->details->getDescriptionHtml($product, 'Solid oak table.')
        );
    }

    public function testCareReadsTargetCodeThenLocalCode(): void
    {
        $this->assertSame('<p>Wipe</p>', $this->details->getCareHtml($this->product(['care_instructions' => '<p>Wipe</p>'])));
        $this->assertSame('<p>Dust</p>', $this->details->getCareHtml($this->product([
            'special_care' => '<p>Dust</p>',
            'care_instructions' => '<p>Wipe</p>',
        ])));
        $this->assertSame('', $this->details->getCareHtml($this->product(['special_care' => '<p> </p>'])));
    }

    public function testCmsBlockIdentifierFallsBackToAttributeSet(): void
    {
        $this->assertSame(
            'pdp_middle_page_default',
            $this->details->getCmsBlockIdentifier($this->product([]), 'pdp_middle_page_%s')
        );
    }
}
