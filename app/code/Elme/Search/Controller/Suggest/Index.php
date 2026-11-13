<?php

declare(strict_types=1);

namespace Elme\Search\Controller\Suggest;

use Elme\Search\Api\Data\SuggestCategory;
use Elme\Search\Api\Data\SuggestProduct;
use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Api\SearchUnavailableException;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /elme-search/suggest?q=vase - header autocomplete (FE-22) from ProductSearchInterface::suggest().
 *
 * Response: {"products": [{"id", "sku", "name", "url", "image"}], "categories": [{"id", "name", "url"}],
 * "queries": ["..."]}. No prices (they are per customer), so the answer is the same for everyone and may be
 * cached publicly for a few minutes. When the engine cannot answer, the lists are empty: the form still submits
 * to the search results page.
 */
class Index implements HttpGetActionInterface
{
    public const MIN_LENGTH = 2;
    private const MAX_LENGTH = 128;
    private const PRODUCT_LIMIT = 6;
    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductSearchInterface $productSearch,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $query = trim(mb_substr((string) $this->request->getParam('q', ''), 0, self::MAX_LENGTH));
        $data = ['products' => [], 'categories' => [], 'queries' => []];

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return $result->setData($data);
        }

        try {
            $suggest = $this->productSearch->suggest(
                $query,
                self::PRODUCT_LIMIT,
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (SearchUnavailableException $e) {
            $this->logger->error('Elme_Search: suggest failed: ' . $e->getMessage());
            $result->setHeader('Cache-Control', 'no-store', true);
            return $result->setData($data);
        }

        $data['products'] = array_map(static fn (SuggestProduct $product): array => [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'url' => $product->url,
            'image' => $product->image,
        ], $suggest->products);
        $data['categories'] = array_map(static fn (SuggestCategory $category): array => [
            'id' => $category->id,
            'name' => $category->name,
            'url' => $category->url,
        ], $suggest->categories);
        $data['queries'] = array_values($suggest->queries);

        $result->setHeader('Cache-Control', 'public, max-age=' . self::CACHE_SECONDS, true);
        $result->setHeader('Pragma', 'cache', true);
        return $result->setData($data);
    }
}
