<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Swatch colour of a colour group (the Colour filter, FE-21).
 *
 * Facet options carry only a label, so the colour comes from a token per colour group
 * (hyva.config.json: color.swatch-*, styled in web/tailwind/elme/plp-filters.css as .elme-swatch--<name>).
 * The groups and colours are the ones of the live site's Colour filter. Labels that are not a group yet
 * (this instance falls back to the colour name until colour_group exists) take the first group word in them.
 */
class ColourSwatch implements ArgumentInterface
{
    private const OTHER = 'other';

    /** Exact group label (lower case) => swatch name */
    private const GROUPS = [
        'beige' => 'beige',
        'brown' => 'brown',
        'white' => 'white',
        'black' => 'black',
        'red' => 'red',
        'green' => 'green',
        'light grey' => 'light-grey',
        'mid grey' => 'mid-grey',
        'yellow' => 'yellow',
        'charcoal' => 'charcoal',
        'blue' => 'blue',
        'other' => 'other',
    ];

    /** Word in a colour name => swatch name, checked in this order */
    private const WORDS = [
        'charcoal' => 'charcoal',
        'light grey' => 'light-grey',
        'light gray' => 'light-grey',
        'grey' => 'mid-grey',
        'gray' => 'mid-grey',
        'black' => 'black',
        'white' => 'white',
        'ivory' => 'white',
        'beige' => 'beige',
        'natural' => 'beige',
        'cream' => 'beige',
        'sand' => 'beige',
        'oat' => 'beige',
        'brown' => 'brown',
        'walnut' => 'brown',
        'tan' => 'brown',
        'green' => 'green',
        'sage' => 'green',
        'olive' => 'green',
        'blue' => 'blue',
        'navy' => 'blue',
        'red' => 'red',
        'rust' => 'red',
        'yellow' => 'yellow',
        'mustard' => 'yellow',
        'gold' => 'yellow',
    ];

    public function getSwatchName(string $label): string
    {
        $key = strtolower(trim($label));
        if (isset(self::GROUPS[$key])) {
            return self::GROUPS[$key];
        }
        foreach (self::WORDS as $word => $swatch) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $key)) {
                return $swatch;
            }
        }
        return self::OTHER;
    }
}
