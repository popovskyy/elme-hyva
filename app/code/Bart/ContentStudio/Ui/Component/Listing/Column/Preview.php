<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Ui\Component\Listing\Column;

use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * The eye button in each grid row. Hover shows the entry's title in a small
 * popover, click opens the preview modal (`js/grid/preview.js`).
 *
 * Rendered as HTML through `ui/grid/cells/html` so the button carries its own
 * data - the grid re-renders rows on every filter and page change, and a
 * delegated listener reading attributes survives that where per-row
 * Knockout bindings would need a custom column component.
 */
class Preview extends Column
{
    private const DATA_PATH = 'bart_content_studio/entry/previewData';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param SchemaConfig $schemaConfig
     * @param Escaper $escaper
     * @param array<mixed> $components
     * @param array<mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly SchemaConfig $schemaConfig,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<mixed> $dataSource
     * @return array<mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['entry_id'])) {
                continue;
            }

            $item[$this->getData('name')] = $this->button($item);
        }

        return $dataSource;
    }

    /**
     * @param array<string, mixed> $item
     * @return string
     */
    private function button(array $item): string
    {
        $type = $this->schemaConfig->getType((string)($item['type'] ?? ''));
        $title = trim((string)($item['title'] ?? ''));
        $identifier = (string)($item['identifier'] ?? '');
        $url = $this->urlBuilder->getUrl(self::DATA_PATH, ['entry_id' => (int)$item['entry_id']]);

        return sprintf(
            '<button type="button" class="studio-grid-preview" data-cs-preview="%s" data-title="%s"'
            . ' data-identifier="%s" data-type-label="%s" aria-label="%s">%s</button>',
            $this->escaper->escapeHtmlAttr($url),
            $this->escaper->escapeHtmlAttr($title !== '' ? $title : $identifier),
            $this->escaper->escapeHtmlAttr($identifier),
            $this->escaper->escapeHtmlAttr((string)($type['label'] ?? $item['type'] ?? '')),
            $this->escaper->escapeHtmlAttr((string)__('Preview %1', $identifier)),
            '<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" focusable="false">'
            . '<path d="M10 4.5C5.5 4.5 2.3 7.6 1 10c1.3 2.4 4.5 5.5 9 5.5s7.7-3.1 9-5.5c-1.3-2.4-4.5-5.5-9-5.5z"'
            . ' fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>'
            . '<circle cx="10" cy="10" r="2.75" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
        );
    }
}
