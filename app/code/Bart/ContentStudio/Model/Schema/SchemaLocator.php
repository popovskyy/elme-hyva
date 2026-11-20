<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;

/**
 * Locates the content_types.xsd shipped with this module.
 */
class SchemaLocator implements SchemaLocatorInterface
{
    /**
     * @var string
     */
    private string $schema;

    /**
     * @param ModuleDirReader $moduleReader
     */
    public function __construct(ModuleDirReader $moduleReader)
    {
        $etcDir = $moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Bart_ContentStudio');
        $this->schema = $etcDir . '/content_types.xsd';
    }

    /**
     * @inheritdoc
     */
    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * @inheritdoc
     */
    public function getPerFileSchema(): string
    {
        return $this->schema;
    }
}
