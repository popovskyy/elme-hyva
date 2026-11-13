<?php

declare(strict_types=1);

namespace Elme\Search\Model\Indexer;

use Elme\Search\Model\OpenSearch\Client;
use Elme\Search\Model\OpenSearch\IndexName;
use Elme\Search\Model\OpenSearch\IndexSchema;

/**
 * Writes documents to elme_products_<store>. A full rebuild fills a new versioned index and swaps the alias,
 * so the storefront never reads a half-built index.
 */
class IndexWriter
{
    public function __construct(
        private readonly Client $client,
        private readonly IndexName $indexName,
        private readonly IndexSchema $schema
    ) {
    }

    /**
     * @param iterable<array<int, array>> $batches
     * @return int number of documents
     */
    public function rebuild(int $storeId, iterable $batches): int
    {
        $alias = $this->indexName->getAlias($storeId);
        $index = $this->indexName->newVersion($storeId);
        $this->client->createIndex($index, $this->schema->getBody());
        $count = 0;
        $old = [];
        try {
            foreach ($batches as $batch) {
                $this->client->bulk($this->indexOperations($index, $batch));
                $count += count($batch);
            }
            $this->client->refresh($index);
            $old =$this->client->getAliasTargets($alias);
            if ($old === [] && $this->client->indexExists($alias)) {
                // a concrete index squats the alias name: remove it so the alias can be created
                $this->client->deleteIndex($alias);
            }
            $this->client->swapAlias($alias, $index, $old);
        } catch (\Throwable $e) {
            $this->client->deleteIndex($index);
            throw $e;
        }
        foreach ($old as $oldIndex) {
            if ($oldIndex !== $index) {
                $this->client->deleteIndex($oldIndex);
            }
        }
        return $count;
    }

    /**
     * Update on save / mview: write these documents and remove the ids that are no longer eligible.
     *
     * @param array<int, array> $documents
     * @param int[] $removeIds
     */
    public function update(int $storeId, array $documents, array $removeIds): void
    {
        $alias = $this->indexName->getAlias($storeId);
        if (!$this->client->indexExists($alias)) {
            return; // never built: the first bin/magento elme:search:reindex creates it
        }
        $operations = $this->indexOperations($alias, $documents);
        foreach ($removeIds as $id) {
            $operations[] = ['delete' => ['_index' => $alias, '_id' => (string)$id]];
        }
        $this->client->bulk($operations, true);
    }

    private function indexOperations(string $index, array $documents): array
    {
        $operations = [];
        foreach ($documents as $id => $document) {
            $operations[] = ['index' => ['_index' => $index, '_id' => (string)$id]];
            $operations[] = $document;
        }
        return $operations;
    }
}
