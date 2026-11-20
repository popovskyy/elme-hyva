<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The three field kinds added for the blog: `datetime`, `reference`,
 * `reference_multi`, plus `wysiwyg` no longer degrading to a plain textarea.
 *
 * All four fail silently when they go wrong - a mistyped year produces a post
 * that simply never publishes, a duplicated tag produces a filter that counts
 * twice, and a `wysiwyg` value that took the strip_tags path arrives at the
 * template as a wall of unformatted text - so they are exactly what this
 * suite exists for.
 *
 * @see SchemaValidator
 */
class SchemaValidatorFieldKindsTest extends TestCase
{
    use SchemaFixtureTrait;

    /**
     * @param mixed $value
     * @param bool $expectedValid
     */
    #[DataProvider('dateTimeProvider')]
    public function testDateTimeValidation(mixed $value, bool $expectedValid): void
    {
        $validator = $this->validatorFor([$this->field('publishedAt', 'datetime')]);

        $errors = $validator->validate('probe', ['publishedAt' => $value]);

        $this->assertSame(
            $expectedValid,
            $errors === [],
            sprintf('%s should be %s', var_export($value, true), $expectedValid ? 'accepted' : 'rejected')
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function dateTimeProvider(): array
    {
        $nextYear = (new \DateTimeImmutable('+1 year'))->format('Y-m-d\TH:i:s\Z');
        $lastYear = (new \DateTimeImmutable('-1 year'))->format('Y-m-d\TH:i:s\Z');
        $farFuture = (new \DateTimeImmutable('+40 years'))->format('Y-m-d\TH:i:s\Z');

        return [
            'scheduled ahead' => [$nextYear, true],
            'backdated' => [$lastYear, true],
            'date only' => [(new \DateTimeImmutable('now'))->format('Y-m-d'), true],
            'mistyped year' => [$farFuture, false],
            'not a date' => ['soon', false],
            'not a string' => [12345, false],
        ];
    }

    /**
     * The stored form is one canonical spelling - UTC, seconds precision - so
     * two entries saved from admins in different timezones sort against each
     * other correctly rather than by the string they happened to submit.
     */
    public function testDateTimeIsNormalizedToUtc(): void
    {
        $validator = $this->validatorFor([$this->field('publishedAt', 'datetime')]);

        $normalized = $validator->normalize('probe', ['publishedAt' => '2026-07-31T10:00:00+10:00']);

        $this->assertSame('2026-07-31T00:00:00Z', $normalized['publishedAt']);
    }

    /**
     * An unparseable value is passed through rather than blanked, so
     * validate() can reject it with a message. Silently emptying the field
     * would look like the entry simply refused to save that one value.
     */
    public function testUnparseableDateTimeSurvivesNormalizeForValidateToReject(): void
    {
        $validator = $this->validatorFor([$this->field('publishedAt', 'datetime')]);

        $normalized = $validator->normalize('probe', ['publishedAt' => 'whenever']);

        $this->assertSame('whenever', $normalized['publishedAt']);
        $this->assertNotSame([], $validator->validate('probe', $normalized));
    }

    /**
     * `wysiwyg` must not take the strip_tags path that `text` and `textarea`
     * take: that was the phase-1 degrade-to-textarea behaviour, and running it
     * on body copy destroys the content instead of cleaning it.
     */
    public function testWysiwygKeepsAllowedMarkupAndDropsScripts(): void
    {
        $validator = $this->validatorFor([$this->field('body', 'wysiwyg')]);

        $normalized = $validator->normalize('probe', [
            'body' => '<p>Keep <strong>this</strong></p><script>alert(1)</script>',
        ]);

        $this->assertSame('<p>Keep <strong>this</strong></p>', $normalized['body']);
    }

    /**
     * The counter and the save-time cap measure the sanitized value, not what
     * was submitted - otherwise a paste from Word fails a limit on markup the
     * entry never stores.
     */
    public function testWysiwygLengthIsMeasuredAfterSanitizing(): void
    {
        $validator = $this->validatorFor([$this->field('body', 'wysiwyg', ['maxlength' => 30])]);
        $submitted = '<div class="a-very-long-class-name-here"><p>Short</p></div>';

        $normalized = $validator->normalize('probe', ['body' => $submitted]);

        $this->assertGreaterThan(30, mb_strlen($submitted));
        $this->assertSame([], $validator->validate('probe', $normalized));
    }

    /**
     * @param mixed $value
     * @param bool $expectedValid
     */
    #[DataProvider('referenceMultiProvider')]
    public function testReferenceMultiValidation(mixed $value, bool $expectedValid): void
    {
        $validator = $this->validatorFor([
            $this->field('tags', 'reference_multi', [
                'max' => 3,
                'allow' => [['type' => 'blog_tag', 'group' => null]],
            ]),
        ]);

        $errors = $validator->validate('probe', ['tags' => $value]);

        $this->assertSame(
            $expectedValid,
            $errors === [],
            sprintf('%s should be %s', var_export($value, true), $expectedValid ? 'accepted' : 'rejected')
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function referenceMultiProvider(): array
    {
        return [
            'within the limit' => [['tag-sofas', 'tag-trends'], true],
            'at the limit' => [['a', 'b', 'c'], true],
            'over the limit' => [['a', 'b', 'c', 'd'], false],
            'empty is not an error on an optional field' => [[], true],
            'a non-string item' => [['ok', 42], false],
            'a blank item' => [['ok', '  '], false],
            'not a list at all' => ['tag-sofas', false],
        ];
    }

    /**
     * Duplicates are dropped rather than rejected: two of the same tag is a
     * slip with one obvious correct reading, and failing the save over it would
     * be pedantry. Order of first appearance is kept - it is the display order.
     */
    public function testReferenceMultiIsDedupedAndReindexed(): void
    {
        $validator = $this->validatorFor([$this->field('tags', 'reference_multi')]);

        $normalized = $validator->normalize('probe', ['tags' => ['b', 'a', 'b', ' a ', '']]);

        $this->assertSame(['b', 'a'], $normalized['tags']);
    }

    /**
     * Existence of the target entry is deliberately not checked here. This
     * class has no repository - that is what keeps it unit-testable - and an
     * all-or-nothing import would otherwise become order-dependent, failing
     * whenever a post happened to be read before its category.
     */
    public function testReferenceToAMissingEntryIsNotRejected(): void
    {
        $validator = $this->validatorFor([
            $this->field('category', 'reference', ['allow' => [['type' => 'blog_category', 'group' => null]]]),
        ]);

        $this->assertSame([], $validator->validate('probe', ['category' => 'no-such-category']));
    }

    /**
     * A required reference still has to be filled in.
     */
    public function testRequiredReferenceRejectsEmpty(): void
    {
        $validator = $this->validatorFor([
            $this->field('category', 'reference', ['required' => true]),
        ]);

        $this->assertNotSame([], $validator->validate('probe', ['category' => '']));
    }
}
