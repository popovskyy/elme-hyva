<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Api\SearchUnavailableException;
use Elme\Search\Model\Config;
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
