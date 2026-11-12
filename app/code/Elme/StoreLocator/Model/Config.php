<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_MAPS_API_KEY = 'elme_storelocator/maps/api_key';

    private const XML_PATH_APPOINTMENT_RECIPIENT = 'elme_storelocator/appointment/recipient_email';

    private const XML_PATH_CONTACT_RECIPIENT = 'contact/email/recipient_email';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getMapsApiKey(?int $storeId = null): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_MAPS_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * Who gets showroom appointment requests (FE-90); the Contact Us recipient when not set.
     */
    public function getAppointmentRecipient(?int $storeId = null): string
    {
        $email = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_APPOINTMENT_RECIPIENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if ($email !== '') {
            return $email;
        }

        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_CONTACT_RECIPIENT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }
}
