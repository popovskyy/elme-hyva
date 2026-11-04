<?php

declare(strict_types=1);

namespace Elme\Theme\Observer;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Product\ProductList\Toolbar as ToolbarModel;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-20 QA #2 / SEO: out-of-range ?p= must not 200 an empty grid (soft-404). Redirect to the last valid page.
 */
class ClampCategoryPage implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly HttpResponse $response,
        private readonly ActionFlag $actionFlag,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(Observer $observer): void
    {
        $page = (int) $this->request->getParam(ToolbarModel::PAGE_PARM_NAME, 1);
        if ($page <= 1) {
            return;
        }

        $categoryId = (int) $this->request->getParam('id');
        if ($categoryId <= 0) {
            return;
        }

        try {
            $category = $this->categoryRepository->get(
                $categoryId,
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException) {
            return;
        }

        $limit = $this->resolveLimit();
        $total = (int) $category->getProductCount();
        $lastPage = max(1, (int) ceil($total / $limit));

        if ($page <= $lastPage) {
            return;
        }

        $query = $this->request->getQuery()->toArray();
        if ($lastPage > 1) {
            $query[ToolbarModel::PAGE_PARM_NAME] = (string) $lastPage;
        } else {
            unset($query[ToolbarModel::PAGE_PARM_NAME]);
        }

        $url = $category->getUrl();
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->setRedirect($url, 302);
    }

    private function resolveLimit(): int
    {
        $default = (int) $this->scopeConfig->getValue(
            'catalog/frontend/grid_per_page',
            ScopeInterface::SCOPE_STORE
        );
        $default = $default > 0 ? $default : 24;

        $valuesRaw = (string) $this->scopeConfig->getValue(
            'catalog/frontend/grid_per_page_values',
            ScopeInterface::SCOPE_STORE
        );
        $allowed = array_values(array_filter(array_map('trim', explode(',', $valuesRaw))));

        $requested = (string) $this->request->getParam(ToolbarModel::LIMIT_PARAM_NAME, '');
        if ($requested !== '' && in_array($requested, $allowed, true)) {
            return max(1, (int) $requested);
        }

        return $default;
    }
}
