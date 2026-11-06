<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Address;

use Elme\Checkout\Exception\CheckoutInputException;
use Magento\Customer\Api\Data\AddressInterface as CustomerAddress;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Writes the delivery and billing address of the quote.
 *
 * Trade invoices go to the account's billing address. Without a default billing address the billing address follows
 * the delivery address; the shipping address's same_as_billing flag says that it does.
 */
class QuoteAddresses
{
    public const SECTION = 'address';
    public const COUNTRY_ID = 'AU';

    /**
     * What a billing address takes over from the delivery address it follows.
     */
    private const FOLLOWED_FIELDS = [
        'customer_address_id',
        'firstname',
        'lastname',
        'company',
        'street',
        'city',
        'region',
        'region_id',
        'postcode',
        'country_id',
        'telephone',
    ];

    public function __construct(
        private readonly AddressBook $addressBook,
        private readonly AddressValidator $validator,
        private readonly AddressFormatter $formatter,
        private readonly QuoteAddressFactory $quoteAddressFactory,
        private readonly RegionFactory $regionFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Brings the quote up to date before it is shown: the default addresses when it has none, the current version of
     * a saved address edited in the account. An address problem must not cost the customer the checkout page.
     */
    public function initialize(Quote $quote): void
    {
        if (!$quote->getCustomerId()) {
            return;
        }

        try {
            $shipping = $quote->getShippingAddress();
            $changed = $this->followAddressBook($quote, $shipping);
            $changed = $this->followAddressBook($quote, $quote->getBillingAddress()) || $changed;
            $changed = $this->applyDefaultShipping($quote) || $changed;
            $changed = $this->updateBilling($quote) || $changed;

            if ($changed) {
                $shipping->setCollectShippingRates(true);
                $this->save($quote);
            }
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf('Checkout addresses of quote %d: %s', $quote->getId(), $exception->getMessage()),
                ['exception' => $exception]
            );
        }
    }

    /**
     * @param Quote $quote
     * @param int $addressId
     * @return void
     * @throws CheckoutInputException
     */
    public function selectShipping(Quote $quote, int $addressId): void
    {
        $this->importSaved($quote, $quote->getShippingAddress(), $this->getUsableSaved($quote, $addressId));
        $this->applyShippingChange($quote);
    }

    /**
     * @param Quote $quote
     * @param mixed[] $fields Fields of the checkout address form
     * @param bool $saveToBook
     * @return void
     * @throws CheckoutInputException
     */
    public function setNewShipping(Quote $quote, array $fields, bool $saveToBook): void
    {
        $candidate = $this->quoteAddressFactory->create();
        $this->fill($candidate, $fields);

        $errors = $this->validator->getErrors($candidate);
        if ($errors) {
            $general = $errors[AddressValidator::GENERAL] ?? null;
            unset($errors[AddressValidator::GENERAL]);
            throw new CheckoutInputException(
                $general !== null ? __($general) : __('Please check the highlighted fields.'),
                self::SECTION,
                $errors
            );
        }

        $savedId = null;
        if ($saveToBook) {
            $savedId = (int) $this->addressBook->add((int) $quote->getCustomerId(), $candidate)->getId();
        }

        $shipping = $quote->getShippingAddress();
        $this->fill($shipping, $fields);
        $shipping->setCustomerAddressId($savedId)
            ->setSaveInAddressBook(0)
            ->setEmail($quote->getCustomerEmail());

        $this->applyShippingChange($quote);
    }

    /**
     * @param Quote $quote
     * @param int $addressId
     * @return void
     * @throws CheckoutInputException
     */
    public function selectBilling(Quote $quote, int $addressId): void
    {
        $this->importSaved($quote, $quote->getBillingAddress(), $this->getUsableSaved($quote, $addressId));
        $quote->getShippingAddress()->setSameAsBilling(0);
        $this->save($quote);
    }

    private function applyShippingChange(Quote $quote): void
    {
        $this->updateBilling($quote);
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $this->save($quote);
    }

    private function save(Quote $quote): void
    {
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($quote);
    }

    private function applyDefaultShipping(Quote $quote): bool
    {
        $shipping = $quote->getShippingAddress();
        if ($shipping->getFirstname()) {
            return false;
        }

        $default = $this->addressBook->getDefaultShipping((int) $quote->getCustomerId());
        if (!$default || !$this->isComplete($default)) {
            return false;
        }

        $this->importSaved($quote, $shipping, $default);

        return true;
    }

    /**
     * Leaves a billing address the customer picked alone.
     */
    private function updateBilling(Quote $quote): bool
    {
        $shipping = $quote->getShippingAddress();
        $billing = $quote->getBillingAddress();
        if (!$shipping->getSameAsBilling() && $billing->getFirstname()) {
            return false;
        }

        $default = $this->addressBook->getDefaultBilling((int) $quote->getCustomerId());
        if ($default && $this->isComplete($default)) {
            $this->importSaved($quote, $billing, $default);
            $shipping->setSameAsBilling(0);

            return true;
        }

        $wasFollowing = (bool) $shipping->getSameAsBilling();
        $shipping->setSameAsBilling(1);
        if (!$shipping->getFirstname()) {
            return !$wasFollowing;
        }

        $before = $billing->getData();
        foreach (self::FOLLOWED_FIELDS as $field) {
            $billing->setData($field, $shipping->getData($field));
        }
        $billing->setEmail($quote->getCustomerEmail())->setSaveInAddressBook(0);

        return !$wasFollowing || array_intersect_key($before, array_flip(self::FOLLOWED_FIELDS))
            != array_intersect_key($billing->getData(), array_flip(self::FOLLOWED_FIELDS));
    }

    /**
     * A quote address keeps its own copy of the saved address: refresh it when the saved one changed in the account,
     * and drop the link when it was deleted (the copy stays: this order can still go there).
     */
    private function followAddressBook(Quote $quote, QuoteAddress $address): bool
    {
        $addressId = (int) $address->getCustomerAddressId();
        if (!$addressId) {
            return false;
        }

        try {
            $saved = $this->addressBook->getAddress((int) $quote->getCustomerId(), $addressId);
        } catch (NoSuchEntityException) {
            $address->setCustomerAddressId(null);
            return true;
        }

        if ($this->formatter->fromCustomerAddress($saved) === $this->formatter->fromQuoteAddress($address)) {
            return false;
        }

        $this->importSaved($quote, $address, $saved);

        return true;
    }

    private function importSaved(Quote $quote, QuoteAddress $target, CustomerAddress $saved): void
    {
        $target->importCustomerAddressData($saved);
        $target->setCustomerAddressId((int) $saved->getId())
            ->setSaveInAddressBook(0)
            ->setEmail($quote->getCustomerEmail());
    }

    /**
     * @param Quote $quote
     * @param int $addressId
     * @return CustomerAddress
     * @throws CheckoutInputException
     */
    private function getUsableSaved(Quote $quote, int $addressId): CustomerAddress
    {
        try {
            $saved = $this->addressBook->getAddress((int) $quote->getCustomerId(), $addressId);
        } catch (NoSuchEntityException) {
            throw new CheckoutInputException(
                __('This address is no longer available. Please choose another one.'),
                self::SECTION
            );
        }

        if (!$this->isComplete($saved)) {
            throw new CheckoutInputException(
                __('This address is incomplete. Please update it in your account or choose another one.'),
                self::SECTION
            );
        }

        return $saved;
    }

    private function isComplete(CustomerAddress $saved): bool
    {
        $candidate = $this->quoteAddressFactory->create();
        $candidate->importCustomerAddressData($saved);
        $candidate->setCustomerAddressId(null);

        return !$this->validator->getErrors($candidate);
    }

    /**
     * @param QuoteAddress $address
     * @param mixed[] $fields
     * @return void
     */
    private function fill(QuoteAddress $address, array $fields): void
    {
        $regionId = (int) $this->text($fields, 'region_id');
        $region = $regionId ? $this->regionFactory->create()->load($regionId) : null;

        $address->setFirstname($this->text($fields, 'firstname'))
            ->setLastname($this->text($fields, 'lastname'))
            ->setCompany($this->text($fields, 'company'))
            ->setStreet(array_values(array_filter(
                [$this->text($fields, 'street_1'), $this->text($fields, 'street_2')],
                'strlen'
            )))
            ->setCity($this->text($fields, 'city'))
            ->setRegionId($regionId ?: null)
            ->setRegion($region?->getName() ?: null)
            ->setPostcode($this->text($fields, 'postcode'))
            ->setCountryId(self::COUNTRY_ID)
            ->setTelephone($this->text($fields, 'telephone'));
    }

    /**
     * @param mixed[] $fields
     * @param string $name
     * @return string
     */
    private function text(array $fields, string $name): string
    {
        $value = $fields[$name] ?? '';

        return is_scalar($value) ? trim(strip_tags((string) $value)) : '';
    }
}
