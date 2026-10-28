<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * A `readonly` child of a repeater is the module's marker for "other code keys
 * off this value" - ranges[].key is matched against Bart\Catalog's
 * MATTRESS_RANGE_MAP, comforts[].key against COMFORT_MAP. Two items sharing
 * one of those keys means the second is dead content: the lookup finds the
 * first and the editor has no way to see the second never renders.
 *
 * The error belongs on the *later* occurrence - the first one is the keeper.
 *
 * @see SchemaValidator
 */
class SchemaValidatorUniquenessTest extends TestCase
{
    use SchemaFixtureTrait;

    /**
     * @return SchemaValidator
     */
    private function rangesValidator(): SchemaValidator
    {
        return $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [
                    $this->field('key', 'text', ['required' => true, 'readonly' => true]),
                    $this->field('label', 'text'),
                ],
            ]),
        ]);
    }

    public function testDuplicateReadonlyKeyIsRejectedOnTheSecondItem(): void
    {
        $errors = $this->rangesValidator()->validate('probe', [
            'ranges' => [
                ['key' => 'windsor', 'label' => 'Windsor'],
                ['key' => 'windsor', 'label' => 'Windsor again'],
            ],
        ]);

        $this->assertArrayHasKey('ranges.1.key', $errors);
        $this->assertArrayNotHasKey('ranges.0.key', $errors, 'The first occurrence is the keeper.');
    }

    public function testThirdDuplicateIsAlsoReported(): void
    {
        $errors = $this->rangesValidator()->validate('probe', [
            'ranges' => [
                ['key' => 'windsor', 'label' => 'A'],
                ['key' => 'balmoral', 'label' => 'B'],
                ['key' => 'windsor', 'label' => 'C'],
                ['key' => 'windsor', 'label' => 'D'],
            ],
        ]);

        $this->assertArrayHasKey('ranges.2.key', $errors);
        $this->assertArrayHasKey('ranges.3.key', $errors);
        $this->assertArrayNotHasKey('ranges.1.key', $errors);
    }

    public function testDistinctKeysAreAccepted(): void
    {
        $errors = $this->rangesValidator()->validate('probe', [
            'ranges' => [
                ['key' => 'windsor', 'label' => 'Windsor'],
                ['key' => 'balmoral', 'label' => 'Balmoral'],
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testUniquenessIsScopedToItsOwnRepeater(): void
    {
        $validator = $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [
                    $this->field('key', 'text', ['required' => true, 'readonly' => true]),
                    $this->field('comforts', 'repeater', [
                        'fields' => [$this->field('key', 'text', ['required' => true, 'readonly' => true])],
                    ]),
                ],
            ]),
        ]);

        // Two ranges may each contain a comfort called "extra-firm"; only a
        // clash inside the same list is a clash.
        $errors = $validator->validate('probe', [
            'ranges' => [
                ['key' => 'windsor', 'comforts' => [['key' => 'extra-firm']]],
                ['key' => 'balmoral', 'comforts' => [['key' => 'extra-firm']]],
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function testNonReadonlyChildrenMayRepeat(): void
    {
        $validator = $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [$this->field('label', 'text')],
            ]),
        ]);

        $errors = $validator->validate('probe', [
            'ranges' => [['label' => 'Same'], ['label' => 'Same']],
        ]);

        $this->assertSame([], $errors, 'Only readonly keys are contracts with other code.');
    }
}
