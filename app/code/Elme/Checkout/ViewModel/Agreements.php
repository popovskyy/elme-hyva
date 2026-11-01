<?php

declare(strict_types=1);

namespace Elme\Checkout\ViewModel;

use Magento\CheckoutAgreements\Model\AgreementModeOptions;
use Magento\CheckoutAgreements\Model\AgreementsConfigProvider;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The checkout agreements of this store, from the provider core's checkout uses: it escapes text that is not HTML.
 */
class Agreements implements ArgumentInterface
{
    public function __construct(
        private readonly AgreementsConfigProvider $configProvider
    ) {
    }

    /**
     * @return array<int, array{agreementId: int|string, mode: int|string, checkboxText: string, content: string}>
     */
    public function getAgreements(): array
    {
        $config = $this->configProvider->getConfig()['checkoutAgreements'];

        return $config['isEnabled'] ? ($config['agreements'] ?? []) : [];
    }

    /**
     * @param array{mode: int|string} $agreement
     * @return bool True when the customer must tick a checkbox; otherwise placing the order counts as accepting
     */
    public function needsCheckbox(array $agreement): bool
    {
        return (int) $agreement['mode'] === AgreementModeOptions::MODE_MANUAL;
    }
}
