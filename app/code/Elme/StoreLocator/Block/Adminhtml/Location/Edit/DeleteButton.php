<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Block\Adminhtml\Location\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        $locationId = $this->getLocationId();
        if (!$locationId) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s')",
                __('Are you sure you want to delete this location?'),
                $this->getUrl('*/*/delete', ['location_id' => $locationId])
            ),
            'sort_order' => 20,
        ];
    }
}
