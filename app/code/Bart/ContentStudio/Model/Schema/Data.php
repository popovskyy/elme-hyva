<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data as ConfigData;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Cached, merged content_types.xml data (config cache, flushed via cache:clean config).
 */
class Data extends ConfigData
{
    private const CACHE_ID = 'bart_content_studio_content_types';

    /**
     * @param Reader $reader
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Reader $reader,
        CacheInterface $cache,
        SerializerInterface $serializer
    ) {
        parent::__construct($reader, $cache, self::CACHE_ID, $serializer, [ConfigCache::CACHE_TAG]);
    }
}
