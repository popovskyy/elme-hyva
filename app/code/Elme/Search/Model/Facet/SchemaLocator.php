<?php

declare(strict_types=1);

namespace Elme\Search\Model\Facet;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;

class SchemaLocator implements SchemaLocatorInterface
{
    private string $schema;

    public function __construct(Reader $moduleReader)
    {
        $this->schema = $moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Elme_Search') . '/elme_search_facets.xsd';
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    public function getPerFileSchema(): string
    {
        return $this->schema;
    }
}
