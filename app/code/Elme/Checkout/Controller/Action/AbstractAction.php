<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Exception\CheckoutInputException;
use InvalidArgumentException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Base of the JSON endpoints elme_checkout/action/*: the page's Alpine store POSTs form_key + a JSON "data" field.
 *
 * Success: {"ok": true, "state": {...}}.
 * Failure: {"ok": false, "message": "...", "section": "...", "field_errors": {...}, "redirect": "..."}.
 * A redirect means the checkout cannot go on here: session gone (login) or nothing left to buy (cart).
 *
 * Business rules stay out of here: an action reads the payload and calls CheckoutManagementInterface.
 */
abstract class AbstractAction implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        protected readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CustomerUrl $customerUrl,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly FormKeyValidator $formKeyValidator
    ) {
    }

    /**
     * @param int $cartId The cart of the current session, with items
     * @return CheckoutStateInterface
     * @throws LocalizedException
     */
    abstract protected function run(int $cartId): CheckoutStateInterface;

    public function execute(): JsonResult
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->failure(401, (string) __('Please login to access this page.'), [
                'redirect' => $this->customerUrl->getLoginUrl(),
            ]);
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId() || !$quote->hasItems()) {
                return $this->failure(409, (string) __('Your cart is empty.'), [
                    'redirect' => $this->urlBuilder->getUrl('checkout/cart'),
                ]);
            }

            return $this->result(200, ['ok' => true, 'state' => $this->run((int) $quote->getId())->toArray()]);
        } catch (CheckoutInputException $exception) {
            return $this->failure(422, $exception->getMessage(), [
                'section' => $exception->getSection(),
                'field_errors' => (object) $exception->getFieldErrors(),
            ]);
        } catch (LocalizedException $exception) {
            return $this->failure(400, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return $this->failure(500, (string) __('Something went wrong. Please try again.'));
        }
    }

    /**
     * The "data" field of the request.
     *
     * @return mixed[]
     */
    protected function getPayload(): array
    {
        try {
            $payload = $this->json->unserialize((string) $this->request->getParam('data', '{}'));
        } catch (InvalidArgumentException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        // No session messages: the store shows the JSON message, a second one would wait on the next page
        return new InvalidRequestException(
            $this->failure(403, (string) __('Your session has expired. Please refresh the page.'))
        );
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // Not null: the core check waves through anything that looks like ajax, even a plain form with ajax=1
        return $this->formKeyValidator->validate($request);
    }

    /**
     * @param int $status
     * @param string $message
     * @param mixed[] $data
     * @return JsonResult
     */
    private function failure(int $status, string $message, array $data = []): JsonResult
    {
        return $this->result($status, ['ok' => false, 'message' => $message] + $data);
    }

    /**
     * @param int $status
     * @param mixed[] $data
     * @return JsonResult
     */
    private function result(int $status, array $data): JsonResult
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);

        return $result->setHttpResponseCode($status)->setData($data);
    }
}
