<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller;

use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Controller\Index\Index as IndexAction;
use Elme\StoreLocator\Controller\Index\View as ViewAction;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url;

/**
 * Clean URLs: /showroom-locator and /showroom-locator/{url_key}.
 */
class Router implements RouterInterface
{
    public const LIST_PATH = 'showroom-locator';

    public const ROUTE_NAME = 'elme_storelocator';

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly LocationRepositoryInterface $locationRepository
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim((string) $request->getPathInfo(), '/');
        if ($identifier === '' || !str_starts_with($identifier, self::LIST_PATH)) {
            return null;
        }

        // Exact list URL, or list with a trailing slash only.
        if ($identifier === self::LIST_PATH) {
            return $this->dispatch($request, 'index', $identifier, IndexAction::class);
        }

        if (!str_starts_with($identifier, self::LIST_PATH . '/')) {
            return null;
        }

        $urlKey = urldecode(trim(substr($identifier, strlen(self::LIST_PATH)), '/'));
        if ($urlKey === '' || str_contains($urlKey, '/')) {
            return null;
        }

        try {
            $location = $this->locationRepository->getByUrlKey($urlKey);
        } catch (NoSuchEntityException) {
            return null;
        }

        if (!$location->getIsActive()) {
            return null;
        }

        $request->setParam('url_key', $urlKey);

        return $this->dispatch($request, 'view', $identifier, ViewAction::class);
    }

    /**
     * @param class-string<ActionInterface> $actionClass
     */
    private function dispatch(
        RequestInterface $request,
        string $action,
        string $identifier,
        string $actionClass
    ): ActionInterface {
        $request->setModuleName(self::ROUTE_NAME)
            ->setControllerName('index')
            ->setActionName($action)
            ->setRouteName(self::ROUTE_NAME)
            ->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, $identifier);
        $request->setDispatched();

        return $this->actionFactory->create($actionClass);
    }
}
