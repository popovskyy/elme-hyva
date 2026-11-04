<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Block\Adminhtml\Location\Edit;

use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Magento\Backend\Block\Widget\Context;
use Magento\Framework\Exception\NoSuchEntityException;

class GenericButton
{
    public function __construct(
        private readonly Context $context,
        private readonly LocationRepositoryInterface $locationRepository
    ) {
    }

    public function getLocationId(): ?int
    {
        try {
            $id = (int) $this->context->getRequest()->getParam('location_id');
            if (!$id) {
                return null;
            }

            return $this->locationRepository->getById($id)->getLocationId();
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
