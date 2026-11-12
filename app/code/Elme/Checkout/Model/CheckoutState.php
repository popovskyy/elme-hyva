<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

class CheckoutState implements CheckoutStateInterface
{
    /**
     * @param mixed[] $sections
     * @param string[] $errors
     */
    public function __construct(
        private readonly array $sections = [],
        private readonly array $errors = []
    ) {
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return ['sections' => $this->sections, 'errors' => $this->errors];
    }
}
