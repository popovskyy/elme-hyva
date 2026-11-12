<?php

declare(strict_types=1);

namespace Elme\ThirdParty\Controller\Outlet;

use Elme\ThirdParty\Model\OutletSignup\Subscriber;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * FE-91: JSON endpoint for /elme-outlet-signup (proxies to Klaviyo Client Subscriptions).
 */
class Subscribe implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Subscriber $subscriber,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        if (!$this->request->isPost()) {
            return $result->setHttpResponseCode(405)->setData([
                'success' => false,
                'message' => (string) __('Method not allowed.'),
            ]);
        }

        try {
            $this->subscriber->subscribe([
                'first_name' => (string) $this->request->getParam('first_name', ''),
                'last_name' => (string) $this->request->getParam('last_name', ''),
                'email' => (string) $this->request->getParam('email', ''),
                'phone' => (string) $this->request->getParam('phone', ''),
            ]);

            return $result->setData([
                'success' => true,
                'message' => (string) __('Thanks - you are signed up for outlet updates.'),
            ]);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Something went wrong with the signup. Please try again.'),
            ]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->jsonFactory->create();
        $result->setHttpResponseCode(403)->setData([
            'success' => false,
            'message' => (string) __('Invalid form key. Please refresh the page and try again.'),
        ]);

        return new InvalidRequestException($result);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }
}
