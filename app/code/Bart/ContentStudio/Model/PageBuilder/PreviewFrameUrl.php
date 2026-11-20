<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\PageBuilder;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\Config\ContentType\AdditionalData\ProviderInterface;

/**
 * Base URL of entry/previewFrame for the Page Builder stage. The stage appends
 * `identifier`, `store_id` and `bare` as a query string; the admin secret key
 * is part of the path, so query parameters do not invalidate it.
 */
class PreviewFrameUrl implements ProviderInterface
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
        return [$itemName => $this->urlBuilder->getUrl('bart_content_studio/entry/previewFrame')];
    }
}
