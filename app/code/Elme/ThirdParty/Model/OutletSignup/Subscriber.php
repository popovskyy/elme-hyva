<?php

declare(strict_types=1);

namespace Elme\ThirdParty\Model\OutletSignup;

use Elme\ThirdParty\ViewModel\Config as ThirdPartyConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * FE-91: subscribe a profile to the Outlet Sign Ups list via Klaviyo Client API (server-side).
 * Browser posts to Magento so DataDome / CORS do not block local or headless clients.
 */
class Subscriber
{
    private const API_REVISION = '2024-06-15';
    private const ENDPOINT = 'https://a.klaviyo.com/client/subscriptions/?company_id=';

    public function __construct(
        private readonly ThirdPartyConfig $config,
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array{email: string, first_name: string, last_name: string, phone: string} $profile
     * @throws LocalizedException
     */
    public function subscribe(array $profile): void
    {
        $companyId = $this->config->getKlaviyoPublicApiKey();
        $listId = $this->config->getOutletListId();
        if ($companyId === '' || $listId === '') {
            throw new LocalizedException(__('Outlet signup is not configured.'));
        }

        $email = trim($profile['email'] ?? '');
        $first = trim($profile['first_name'] ?? '');
        $last = trim($profile['last_name'] ?? '');
        $phone = $this->toE164(trim($profile['phone'] ?? ''));

        if ($first === '' || $last === '') {
            throw new LocalizedException(__('Please enter your first and last name.'));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Please enter a valid email address.'));
        }
        if ($phone === '') {
            throw new LocalizedException(__('Please enter a valid phone number.'));
        }

        $payload = [
            'data' => [
                'type' => 'subscription',
                'attributes' => [
                    'custom_source' => 'Elme Outlet Signup',
                    'profile' => [
                        'data' => [
                            'type' => 'profile',
                            'attributes' => [
                                'email' => $email,
                                'first_name' => $first,
                                'last_name' => $last,
                                'phone_number' => $phone,
                                'properties' => [
                                    'signup_page' => 'elme-outlet-signup',
                                ],
                            ],
                        ],
                    ],
                ],
                'relationships' => [
                    'list' => [
                        'data' => [
                            'type' => 'list',
                            'id' => $listId,
                        ],
                    ],
                ],
            ],
        ];

        $curl = $this->curlFactory->create();
        $curl->setTimeout(15);
        $curl->addHeader('Content-Type', 'application/json');
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('revision', self::API_REVISION);

        try {
            $curl->post(self::ENDPOINT . rawurlencode($companyId), $this->json->serialize($payload));
        } catch (\Throwable $e) {
            $this->logger->error('FE-91 Klaviyo outlet signup request failed', ['exception' => $e]);
            throw new LocalizedException(__('Something went wrong with the signup. Please try again.'));
        }

        $status = (int) $curl->getStatus();
        if ($status === 202 || ($status >= 200 && $status < 300)) {
            return;
        }

        $this->logger->warning('FE-91 Klaviyo outlet signup rejected', [
            'status' => $status,
            'body' => substr((string) $curl->getBody(), 0, 500),
        ]);
        throw new LocalizedException(__('Something went wrong with the signup. Please try again.'));
    }

    private function toE164(string $raw): string
    {
        $digits = preg_replace('/[^\d+]/', '', $raw) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '+')) {
            return strlen($digits) >= 11 ? $digits : '';
        }
        $only = preg_replace('/\D/', '', $digits) ?? '';
        if (str_starts_with($only, '61') && strlen($only) >= 10) {
            return '+' . $only;
        }
        if (str_starts_with($only, '0') && strlen($only) >= 9) {
            return '+61' . substr($only, 1);
        }
        if (strlen($only) >= 9) {
            return '+61' . $only;
        }

        return '';
    }
}
