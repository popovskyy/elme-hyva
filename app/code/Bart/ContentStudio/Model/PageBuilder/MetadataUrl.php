<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\PageBuilder;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\Config\ContentType\AdditionalData\ProviderInterface;

/**
 * URL of the entry metadata endpoint, for the "Content Studio" Page Builder
 * type (content_type/content_studio.xml `additional_data`).
 */
class MetadataUrl implements ProviderInterface
{
    /**
     * @param UrlInterface $urlBuilder Backend URL builder (admin area).
     */
    public function __construct(private readonly UrlInterface $urlBuilder)
    {
    }

    /**
     * @inheritDoc
     */
    public function getData(string $itemName): array
    {
        return [$itemName => $this->urlBuilder->getUrl('bart_content_studio/pagebuilder/metadata')];
    }
}
