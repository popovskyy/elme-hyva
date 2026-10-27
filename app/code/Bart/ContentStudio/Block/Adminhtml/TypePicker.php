<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml;

use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Content-type picker shown when creating a new entry.
 */
class TypePicker extends Template
{
    /**
     * Maps a type's `icon` attribute (etc/content_types.xml) to a symbol id
     * in the sprite top-bar.phtml renders - the two templates share a page,
     * so `<use>` resolves regardless of which one draws it. Unmapped names
     * become `studio-icon-{name}` (same convention as `dom.icon()` in JS).
     * Absent icons fall back to {@see DEFAULT_ICON_SYMBOL} rather than
     * rendering nothing, so a future type is never left illegible next to
     * one that has an icon.
     */
    private const ICON_SYMBOL_MAP = [
        'compare' => 'studio-icon-target',
        'article' => 'studio-icon-article',
        'pin' => 'studio-icon-pin',
    ];

    private const DEFAULT_ICON_SYMBOL = 'studio-icon-list';

    /**
     * @param Context $context
     * @param SchemaConfig $schemaConfig
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly SchemaConfig $schemaConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getStandaloneTypes(): array
    {
        return $this->schemaConfig->getStandaloneTypes();
    }

    /**
     * Types that do not exist yet, shown locked underneath the real ones so a
     * content manager can see what is coming without mistaking it for
     * something they can build today.
     *
     * Static on purpose: these are not content types (nothing is registered,
     * there is no schema, no template) so they have no home in
     * content_types.xml - that file describes what the module can render, not
     * a roadmap.
     *
     * @return array<int, array{label: string, description: string, icon: string}>
     */
    public function getComingSoonTypes(): array
    {
        return [
            [
                'label' => (string)__('Shop The Look'),
                'description' => (string)__('Lifestyle image with product pins - tap a pin to open the product.'),
                'icon' => 'pin',
            ],
        ];
    }

    /**
     * @param string $typeCode
     * @return string
     */
    public function getEditUrl(string $typeCode): string
    {
        return $this->getUrl('bart_content_studio/entry/edit', ['type' => $typeCode]);
    }

    /**
     * @param array<string, mixed> $type
     * @return string
     */
    public function getIconSymbol(array $type): string
    {
        $icon = (string)($type['icon'] ?? '');

        if ($icon === '') {
            return self::DEFAULT_ICON_SYMBOL;
        }

        return self::ICON_SYMBOL_MAP[$icon] ?? ('studio-icon-' . $icon);
    }
}
