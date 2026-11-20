<?php

declare(strict_types=1);

namespace Elme\Checkout\Api;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Builds one section of the checkout state. Add one through di.xml on Elme\Checkout\Model\StateBuilder.
 */
interface SectionProviderInterface
{
    /**
     * @param CartInterface $quote
     * @return mixed[] JSON-safe data of the section
     */
    public function getData(CartInterface $quote): array;
}
