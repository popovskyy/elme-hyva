<?php

declare(strict_types=1);

namespace Elme\Checkout\Api\Data;

/**
 * The full checkout state: one entry per section provider, in pool order.
 */
interface CheckoutStateInterface
{
    /**
     * Section code => section data. A section whose provider failed is null and has an entry in getErrors().
     *
     * @return mixed[]
     */
    public function getSections(): array;

    /**
     * Section code => message shown in that section.
     *
     * @return string[]
     */
    public function getErrors(): array;

    /**
     * The state as sent to the Alpine store.
     *
     * @return mixed[]
     */
    public function toArray(): array;
}
