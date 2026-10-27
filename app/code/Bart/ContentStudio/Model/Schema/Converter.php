<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

use Magento\Framework\Config\ConverterInterface;

/**
 * Converts the merged content_types.xml DOM into a plain array keyed by type code.
 *
 * Field nodes are recursive (group/repeater nest further fields, blocks nests
 * <allow>), so conversion walks each <type> depth-first rather than relying
 * on a flat xpath query.
 */
class Converter implements ConverterInterface
{
    private const BOOL_ATTRIBUTES = ['standalone', 'copyable', 'required', 'readonly'];
    private const NUMERIC_ATTRIBUTES = ['version', 'min', 'max', 'maxlength'];
    private const TYPE_ATTRIBUTES = [
        'code', 'label', 'standalone', 'copyable', 'group', 'icon', 'description', 'template', 'renderer', 'version',
    ];
    private const FIELD_ATTRIBUTES = [
        'name', 'type', 'label', 'required', 'readonly', 'width', 'default', 'min', 'max', 'maxlength',
        'label_field', 'renderer', 'description', 'recommended',
    ];

    /**
     * @inheritdoc
     */
    public function convert($source): array
    {
        $types = [];
        $xpath = new \DOMXPath($source);

        foreach ($xpath->query('/content_types/type') as $typeNode) {
            $type = $this->readAttributes($typeNode, self::TYPE_ATTRIBUTES);
            $type['fields'] = $this->convertFields($typeNode);
            $types[$type['code']] = $type;
        }

        return $types;
    }

    /**
     * @param \DOMElement $parent
     * @return array<int, array<string, mixed>>
     */
    private function convertFields(\DOMElement $parent): array
    {
        $fields = [];

        foreach ($parent->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->nodeName !== 'field') {
                continue;
            }
            $fields[] = $this->convertField($node);
        }

        return $fields;
    }

    /**
     * @param \DOMElement $node
     * @return array<string, mixed>
     */
    private function convertField(\DOMElement $node): array
    {
        $field = $this->readAttributes($node, self::FIELD_ATTRIBUTES);
        $field['options'] = $this->convertOptions($node);
        $field['allow'] = $this->convertAllow($node);
        $field['fields'] = $this->convertFields($node);

        return $field;
    }

    /**
     * @param \DOMElement $node
     * @return array<int, array{value: string, label: string}>
     */
    private function convertOptions(\DOMElement $node): array
    {
        $options = [];

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->nodeName !== 'option') {
                continue;
            }
            $options[] = [
                'value' => (string)$child->getAttribute('value'),
                'label' => (string)$child->getAttribute('label'),
            ];
        }

        return $options;
    }

    /**
     * Empty result means "allow every registered content type".
     *
     * @param \DOMElement $node
     * @return array<int, array{type: string|null, group: string|null}>
     */
    private function convertAllow(\DOMElement $node): array
    {
        $allow = [];

        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->nodeName !== 'allow') {
                continue;
            }
            $allow[] = [
                'type' => $child->getAttribute('type') !== '' ? $child->getAttribute('type') : null,
                'group' => $child->getAttribute('group') !== '' ? $child->getAttribute('group') : null,
            ];
        }

        return $allow;
    }

    /**
     * @param \DOMElement $node
     * @param string[] $names
     * @return array<string, mixed>
     */
    private function readAttributes(\DOMElement $node, array $names): array
    {
        $values = [];

        foreach ($names as $name) {
            $values[$name] = $this->readAttribute($node, $name);
        }

        return $values;
    }

    /**
     * @param \DOMElement $node
     * @param string $name
     * @return mixed
     */
    private function readAttribute(\DOMElement $node, string $name)
    {
        if (!$node->hasAttribute($name)) {
            return null;
        }

        $value = $node->getAttribute($name);

        if (in_array($name, self::BOOL_ATTRIBUTES, true)) {
            return $value === 'true' || $value === '1';
        }

        if (in_array($name, self::NUMERIC_ATTRIBUTES, true)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        return $value;
    }
}
