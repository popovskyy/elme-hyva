<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Appointment;

use Elme\StoreLocator\Model\Config;
use Magento\Contact\Model\ConfigInterface as ContactConfig;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-90: emails a showroom appointment request to the showroom team, reply-to the customer.
 */
class Mail
{
    private const TEMPLATE_ID = 'elme_storelocator_appointment_email_template';

    public function __construct(
        private readonly Config $config,
        private readonly ContactConfig $contactConfig,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param array<string, mixed> $request validated request (Validator::validate)
     */
    public function send(array $request): void
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $recipient = $this->config->getAppointmentRecipient($storeId);
        if ($recipient === '') {
            throw new \RuntimeException('No recipient configured for showroom appointment requests.');
        }

        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier(self::TEMPLATE_ID)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars(['data' => new DataObject($request)])
                ->setFromByScope($this->contactConfig->emailSender(), $storeId)
                ->addTo($recipient)
                ->setReplyTo((string) $request['email'], (string) $request['name'])
                ->getTransport()
                ->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
