<?php

declare(strict_types=1);

namespace Elme\Search\Model\Storefront;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\BucketInterface;
use Magento\Framework\Search\Response\Bucket;

/**
 * Aggregations handed to Magento's layered navigation when the listing comes from ProductSearchInterface: every
 * bucket exists and is empty, so Magento's own filters render nothing (facets come from the SearchFacets
 * ViewModel instead) and nothing throws "The bucket doesn't exist".
 */
class EmptyAggregation implements AggregationInterface
{
    /** @var array<string, BucketInterface> */
    private array $buckets = [];

    public function getBuckets()
    {
        return $this->buckets;
    }

    public function getBucket($bucketName)
    {
        return $this->buckets[$bucketName] ??= new Bucket((string)$bucketName, []);
    }

    public function getBucketNames()
    {
        return array_keys($this->buckets);
    }
}
