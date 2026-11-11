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
 * Success: {"ok": true, "state": {...}}, or {"ok": true, "redirect": "..."} when the checkout is finished.
 * Failure: {"ok": false, "message": "...", "section": "...", "field_errors": {...}, "state": {...}, "redirect": "..."}.
 * A failure with a redirect means the checkout cannot go on here: session gone (login) or nothing left to buy (cart).
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
        protected readonly UrlInterface $urlBuilder,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
        private readonly FormKeyValidator $formKeyValidator
    ) {
    }

    /**
     * @param int $cartId The cart of the current session, with items
     * @return CheckoutStateInterface|mixed[] The new state, or the answer of an action that ends the checkout
     * @throws LocalizedException
     */
    abstract protected function run(int $cartId): CheckoutStateInterface|array;

    public function execute(): JsonResult
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->failure(401, (string) __('Please login to access this page.'), [
                'redirect' => $this->customerUrl->getLoginUrl(),
            ]);
        }

        $cartId = 0;
        try {
            $quote = $this->checkoutSession->getQuote();
            if (!$quote->getId() || !$quote->hasItems()) {
                return $this->resolveEmptyCart() ?? $this->failure(409, (string) __('Your cart is empty.'), [
                    'redirect' => $this->urlBuilder->getUrl('checkout/cart'),
                ]);
            }

            $cartId = (int) $quote->getId();
            $answer = $this->run($cartId);

            return $this->result(200, ['ok' => true] + (
                $answer instanceof CheckoutStateInterface ? ['state' => $answer->toArray()] : $answer
            ));
        } catch (CheckoutInputException $exception) {
            return $this->failure(422, $exception->getMessage(), [
                'section' => $exception->getSection(),
                'field_errors' => (object) $exception->getFieldErrors(),
            ] + $this->inputErrorData($cartId));
        } catch (LocalizedException $exception) {
            return $this->failure(400, $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return $this->failure(500, (string) __('Something went wrong. Please try again.'));
        }
    }

    /**
     * When the cart is empty: null keeps the 409, a result finishes the request (e.g. success after a lost answer).
     */
    protected function resolveEmptyCart(): ?JsonResult
    {
        return null;
    }

    /**
     * @param string $path
     * @return JsonResult
     */
    protected function redirectTo(string $path): JsonResult
    {
        return $this->result(200, ['ok' => true, 'redirect' => $this->urlBuilder->getUrl($path)]);
    }

    /**
     * What goes along with the answer to a CheckoutInputException.
     *
     * @param int $cartId
     * @return mixed[]
     */
    protected function inputErrorData(int $cartId): array
    {
        return [];
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
