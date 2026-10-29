<?php

declare(strict_types=1);

namespace Elme\ContactForm\ViewModel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Newsletter\Model\Config as NewsletterConfig;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-46: Contact Us field options, persisted values, and logged-in prefill.
 */
class FormData implements ArgumentInterface
{
    public const PERSIST_KEY = 'elme_contact_us';

    public const MESSAGE_MAX = 200;

    public const CLAIM_PORTAL_URL = 'https://help.elmeliving.com/';

    /**
     * Production business types (were mislabeled “enquiry” on the old site).
     *
     * @var array<string, string>
     */
    public const BUSINESS_TYPES = [
        'retailer' => 'Retailer',
        'commercial' => 'Commercial',
        'trade' => 'Trade',
        'media' => 'Media',
        'online_retailer' => 'Online Retailer',
        'other' => 'Other',
    ];

    /**
     * Triage topics (idea review).
     *
     * @var array<string, string>
     */
    public const ENQUIRY_TYPES = [
        'orders_delivery' => 'Orders & delivery',
        'account_pricing' => 'Account & pricing',
        'stock' => 'Stock availability',
        'showroom' => 'Showroom',
        'product_claim' => 'Product quality / claim',
        'other' => 'Other',
    ];

    public function __construct(
        private readonly DataPersistorInterface $dataPersistor,
        private readonly CustomerSession $customerSession,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly NewsletterConfig $newsletterConfig,
        private readonly RequestInterface $request
    ) {
    }

    public function getFormAction(): string
    {
        return $this->urlBuilder->getUrl('elme_contact/index/post', ['_secure' => true]);
    }

    public function getMessageMaxLength(): int
    {
        return self::MESSAGE_MAX;
    }

    public function isNewsletterEnabled(): bool
    {
        return $this->newsletterConfig->isActive();
    }

    /**
     * @return array<string, string>
     */
    public function getBusinessTypes(): array
    {
        return self::BUSINESS_TYPES;
    }

    /**
     * @return array<string, string>
     */
    public function getEnquiryTypes(): array
    {
        return self::ENQUIRY_TYPES;
    }

    public function getBusinessTypeLabel(string $code): string
    {
        return self::BUSINESS_TYPES[$code] ?? $code;
    }

    public function getEnquiryTypeLabel(string $code): string
    {
        return self::ENQUIRY_TYPES[$code] ?? $code;
    }

    /**
     * @return list<array{label: string, href: string, external?: bool}>
     */
    public function getShortcutLinks(): array
    {
        $base = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');

        return [
            [
                'label' => (string) __('FAQs'),
                'href' => $base . '/faqs',
            ],
            [
                'label' => (string) __('Submit a Product Claim'),
                'href' => self::CLAIM_PORTAL_URL,
                'external' => true,
            ],
            [
                'label' => (string) __('Become a Stockist'),
                'href' => $base . '/become-a-stockist',
            ],
            [
                'label' => (string) __('Trade login'),
                'href' => $this->urlBuilder->getUrl('customer/account/login'),
            ],
        ];
    }

    /**
     * Success follow-up links for a given enquiry type code.
     *
     * @return list<array{label: string, href: string, external?: bool}>
     */
    public function getSuccessLinks(string $enquiryType = ''): array
    {
        $base = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        $links = [];

        if ($enquiryType === 'product_claim') {
            $links[] = [
                'label' => (string) __('Open the claim portal'),
                'href' => self::CLAIM_PORTAL_URL,
                'external' => true,
            ];
        }

        if ($enquiryType === 'orders_delivery') {
            $links[] = [
                'label' => (string) __('Shipping & Returns'),
                'href' => $base . '/shipping-and-returns',
            ];
        }

        if ($enquiryType === 'account_pricing' || $enquiryType === 'orders_delivery') {
            $links[] = [
                'label' => (string) __('Go to your account'),
                'href' => $this->urlBuilder->getUrl('customer/account'),
            ];
        }

        $links[] = [
            'label' => (string) __('Browse FAQs'),
            'href' => $base . '/faqs',
        ];

        return $links;
    }

    public function getFieldValue(string $key): string
    {
        $persist = $this->getPersistData();
        if (array_key_exists($key, $persist) && $persist[$key] !== null && $persist[$key] !== '') {
            return (string) $persist[$key];
        }

        return $this->getPrefillValue($key);
    }

    public function isSentSuccess(): bool
    {
        return (string) $this->request->getParam('sent') === '1';
    }

    public function getSentEnquiryType(): string
    {
        $code = (string) $this->request->getParam('topic');
        return array_key_exists($code, self::ENQUIRY_TYPES) ? $code : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getPersistData(): array
    {
        $data = $this->dataPersistor->get(self::PERSIST_KEY);
        return is_array($data) ? $data : [];
    }

    private function getPrefillValue(string $key): string
    {
        if (!$this->customerSession->isLoggedIn()) {
            return '';
        }

        try {
            $customer = $this->customerRepository->getById((int) $this->customerSession->getCustomerId());
        } catch (\Exception) {
            return '';
        }

        return match ($key) {
            'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'email' => (string) $customer->getEmail(),
            'company' => $this->resolveCompany($customer),
            'telephone' => $this->resolveTelephone($customer),
            default => '',
        };
    }

    private function resolveCompany(CustomerInterface $customer): string
    {
        $companyAttr = $customer->getCustomAttribute('company');
        if ($companyAttr && trim((string) $companyAttr->getValue()) !== '') {
            return trim((string) $companyAttr->getValue());
        }

        foreach ($customer->getAddresses() ?? [] as $address) {
            $company = trim((string) $address->getCompany());
            if ($company !== '') {
                return $company;
            }
        }

        return '';
    }

    private function resolveTelephone(CustomerInterface $customer): string
    {
        foreach ($customer->getAddresses() ?? [] as $address) {
            $phone = trim((string) $address->getTelephone());
            if ($phone !== '') {
                return $phone;
            }
        }

        return '';
    }
}
