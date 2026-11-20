<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * The configurable options JSON (PDP options, PLP swatches) carries the price of every child product.
 * Guests get it without prices; Hyvä's option script copes with empty price maps.
 */
class StripConfigurablePricesPlugin
{
    private const PRICE_KEYS = ['prices', 'optionPrices'];

    public function __construct(
        private readonly LoginState $loginState,
        private readonly Json $json
    ) {
    }

    public function afterGetJsonConfig(object $subject, $result)
    {
        if (!is_string($result) || $this->loginState->isLoggedIn()) {
            return $result;
        }

        $config = $this->json->unserialize($result);
        if (!is_array($config)) {
            return $result;
        }
        foreach (self::PRICE_KEYS as $key) {
            if (isset($config[$key])) {
                $config[$key] = new \stdClass();
            }
        }

        return $this->json->serialize($config);
    }
}
