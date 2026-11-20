<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Api\SearchUnavailableException;
use Elme\Search\Model\Config;
use Elme\Search\Model\SkuQuery;
use OpenSearch\Client as OpenSearchClient;
use OpenSearch\ClientBuilder;

/**
 * Thin wrapper over the opensearch-php client: the calls our adapter needs, every failure as
 * SearchUnavailableException. Short timeouts so a dead cluster cannot hang a storefront page.
 */
class Client
{
    private ?OpenSearchClient $client = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function search(string $index, array $body): array
    {
        return $this->call(fn (OpenSearchClient $c) => $c->search(['index' => $index, 'body' => $body]));
    }

    /**
     * @param array $operations bulk body (action line, source line, ...)
     */
    public function bulk(array $operations, bool $refresh = false): void
    {
        if ($operations === []) {
            return;
        }
        $params = ['body' => $operations];
        if ($refresh) {
            $params['refresh'] = 'true';
        }
        $response = $this->call(fn (OpenSearchClient $c) => $c->bulk($params));
        if (!empty($response['errors'])) {
            $first = '';
            foreach ($response['items'] ?? [] as $item) {
                $result = reset($item);
                if (isset($result['error'])) {
                    $first = json_encode($result['error']);
                    break;
                }
            }
            throw new SearchUnavailableException('OpenSearch bulk request had errors: ' . $first);
        }
    }

    public function createIndex(string $index, array $body): void
    {
        $this->call(fn (OpenSearchClient $c) => $c->indices()->create(['index' => $index, 'body' => $body]));
    }

    public function refresh(string $index): void
    {
        $this->call(fn (OpenSearchClient $c) => $c->indices()->refresh(['index' => $index]));
    }

    public function deleteIndex(string $index): void
    {
        $this->call(fn (OpenSearchClient $c) => $c->indices()->delete(['index' => $index]));
    }

    public function indexExists(string $index): bool
    {
        return (bool)$this->call(fn (OpenSearchClient $c) => $c->indices()->exists(['index' => $index]));
    }

    /**
     * @return string[] the concrete indices behind an alias
     */
    public function getAliasTargets(string $alias): array
    {
        $exists = (bool)$this->call(fn (OpenSearchClient $c) => $c->indices()->existsAlias(['name' => $alias]));
        if (!$exists) {
            return [];
        }
        $response = $this->call(fn (OpenSearchClient $c) => $c->indices()->getAlias(['name' => $alias]));
        return array_map('strval', array_keys($response));
    }

    /**
     * Versioned indices of an alias that it does not point at: the ones a full rebuild is filling right now.
     *
     * @return string[]
     */
    public function getBuildingIndices(string $alias): array
    {
        $building = [];
        foreach ($this->listVersionedIndices($alias) as $index => $info) {
            if (!isset($info['aliases'][$alias])) {
                $building[] = (string)$index;
            }
        }
        return $building;
    }

    /**
     * Drop leftover versioned indices from a killed rebuild. Only ones older than $minAgeSeconds, so a rebuild
     * running in parallel is not touched. Age comes from the `_vYmdHis` suffix in the index name (see IndexName),
     * falling back to OpenSearch creation_date.
     *
     * @return string[] deleted index names
     */
    public function deleteOrphanBuildingIndices(string $alias, int $minAgeSeconds = 3600): array
    {
        $cutoffTs = time() - $minAgeSeconds;
        $deleted = [];
        foreach ($this->listVersionedIndices($alias) as $index => $info) {
            if (isset($info['aliases'][$alias])) {
                continue;
            }
            $createdTs = $this->versionTimestamp((string)$index);
            if ($createdTs === null) {
                $createdMs = (int)($info['settings']['index']['creation_date'] ?? 0);
                $createdTs = $createdMs > 0 ? (int)floor($createdMs / 1000) : null;
            }
            if ($createdTs === null || $createdTs >= $cutoffTs) {
                continue;
            }
            $this->deleteIndex((string)$index);
            $deleted[] = (string)$index;
        }
        return $deleted;
    }

    /**
     * elme_products_1_v202611131941253377 -> unix time of the YmdHis part.
     */
    private function versionTimestamp(string $index): ?int
    {
        if (!preg_match('/_v(\d{14})/', $index, $match)) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('YmdHis', $match[1]);
        return $dt instanceof \DateTimeImmutable ? $dt->getTimestamp() : null;
    }

    /**
     * True when at least one document has this exact SKU or a SKU that starts with it (compact form included).
     * Used so "l-shaped" keeps family collapse while "Z-ARD" / "218AREDIN" still list every matching SKU.
     */
    public function hasSkuMatch(string $index, string $query): bool
    {
        $query = trim($query);
        if ($query === '') {
            return false;
        }
        $lower = mb_strtolower($query);
        $compact = SkuQuery::compact($query);
        $should = [
            ['term' => ['sku' => $lower]],
            ['prefix' => ['sku' => $lower]],
        ];
        if ($compact !== '') {
            $should[] = ['term' => ['sku.compact' => $compact]];
            $should[] = ['prefix' => ['sku.compact' => $compact]];
        }
        $response = $this->search($index, [
            'size' => 0,
            'track_total_hits' => true,
            'query' => ['bool' => ['should' => $should, 'minimum_should_match' => 1]],
        ]);
        $total = $response['hits']['total'] ?? 0;
        return (int)(is_array($total) ? ($total['value'] ?? 0) : $total) > 0;
    }

    /**
     * @return array<string, array>
     */
    private function listVersionedIndices(string $alias): array
    {
        $response = $this->call(fn (OpenSearchClient $c) => $c->indices()->get([
            'index' => $alias . '_v*',
            'allow_no_indices' => true,
            'ignore_unavailable' => true,
        ]));
        return is_array($response) ? $response : [];
    }

    /**
     * Point the alias at $newIndex only, atomically.
     *
     * @param string[] $oldIndices
     */
    public function swapAlias(string $alias, string $newIndex, array $oldIndices): void
    {
        $actions = [['add' => ['index' => $newIndex, 'alias' => $alias]]];
        foreach ($oldIndices as $old) {
            $actions[] = ['remove' => ['index' => $old, 'alias' => $alias]];
        }
        $this->call(
            fn (OpenSearchClient $c) => $c->indices()->updateAliases(['body' => ['actions' => $actions]])
        );
    }

    private function call(callable $fn): mixed
    {
        try {
            return $fn($this->getClient());
        } catch (SearchUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new SearchUnavailableException('OpenSearch: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getClient(): OpenSearchClient
    {
        if ($this->client === null) {
            $this->client = ClientBuilder::fromConfig($this->config->getClientOptions(), true);
        }
        return $this->client;
    }
}
