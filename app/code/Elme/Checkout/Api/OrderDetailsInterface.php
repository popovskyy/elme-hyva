<?php

declare(strict_types=1);

namespace Elme\Checkout\Api;

/**
 * The order details the customer gives at checkout. These are also the column names on quote, sales_order and
 * sales_order_grid, the extension attribute codes and the order email variables.
 */
interface OrderDetailsInterface
{
    public const DELIVERY_INSTRUCTIONS = 'delivery_instructions';
    public const DELIVERY_INSTRUCTIONS_MAX_LENGTH = 250;
}
