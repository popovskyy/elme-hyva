<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class LocationActions extends Column
{
    private const URL_PATH_EDIT = 'elme_storelocator/location/edit';
    private const URL_PATH_DELETE = 'elme_storelocator/location/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['location_id'])) {
                continue;
            }
            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['location_id' => $item['location_id']]),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_DELETE,
                        ['location_id' => $item['location_id']]
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete %1', $item['name'] ?? ''),
                        'message' => __('Are you sure you want to delete this location?'),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}
