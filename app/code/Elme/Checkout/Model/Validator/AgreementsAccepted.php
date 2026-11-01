<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Validator;

use Elme\Checkout\Api\PlaceOrderValidatorInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use Magento\CheckoutAgreements\Model\AgreementsProvider;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Every checkout agreement with a checkbox must be ticked. Nothing to do when agreements are off in the admin.
 */
class AgreementsAccepted implements PlaceOrderValidatorInterface
{
    public const SECTION = 'agreements';

    public function __construct(
        private readonly AgreementsProvider $agreementsProvider
    ) {
    }

    public function validate(CartInterface $quote, array $input = []): void
    {
        $accepted = array_map('intval', (array) ($input['agreements'] ?? []));
        $required = array_map('intval', $this->agreementsProvider->getRequiredAgreementIds());
        $missing = array_diff($required, $accepted);
        if (!$missing) {
            return;
        }

        $message = __('Please accept the terms and conditions.');
        $fieldErrors = [];
        foreach ($missing as $agreementId) {
            $fieldErrors['agreement_' . $agreementId] = (string) $message;
        }

        throw new CheckoutInputException($message, self::SECTION, $fieldErrors);
    }
}
