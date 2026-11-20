<?php

declare(strict_types=1);

namespace Elme\Search\Model\Facet;

use Magento\Framework\Config\ConverterInterface;

/**
 * etc/elme_search_facets.xml -> ['facets' => [code => [attribute => value]]].
 */
class Converter implements ConverterInterface
{
    public function convert($source): array
    {
        $facets = [];
        /** @var \DOMElement $node */
        foreach ($source->getElementsByTagName('facet') as $node) {
            $row = [];
            foreach ($node->attributes as $attribute) {
                $row[$attribute->nodeName] = $attribute->nodeValue;
            }
            $facets[$row['code']] = $row;
        }
        return ['facets' => $facets];
    }
}
