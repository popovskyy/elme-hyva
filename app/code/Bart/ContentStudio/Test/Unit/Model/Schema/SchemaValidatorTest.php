<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins what the validator already gets right, so a later change cannot quietly
 * take it away.
 *
 * Assertions are on the error *key* (the field path), never on the message -
 * messages go through __() and are translated.
 *
 * @see SchemaValidator
 */
class SchemaValidatorTest extends TestCase
{
    use SchemaFixtureTrait;

    /**
     * @param mixed $value
     * @param string|null $expectedErrorPath
     */
    #[DataProvider('textLengthProvider')]
    public function testTextLength(mixed $value, ?string $expectedErrorPath): void
    {
        $validator = $this->validatorFor([
            $this->field('title', 'text', ['required' => true]),
            $this->field('subtitle', 'textarea'),
        ]);

        $errors = $validator->validate('probe', ['title' => $value, 'subtitle' => $value]);

        if ($expectedErrorPath === null) {
            $this->assertSame([], $errors);

            return;
        }

        $this->assertArrayHasKey($expectedErrorPath, $errors);
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function textLengthProvider(): array
    {
        return [
            'well under the cap' => ['Compare Our Mattresses', null],
            'just under the cap' => [str_repeat('a', SchemaValidator::MAX_TEXT_LENGTH), null],
            'one over the cap' => [str_repeat('a', SchemaValidator::MAX_TEXT_LENGTH + 1), 'title'],
            'far over the cap' => [str_repeat('a', 5000), 'title'],
            'empty hits required first' => ['', 'title'],
            // mb_strlen, not strlen: a multibyte string under the cap in
            // characters is over it in bytes, and the cap is about characters.
            'multibyte under the cap' => [str_repeat("\u{043C}", SchemaValidator::MAX_TEXT_LENGTH), null],
        ];
    }

    /**
     * @param int $length
     * @param bool $expectedValid
     */
    #[DataProvider('maxlengthProvider')]
    public function testPerFieldMaxlengthOverridesTheGlobalCap(int $length, bool $expectedValid): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text', ['maxlength' => 80])]);

        $errors = $validator->validate('probe', ['title' => str_repeat('a', $length)]);

        $this->assertSame($expectedValid, $errors === []);
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function maxlengthProvider(): array
    {
        return [
            'well under' => [20, true],
            'exactly at the limit' => [80, true],
            'one over' => [81, false],
            // Would have passed the global 2000 cap; the per-field limit is
            // the whole point.
            'under the global cap but over the field limit' => [1500, false],
        ];
    }

    public function testMaxlengthFallsBackToTheGlobalCapWhenAbsent(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $this->assertSame([], $validator->validate('probe', ['title' => str_repeat('a', 1500)]));
    }

    public function testMaxlengthCountsCharactersNotBytes(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text', ['maxlength' => 10])]);

        $this->assertSame([], $validator->validate('probe', ['title' => str_repeat("\u{043C}", 10)]));
        $this->assertArrayHasKey('title', $validator->validate('probe', ['title' => str_repeat("\u{043C}", 11)]));
    }

    public function testMaxlengthAppliesToTextareaToo(): void
    {
        $validator = $this->validatorFor([$this->field('subtitle', 'textarea', ['maxlength' => 5])]);

        $this->assertArrayHasKey('subtitle', $validator->validate('probe', ['subtitle' => 'far too long']));
    }

    public function testEmojiAndBidiAreAccepted(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $this->assertSame([], $validator->validate('probe', ['title' => "\u{1F6CF} \u{0645}\u{0631}\u{062A}\u{0628}\u{0629} \u{202E}reversed"]));
    }

    /**
     * @param mixed $value
     * @param string|null $expectedErrorPath
     */
    #[DataProvider('numberProvider')]
    public function testNumberRange(mixed $value, ?string $expectedErrorPath): void
    {
        $validator = $this->validatorFor([
            $this->field('xPct', 'number', ['min' => 0, 'max' => 100]),
        ]);

        $errors = $validator->validate('probe', ['xPct' => $value]);

        if ($expectedErrorPath === null) {
            $this->assertSame([], $errors);

            return;
        }

        $this->assertArrayHasKey($expectedErrorPath, $errors);
    }

    /**
     * @return array<string, array{0: mixed, 1: string|null}>
     */
    public static function numberProvider(): array
    {
        return [
            'mid range' => [50, null],
            'lower bound' => [0.5, null],
            'upper bound' => [100, null],
            'fractional' => [33.333333, null],
            'numeric string' => ['42', null],
            'over the max' => [150, 'xPct'],
            'under the min' => [-20, 'xPct'],
            'not a number' => ['abc', 'xPct'],
            'array' => [[1, 2], 'xPct'],
        ];
    }

    /**
     * @param mixed $value
     * @param bool $expectedValid
     */
    #[DataProvider('urlProvider')]
    public function testUrlSafety(mixed $value, bool $expectedValid): void
    {
        $validator = $this->validatorFor([$this->field('ctaHref', 'url')]);

        $errors = $validator->validate('probe', ['ctaHref' => $value]);

        $this->assertSame(
            $expectedValid,
            $errors === [],
            sprintf('%s should be %s', var_export($value, true), $expectedValid ? 'accepted' : 'rejected')
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function urlProvider(): array
    {
        return [
            'placeholder hash' => ['#', true],
            'anchor' => ['#layers', true],
            'root relative' => ['/mattresses', true],
            'https' => ['https://example.com/x', true],
            'http' => ['http://example.com/x', true],
            'javascript scheme' => ['javascript:alert(1)', false],
            'javascript scheme mixed case' => ['JaVaScRiPt:alert(1)', false],
            'data scheme' => ['data:text/html,<b>x', false],
            'vbscript scheme' => ['vbscript:msgbox(1)', false],
            'bare host' => ['example.com', false],
            'not a string' => [['https://example.com'], false],
            // Protocol-relative: passes the "^/" branch today, which makes an
            // off-site link look internal. Fixed by plan 005.
            'protocol relative' => ['//evil.com', false],
            'protocol relative backslash' => ['/\\evil.com', false],
        ];
    }

    public function testEmptyOptionalUrlIsAccepted(): void
    {
        $validator = $this->validatorFor([$this->field('ctaHref', 'url')]);

        $this->assertSame([], $validator->validate('probe', ['ctaHref' => '']));
    }

    /**
     * @param int $count
     * @param bool $expectedValid
     */
    #[DataProvider('repeaterCapProvider')]
    public function testRepeaterItemCap(int $count, bool $expectedValid): void
    {
        $validator = $this->validatorFor([
            $this->field('dots', 'repeater', [
                'max' => 10,
                'fields' => [$this->field('title', 'text')],
            ]),
        ]);

        $errors = $validator->validate('probe', [
            'dots' => array_fill(0, $count, ['title' => 'd']),
        ]);

        $this->assertSame($expectedValid, $errors === []);
    }

    /**
     * @return array<string, array{0: int, 1: bool}>
     */
    public static function repeaterCapProvider(): array
    {
        return [
            'one under' => [9, true],
            'exactly at the cap' => [10, true],
            'one over' => [11, false],
            'far over' => [200, false],
        ];
    }

    public function testRepeaterFallsBackToTheGlobalCapWithoutMax(): void
    {
        $validator = $this->validatorFor([
            $this->field('items', 'repeater', ['fields' => [$this->field('title', 'text')]]),
        ]);

        $overGlobal = array_fill(0, SchemaValidator::MAX_REPEATER_ITEMS + 1, ['title' => 'x']);

        $this->assertArrayHasKey('items', $validator->validate('probe', ['items' => $overGlobal]));
    }

    public function testNestedRepeaterErrorsCarryTheirFullPath(): void
    {
        $validator = $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [
                    $this->field('key', 'text', ['required' => true]),
                    $this->field('dots', 'repeater', [
                        'fields' => [$this->field('xPct', 'number', ['min' => 0, 'max' => 100])],
                    ]),
                ],
            ]),
        ]);

        $errors = $validator->validate('probe', [
            'ranges' => [
                ['key' => 'ok', 'dots' => [['xPct' => 5]]],
                ['key' => 'ok', 'dots' => [['xPct' => 5], ['xPct' => 900]]],
            ],
        ]);

        $this->assertArrayHasKey('ranges.1.dots.1.xPct', $errors);
    }

    /**
     * @param array<string, mixed> $data
     * @param string $expectedErrorPath
     */
    #[DataProvider('malformedStructureProvider')]
    public function testMalformedStructure(array $data, string $expectedErrorPath): void
    {
        $validator = $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [$this->field('key', 'text', ['required' => true])],
            ]),
        ]);

        $this->assertArrayHasKey($expectedErrorPath, $validator->validate('probe', $data));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function malformedStructureProvider(): array
    {
        return [
            'repeater is a string' => [['ranges' => 'oops'], 'ranges'],
            'repeater is a number' => [['ranges' => 7], 'ranges'],
            'item is missing its required child' => [['ranges' => [['label' => 'L']]], 'ranges.0.key'],
            'item is a scalar' => [['ranges' => ['oops']], 'ranges.0.key'],
        ];
    }

    public function testUnknownTypeIsRejected(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $this->assertArrayHasKey('_type', $validator->validate('no-such-type', []));
    }

    /**
     * A `color` saves into a `style` attribute, so the save gate has to reject
     * anything the renderers will drop. Before this existed the field was a
     * plain `text`: "red" saved clean, the panel then never rendered, and
     * nothing anywhere said why.
     *
     * @param mixed $value
     * @param bool $expectedValid
     */
    #[DataProvider('colorProvider')]
    public function testColorMustBeAHexValue(mixed $value, bool $expectedValid): void
    {
        $validator = $this->validatorFor([$this->field('bgColor', 'color')]);

        $errors = $validator->validate('probe', ['bgColor' => $value]);

        if ($expectedValid) {
            $this->assertSame([], $errors);

            return;
        }

        $this->assertArrayHasKey('bgColor', $errors);
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function colorProvider(): array
    {
        return [
            'six-digit hex' => ['#ab9377', true],
            'three-digit hex' => ['#abc', true],
            'uppercase hex' => ['#AB9377', true],
            // Empty is how an editor turns the panel off, so it must not error.
            'empty is allowed' => ['', true],
            'named colour' => ['red', false],
            'named colour that fits the old 7-char cap' => ['crimson', false],
            'hash with non-hex digits' => ['#zzzzzz', false],
            'missing hash' => ['ab9377', false],
            'four-digit hex' => ['#abcd', false],
            'trailing css' => ['#ab9377; background-image:url(//evil)', false],
            'url payload' => ['url(//evil)', false],
            'non-string' => [['#ab9377'], false],
        ];
    }

    public function testOversizedPayloadIsRejectedWholesale(): void
    {
        $validator = $this->validatorFor([
            $this->field('items', 'repeater', ['fields' => [$this->field('body', 'textarea')]]),
        ]);

        $chunk = str_repeat('x', 1000);
        $items = array_fill(0, (int)(SchemaValidator::MAX_PAYLOAD_BYTES / 1000) + 10, ['body' => $chunk]);

        $this->assertArrayHasKey('_root', $validator->validate('probe', ['items' => $items]));
    }
}
