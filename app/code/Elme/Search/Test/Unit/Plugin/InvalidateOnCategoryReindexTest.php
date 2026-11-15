<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Plugin;

use Elme\Search\Model\Indexer\ProductIndexer;
use Elme\Search\Plugin\InvalidateOnCategoryReindex;
use Magento\Catalog\Model\Indexer\Category\Product as CategoryProductIndexer;
use Magento\Catalog\Model\Indexer\Product\Category as ProductCategoryIndexer;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InvalidateOnCategoryReindexTest extends TestCase
{
    private IndexerInterface&MockObject $indexer;
    private InvalidateOnCategoryReindex $plugin;

    protected function setUp(): void
    {
        $this->indexer = $this->createMock(IndexerInterface::class);
        $this->indexer->method('isInvalid')->willReturn(false);
        $registry = $this->createMock(IndexerRegistry::class);
        $registry->method('get')->with(ProductIndexer::INDEXER_ID)->willReturn($this->indexer);
        $this->plugin = new InvalidateOnCategoryReindex($registry, $this->createStub(LoggerInterface::class));
    }

    public function testCategoryReindexInvalidatesTheSearchIndex(): void
    {
        $this->indexer->expects($this->exactly(3))->method('invalidate');
        $subject = $this->createStub(CategoryProductIndexer::class);

        $this->plugin->afterExecute($subject, null);
        $this->plugin->afterExecuteList($subject, null);
        $this->plugin->afterExecuteRow($subject, null);
    }

    public function testProductSideReindexIsLeftToOurOwnMview(): void
    {
        $this->indexer->expects($this->never())->method('invalidate');
        $subject = $this->createStub(ProductCategoryIndexer::class);

        $this->plugin->afterExecute($subject, null);
        $this->plugin->afterExecuteList($subject, null);
        $this->plugin->afterExecuteRow($subject, null);
    }
}
