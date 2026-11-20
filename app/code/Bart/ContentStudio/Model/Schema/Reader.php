<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Config\ValidationStateInterface;

/**
 * Reads and merges every module's etc/content_types.xml.
 *
 * Only the top-level <type> node is merge-aware (by `code`): a module cannot
 * currently extend fields of a <type> declared by another module. That is
 * an acceptable limit for a lean schema builder - types are expected to be
 * declared whole by a single module.
 */
class Reader extends Filesystem
{
    /**
     * @var array<string, string>
     */
    protected $_idAttributes = [
        '/content_types/type' => 'code',
    ];

    /**
     * @param FileResolverInterface $fileResolver
     * @param Converter $converter
     * @param SchemaLocatorInterface $schemaLocator
     * @param ValidationStateInterface $validationState
     * @param string $fileName
     * @param array<string, string> $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocatorInterface $schemaLocator,
        ValidationStateInterface $validationState,
        $fileName = 'content_types.xml',
        $idAttributes = [],
        $domDocumentClass = Dom::class,
        $defaultScope = 'global'
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope
        );
    }
}
