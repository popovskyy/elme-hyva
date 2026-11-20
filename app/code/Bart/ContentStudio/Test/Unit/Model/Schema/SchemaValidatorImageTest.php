<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `image` fields fall through validateField()'s match to `default => null`,
 * so today they accept absolutely anything. A url field one line away is
 * checked; an image source is not.
 *
 * The rule is deliberately about the *scheme*, not the file extension:
 * Cloudinary serves images from paths like
 * /image/upload/c_limit,f_auto/v1/FOO with no suffix at all, so an extension
 * check would reject real, working images.
 *
 * @see SchemaValidator
 */
class SchemaValidatorImageTest extends TestCase
{
    use SchemaFixtureTrait;

    /**
     * @param mixed $value
     * @param bool $expectedValid
     */
    #[DataProvider('imageUrlProvider')]
    public function testImageUrlSafety(mixed $value, bool $expectedValid): void
    {
        $validator = $this->validatorFor([$this->field('imageUrl', 'image')]);

        $errors = $validator->validate('probe', ['imageUrl' => $value]);

        $this->assertSame(
            $expectedValid,
            $errors === [],
            sprintf('%s should be %s', var_export($value, true), $expectedValid ? 'accepted' : 'rejected')
        );
    }

    /**
     * @return array<string, array{0: mixed, 1: bool}>
     */
    public static function imageUrlProvider(): array
    {
        return [
            'cloudinary with extension' => [
                'https://res.cloudinary.com/dmndjbrsi/image/upload/v1757300209/BED%20BUILDER/HADCEB_1.jpg',
                true,
            ],
            'cloudinary transformation without extension' => [
                'https://res.cloudinary.com/dmndjbrsi/image/upload/c_limit,f_auto,q_auto/v1/WINDSOR/INTRO',
                true,
            ],
            'media relative path' => ['/media/wysiwyg/mattress.jpg', true],
            'http' => ['http://example.com/a.png', true],
            'query string and no extension' => ['https://example.com/img?id=4', true],
            'javascript scheme' => ['javascript:alert(1)', false],
            'data scheme' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=', false],
            'vbscript scheme' => ['vbscript:msgbox(1)', false],
            'bare host' => ['example.com/a.jpg', false],
            'protocol relative' => ['//evil.com/a.jpg', false],
            'not a string' => [123, false],
            // "#" is the compare-mattress placeholder for a link with no
            // destination. It is meaningless as an image source.
            'hash placeholder' => ['#', false],
        ];
    }

    public function testEmptyImageIsAccepted(): void
    {
        $validator = $this->validatorFor([$this->field('imageUrl', 'image')]);

        $this->assertSame([], $validator->validate('probe', ['imageUrl' => '']));
    }

    public function testRequiredImageStillReportsMissing(): void
    {
        $validator = $this->validatorFor([$this->field('imageUrl', 'image', ['required' => true])]);

        $this->assertArrayHasKey('imageUrl', $validator->validate('probe', ['imageUrl' => '']));
    }
}
