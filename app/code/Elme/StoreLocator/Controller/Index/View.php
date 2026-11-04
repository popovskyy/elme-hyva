<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Index;

use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;

class View implements HttpGetActionInterface
{
    public const REGISTRY_KEY = 'elme_store_locator_location';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly PageFactory $resultPageFactory,
        private readonly ForwardFactory $resultForwardFactory,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly Registry $registry
    ) {
    }

    public function execute(): ResultInterface
    {
        $urlKey = trim((string) $this->request->getParam('url_key'));
        if ($urlKey === '') {
            return $this->forwardNoRoute();
        }

        try {
            $location = $this->locationRepository->getByUrlKey($urlKey);
        } catch (NoSuchEntityException) {
            return $this->forwardNoRoute();
        }

        if (!$location->getIsActive()) {
            return $this->forwardNoRoute();
        }

        $this->registry->register(self::REGISTRY_KEY, $location);

        return $this->resultPageFactory->create();
    }

    private function forwardNoRoute(): ResultInterface
    {
        $result = $this->resultForwardFactory->create();
        $result->setController('index');
        $result->forward('noroute');

        return $result;
    }
}
