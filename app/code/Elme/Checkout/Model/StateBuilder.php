<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Api\SectionProviderInterface;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds the checkout state from the provider pool. A failing provider costs only its own section: the customer
 * still sees the rest of the page, and the failing section says what happened.
 */
class StateBuilder
{
    /**
     * @param CheckoutStateFactory $stateFactory
     * @param LoggerInterface $logger
     * @param array<string, array{sortOrder: int, provider: SectionProviderInterface}> $providers By section code
     */
    public function __construct(
        private readonly CheckoutStateFactory $stateFactory,
        private readonly LoggerInterface $logger,
        private readonly array $providers = []
    ) {
    }

    public function build(CartInterface $quote): CheckoutStateInterface
    {
        $providers = $this->providers;
        uasort($providers, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

        $sections = [];
        $errors = [];
        foreach ($providers as $code => $definition) {
            try {
                $sections[$code] = $definition['provider']->getData($quote);
            } catch (Throwable $exception) {
                $this->logger->error(
                    sprintf('Checkout section "%s" failed: %s', $code, $exception->getMessage()),
                    ['exception' => $exception]
                );
                $sections[$code] = null;
                $errors[$code] = (string) __('This section could not be loaded. Please refresh the page.');
            }
        }

        return $this->stateFactory->create(['sections' => $sections, 'errors' => $errors]);
    }
}
