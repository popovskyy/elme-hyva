<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Controller\Suggest;

use Elme\Search\Api\Data\SuggestCategory;
use Elme\Search\Api\Data\SuggestProduct;
use Elme\Search\Api\Data\SuggestResult;
use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Api\SearchUnavailableException;
use Elme\Search\Controller\Suggest\Index;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class IndexTest extends TestCase
{
    private RequestInterface&MockObject $request;
    private ProductSearchInterface&MockObject $search;
    private Json&MockObject $json;
    private array $data = [];
    private array $headers = [];

    protected function setUp(): void
    {
        $this->request = $this->createMock(RequestInterface::class);
        $this->search = $this->createMock(ProductSearchInterface::class);
        $this->json = $this->createMock(Json::class);
        $this->json->method('setData')->willReturnCallback(function ($data) {
            $this->data = $data;
            return $this->json;
        });
        $this->json->method('setHeader')->willReturnCallback(function ($name, $value) {
            $this->headers[$name] = $value;
            return $this->json;
        });
    }

    private function controller(): Index
    {
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($this->json);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new Index(
            $this->request,
            $factory,
            $this->search,
            $storeManager,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testShortQueryDoesNotSearch(): void
    {
        $this->request->method('getParam')->willReturn(' v ');
        $this->search->expects($this->never())->method('suggest');

        $this->controller()->execute();

        $this->assertSame(['products' => [], 'categories' => [], 'queries' => []], $this->data);
    }

    public function testMapsSuggestResultAndCachesPublicly(): void
    {
        $this->request->method('getParam')->willReturn(' PL.186.GYGN ');
        $this->search->expects($this->once())->method('suggest')->with('PL.186.GYGN', 6, 1)->willReturn(
            new SuggestResult(
                [new SuggestProduct(5, 'PL.186.GYGN', 'Vase', 'https://x/vase.html', null)],
                [new SuggestCategory(3, 'Vases', 'https://x/vases.html')],
                ['Vase']
            )
        );

        $this->controller()->execute();

        $this->assertSame([
            'products' => [['id' => 5, 'sku' => 'PL.186.GYGN', 'name' => 'Vase', 'url' => 'https://x/vase.html', 'image' => null]],
            'categories' => [['id' => 3, 'name' => 'Vases', 'url' => 'https://x/vases.html']],
            'queries' => ['Vase'],
        ], $this->data);
        $this->assertStringStartsWith('public', $this->headers['Cache-Control']);
    }

    public function testEngineDownGivesEmptyListsNotCached(): void
    {
        $this->request->method('getParam')->willReturn('vase');
        $this->search->method('suggest')->willThrowException(new SearchUnavailableException('down'));

        $this->controller()->execute();

        $this->assertSame(['products' => [], 'categories' => [], 'queries' => []], $this->data);
        $this->assertSame('no-store', $this->headers['Cache-Control']);
    }
}
