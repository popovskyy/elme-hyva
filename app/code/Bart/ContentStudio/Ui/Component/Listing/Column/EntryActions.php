<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit/Delete actions for the Content Studio entry grid.
 */
class EntryActions extends Column
{
    private const EDIT_PATH = 'bart_content_studio/entry/edit';
    private const DELETE_PATH = 'bart_content_studio/entry/delete';
    private const EXPORT_PATH = 'bart_content_studio/entry/export';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param array<mixed> $components
     * @param array<mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(self::EDIT_PATH, ['entry_id' => $item['entry_id']]),
                    'label' => __('Edit'),
                ],
                'export' => [
                    'href' => $this->urlBuilder->getUrl(self::EXPORT_PATH, ['entry_id' => $item['entry_id']]),
                    'label' => __('Export'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(self::DELETE_PATH, ['entry_id' => $item['entry_id']]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $item['identifier'] ?? ''),
                        'message' => __('Are you sure you want to delete this entry?'),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
