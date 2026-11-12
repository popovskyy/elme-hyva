<?php

declare(strict_types=1);

namespace Elme\ThirdParty\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * FE-54: storefront config for deferred third-party loaders.
 */
class Config implements ArgumentInterface
{
    private const XML_ENABLED = 'elme_third_party/general/enabled';
    private const XML_CONSENT = 'elme_third_party/general/consent_mode';
    private const XML_GTM_ENABLED = 'elme_third_party/gtm/enabled';
    private const XML_GTM_ID = 'elme_third_party/gtm/container_id';
    private const XML_KL_ENABLED = 'elme_third_party/klaviyo/enabled';
    private const XML_KL_KEY = 'elme_third_party/klaviyo/public_api_key';
    private const XML_KL_FORMS = 'elme_third_party/klaviyo/forms_enabled';
    private const XML_KL_OUTLET_FORM = 'elme_third_party/klaviyo/outlet_embed_form_id';
    private const XML_ZD_ENABLED = 'elme_third_party/zendesk/enabled';
    private const XML_ZD_KEY = 'elme_third_party/zendesk/snippet_key';
    private const XML_ZD_OFF_M = 'elme_third_party/zendesk/launcher_offset_mobile';
    private const XML_ZD_OFF_D = 'elme_third_party/zendesk/launcher_offset_desktop';
    private const XML_TW_ENABLED = 'elme_third_party/triple_whale/enabled';
    private const XML_TW_SHOP = 'elme_third_party/triple_whale/shop';
    private const XML_TW_PLAT = 'elme_third_party/triple_whale/platform';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->flag(self::XML_ENABLED);
    }

    /**
     * Klaviyo onsite embed form id for /elme-outlet-signup (FE-91). Empty = do not render.
     */
    public function getOutletEmbedFormId(): string
    {
        $id = trim((string) $this->get(self::XML_KL_OUTLET_FORM));

        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1 ? $id : '';
    }

    /**
     * JSON config for the deferred bootstrap (no secrets — public vendor keys only).
     */
    public function getBootstrapJson(): string
    {
        $payload = [
            'consentMode' => (string) $this->get(self::XML_CONSENT) ?: 'defer_only',
            'gtm' => [
                'enabled' => $this->flag(self::XML_GTM_ENABLED),
                'id' => trim((string) $this->get(self::XML_GTM_ID)),
            ],
            'klaviyo' => [
                'enabled' => $this->flag(self::XML_KL_ENABLED),
                'companyId' => trim((string) $this->get(self::XML_KL_KEY)),
                'forms' => $this->flag(self::XML_KL_FORMS),
            ],
            'zendesk' => [
                'enabled' => $this->flag(self::XML_ZD_ENABLED),
                'key' => trim((string) $this->get(self::XML_ZD_KEY)),
                'offsetMobile' => trim((string) $this->get(self::XML_ZD_OFF_M)) ?: '5.5rem',
                'offsetDesktop' => trim((string) $this->get(self::XML_ZD_OFF_D)) ?: '1.5rem',
            ],
            'tripleWhale' => [
                'enabled' => $this->flag(self::XML_TW_ENABLED),
                'shop' => trim((string) $this->get(self::XML_TW_SHOP)),
                'platform' => trim((string) $this->get(self::XML_TW_PLAT)) ?: 'custom-msp',
            ],
        ];

        return $this->json->serialize($payload);
    }

    private function get(string $path): mixed
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    private function flag(string $path): bool
    {
        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE);
    }
}
