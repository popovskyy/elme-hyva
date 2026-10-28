<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\Config;
use Bart\ContentStudio\Model\Schema\HtmlSanitizer;
use Bart\ContentStudio\Model\Schema\SchemaValidator;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Builds schema arrays in the exact shape Converter produces.
 *
 * The tests deliberately do not read the real etc/content_types.xml: a schema
 * edit would otherwise silently rewrite what they assert, and the point of
 * these tests is to pin the validator, not the mattress content type.
 */
trait SchemaFixtureTrait
{
    /**
     * Every attribute Converter emits, defaulting to null exactly as
     * Converter::readAttribute() does for an absent attribute.
     *
     * @param string $name
     * @param string $type
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function field(string $name, string $type, array $overrides = []): array
    {
        return array_merge([
            'name' => $name,
            'type' => $type,
            'label' => ucfirst($name),
            'required' => null,
            'readonly' => null,
            'width' => null,
            'default' => null,
            'min' => null,
            'max' => null,
            'maxlength' => null,
            'label_field' => null,
            'renderer' => null,
            'description' => null,
            'options' => [],
            'allow' => [],
            'fields' => [],
        ], $overrides);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    private function type(array $fields, string $code = 'probe'): array
    {
        return [
            'code' => $code,
            'label' => 'Probe',
            'standalone' => true,
            'copyable' => null,
            'group' => null,
            'icon' => null,
            'description' => null,
            'template' => null,
            'renderer' => null,
            'version' => 1,
            'fields' => $fields,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return SchemaValidator
     */
    private function validatorFor(array $fields, string $code = 'probe'): SchemaValidator
    {
        /** @var Config&MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('getType')->willReturnCallback(
            fn (string $requested): ?array => $requested === $code ? $this->type($fields, $code) : null
        );

        // The sanitizer is a real one, not a mock: it has no collaborators, and
        // a stub would let a `wysiwyg` assertion pass against markup the real
        // one would have stripped.
        return new SchemaValidator($config, new HtmlSanitizer());
    }
}
