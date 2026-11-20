<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * normalize() decides what actually reaches the database.
 *
 * @see SchemaValidator::normalize()
 */
class SchemaValidatorNormalizeTest extends TestCase
{
    use SchemaFixtureTrait;

    public function testTextMarkupIsStripped(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $result = $validator->normalize('probe', ['title' => '<script>alert(1)</script>Compare']);

        $this->assertSame('alert(1)Compare', $result['title']);
    }

    public function testTextIsTrimmed(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $this->assertSame('Compare', $validator->normalize('probe', ['title' => "  Compare \n"])['title']);
    }

    /**
     * The renderer writes these with textContent and the phtml emits them
     * inside JSON, so raw HTML here is not an XSS today. It is one innerHTML
     * away from being one, and the asymmetry with `text` is invisible to
     * anyone declaring a new content type.
     */
    public function testTextareaMarkupIsStrippedLikeText(): void
    {
        $validator = $this->validatorFor([
            $this->field('subtitle', 'textarea'),
            $this->field('title', 'text'),
        ]);

        $result = $validator->normalize('probe', [
            'title' => '<b>t</b>',
            'subtitle' => '<img src=x onerror=alert(1)>Explore',
        ]);

        $this->assertSame('t', $result['title']);
        $this->assertSame('Explore', $result['subtitle']);
    }

    public function testWysiwygKeepsItsMarkup(): void
    {
        $validator = $this->validatorFor([$this->field('body', 'wysiwyg')]);

        $html = '<p>Real <strong>rich</strong> text</p>';

        $this->assertSame(
            $html,
            $validator->normalize('probe', ['body' => $html])['body'],
            'wysiwyg is declared for phase-2 rich text; stripping tags there would be wrong.'
        );
    }

    public function testUnknownTopLevelKeysAreDropped(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $result = $validator->normalize('probe', ['title' => 'Compare', 'hackerField' => 'x']);

        $this->assertSame(['title' => 'Compare'], $result);
    }

    public function testUnknownKeysInsideRepeaterItemsAreDropped(): void
    {
        $validator = $this->validatorFor([
            $this->field('ranges', 'repeater', [
                'fields' => [$this->field('key', 'text')],
            ]),
        ]);

        $result = $validator->normalize('probe', [
            'ranges' => [['key' => 'windsor', 'junk' => 'x']],
        ]);

        $this->assertSame([['key' => 'windsor']], $result['ranges']);
    }

    public function testDeclaredKeysAreNotInventedWhenAbsent(): void
    {
        $validator = $this->validatorFor([
            $this->field('title', 'text'),
            $this->field('subtitle', 'textarea'),
        ]);

        $result = $validator->normalize('probe', ['title' => 'Compare']);

        $this->assertSame(['title' => 'Compare'], $result);
        $this->assertArrayNotHasKey('subtitle', $result, 'Absent stays absent; it is not defaulted here.');
    }

    public function testNonStringValuesSurviveUntouched(): void
    {
        $validator = $this->validatorFor([
            $this->field('scaffold', 'boolean'),
            $this->field('xPct', 'number'),
        ]);

        $result = $validator->normalize('probe', ['scaffold' => true, 'xPct' => 33.5]);

        $this->assertTrue($result['scaffold']);
        $this->assertSame(33.5, $result['xPct']);
    }

    public function testUnknownTypeIsReturnedUnchanged(): void
    {
        $validator = $this->validatorFor([$this->field('title', 'text')]);

        $data = ['anything' => '<b>at all</b>'];

        $this->assertSame($data, $validator->normalize('no-such-type', $data));
    }
}
