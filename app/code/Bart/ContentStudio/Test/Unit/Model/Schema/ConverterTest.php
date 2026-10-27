<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\Converter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The converter is the only place the merged XML becomes the array every other
 * layer reads, and its output is cached - a wrong cast here shows up much later
 * as an admin form that renders the wrong control, not as an error.
 *
 * @see Converter
 */
class ConverterTest extends TestCase
{
    private Converter $converter;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->converter = new Converter();
    }

    public function testTypesAreKeyedByCode(): void
    {
        $types = $this->convert(
            <<<'XML'
            <type code="usp_strip" label="USP Strip"/>
            <type code="compare_mattress" label="Compare"/>
            XML
        );

        $this->assertSame(['usp_strip', 'compare_mattress'], array_keys($types));
        $this->assertSame('USP Strip', $types['usp_strip']['label']);
    }

    /**
     * @param string $attribute
     * @param string $written
     * @param bool $expected
     */
    #[DataProvider('booleanAttributeProvider')]
    public function testBooleanAttributesAreCast(string $attribute, string $written, bool $expected): void
    {
        $types = $this->convert(
            '<type code="t" label="T" ' . $attribute . '="' . $written . '">'
            . '<field name="f" type="text" label="F" ' . $attribute . '="' . $written . '"/>'
            . '</type>'
        );

        $subject = in_array($attribute, ['standalone', 'copyable'], true)
            ? $types['t']
            : $types['t']['fields'][0];

        $this->assertSame($expected, $subject[$attribute]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function booleanAttributeProvider(): array
    {
        return [
            'standalone true' => ['standalone', 'true', true],
            'standalone 1' => ['standalone', '1', true],
            'standalone false' => ['standalone', 'false', false],
            'copyable true' => ['copyable', 'true', true],
            'copyable false' => ['copyable', 'false', false],
            'required true' => ['required', 'true', true],
            'readonly true' => ['readonly', 'true', true],
            'readonly false' => ['readonly', 'false', false],
        ];
    }

    public function testNumericAttributesAreCast(): void
    {
        $types = $this->convert(
            '<type code="t" label="T" version="2">'
            . '<field name="f" type="number" label="F" min="0" max="10" maxlength="140"/>'
            . '</type>'
        );

        $field = $types['t']['fields'][0];

        $this->assertSame(2, $types['t']['version']);
        $this->assertSame(0, $field['min']);
        $this->assertSame(10, $field['max']);
        $this->assertSame(140, $field['maxlength']);
    }

    public function testDecimalBoundsStayFloats(): void
    {
        $types = $this->convert(
            '<type code="t" label="T" version="1.5">'
            . '<field name="f" type="number" label="F" min="0.5" max="9.75"/>'
            . '</type>'
        );

        $this->assertSame(1.5, $types['t']['version']);
        $this->assertSame(0.5, $types['t']['fields'][0]['min']);
        $this->assertSame(9.75, $types['t']['fields'][0]['max']);
    }

    /**
     * Absent is not the same as false: `standalone` unset must stay null so the
     * "new entry" picker and the validator can tell "not declared" from "off".
     */
    public function testAbsentAttributesAreNullRatherThanEmptyString(): void
    {
        $types = $this->convert('<type code="t" label="T"><field name="f" type="text" label="F"/></type>');

        $this->assertNull($types['t']['standalone']);
        $this->assertNull($types['t']['copyable']);
        $this->assertNull($types['t']['template']);
        $this->assertNull($types['t']['fields'][0]['required']);
        $this->assertNull($types['t']['fields'][0]['maxlength']);
        $this->assertArrayHasKey('renderer', $types['t']);
    }

    public function testRepeaterChildrenNestUnderTheirOwnFieldsKey(): void
    {
        $types = $this->convert(
            <<<'XML'
            <type code="t" label="T">
                <field name="items" type="repeater" label="Items" label_field="label" max="6">
                    <field name="label" type="text" label="Label" required="true"/>
                    <field name="icon" type="image" label="Icon"/>
                </field>
            </type>
            XML
        );

        $repeater = $types['t']['fields'][0];

        $this->assertCount(1, $types['t']['fields'], 'nested fields must not be flattened to the top level');
        $this->assertSame('label', $repeater['label_field']);
        $this->assertSame(6, $repeater['max']);
        $this->assertSame(['label', 'icon'], array_column($repeater['fields'], 'name'));
        $this->assertTrue($repeater['fields'][0]['required']);
    }

    public function testGroupsNestArbitrarilyDeep(): void
    {
        $types = $this->convert(
            <<<'XML'
            <type code="t" label="T">
                <field name="outer" type="group" label="Outer">
                    <field name="inner" type="repeater" label="Inner">
                        <field name="leaf" type="text" label="Leaf"/>
                    </field>
                </field>
            </type>
            XML
        );

        $leaf = $types['t']['fields'][0]['fields'][0]['fields'][0];

        $this->assertSame('leaf', $leaf['name']);
        $this->assertSame([], $leaf['fields']);
    }

    public function testSelectOptionsArePreservedInOrder(): void
    {
        $types = $this->convert(
            <<<'XML'
            <type code="t" label="T">
                <field name="align" type="select" label="Align">
                    <option value="left" label="Left"/>
                    <option value="right" label="Right"/>
                </field>
            </type>
            XML
        );

        $this->assertSame(
            [
                ['value' => 'left', 'label' => 'Left'],
                ['value' => 'right', 'label' => 'Right'],
            ],
            $types['t']['fields'][0]['options']
        );
    }

    public function testAllowNodesCarryTypeAndGroupWithNullForTheAbsentOne(): void
    {
        $types = $this->convert(
            <<<'XML'
            <type code="t" label="T">
                <field name="body" type="blocks" label="Body">
                    <allow type="usp_strip"/>
                    <allow group="editorial"/>
                </field>
            </type>
            XML
        );

        $this->assertSame(
            [
                ['type' => 'usp_strip', 'group' => null],
                ['type' => null, 'group' => 'editorial'],
            ],
            $types['t']['fields'][0]['allow']
        );
    }

    /**
     * An empty `allow` list means "every registered type", so it must be an
     * empty array rather than missing - callers `foreach` over it.
     */
    public function testFieldsWithoutChildNodesGetEmptyCollections(): void
    {
        $types = $this->convert('<type code="t" label="T"><field name="f" type="text" label="F"/></type>');

        $field = $types['t']['fields'][0];

        $this->assertSame([], $field['options']);
        $this->assertSame([], $field['allow']);
        $this->assertSame([], $field['fields']);
    }

    public function testUnknownAttributesAreDropped(): void
    {
        $types = $this->convert(
            '<type code="t" label="T" bogus="x"><field name="f" type="text" label="F" nope="y"/></type>'
        );

        $this->assertArrayNotHasKey('bogus', $types['t']);
        $this->assertArrayNotHasKey('nope', $types['t']['fields'][0]);
    }

    public function testEmptyDocumentConvertsToAnEmptyMap(): void
    {
        $this->assertSame([], $this->convert(''));
    }

    /**
     * @param string $inner
     * @return array<string, array<string, mixed>>
     */
    private function convert(string $inner): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><content_types>' . $inner . '</content_types>');

        return $this->converter->convert($dom);
    }
}
