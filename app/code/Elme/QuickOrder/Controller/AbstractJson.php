<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * Base of the Quick Order JSON endpoints. Their URLs are the same for every customer, so a shared cache (Varnish,
 * Fastly, a proxy) would hand one customer's cart to the whole customer group: every answer is no-store, and no
 * error is a 200. Guests get 401 (Plugin\AnswerGuestsWith401 keeps the login redirect away from these URLs).
 */
abstract class AbstractJson
{
    private const CACHE_CONTROL = 'private, no-store, no-cache, must-revalidate, max-age=0';

    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    public function __construct(
        protected readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        protected readonly CustomerSession $customerSession,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly JsonSerializer $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, mixed> The answer; LocalizedException for bad input (400)
     * @throws LocalizedException
     */
    abstract protected function run(int $customerId, int $customerGroupId): array;

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', self::CACHE_CONTROL, true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('Expires', '0', true);
        $result->setHeader('Surrogate-Control', 'private, no-store', true);

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)
                ->setData(['success' => false, 'message' => (string) __('Please login to access this page.')]);
        }

        try {
            if ($this->request instanceof HttpRequest && $this->request->isPost()) {
                $this->validateFormKey();
            }
            $data = $this->run(
                (int) $this->customerSession->getCustomerId(),
                (int) $this->customerSession->getCustomerGroupId()
            );

            return $result->setData(['success' => true] + $data);
        } catch (LocalizedException $e) {
            return $result->setHttpResponseCode(400)->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->error('Elme_QuickOrder: ' . static::class . ' failed.', ['exception' => $e]);

            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string) __('Something went wrong. Please try again.'),
            ]);
        }
    }

    /**
     * The JSON request body (POST), or the request params when the body is not JSON.
     *
     * @return array<string, mixed>
     */
    protected function getPayload(): array
    {
        if ($this->payload !== null) {
            return $this->payload;
        }

        $content = $this->request instanceof HttpRequest ? (string) $this->request->getContent() : '';
        try {
            $decoded = $content !== '' ? $this->json->unserialize($content) : null;
        } catch (\InvalidArgumentException) {
            $decoded = null;
        }

        return $this->payload = is_array($decoded) ? $decoded : $this->request->getParams();
    }

    /**
     * [{sku, qty}, ...] of the payload as sku => qty; the same sku twice adds up.
     *
     * @return array<string, float>
     * @throws LocalizedException
     */
    protected function getRows(int $limit): array
    {
        $items = $this->getPayload()['items'] ?? [];
        if (!is_array($items) || count($items) > $limit) {
            throw new LocalizedException(__('Invalid data.'));
        }

        $rows = [];
        foreach ($items as $item) {
            $sku = is_array($item) ? trim((string) ($item['sku'] ?? '')) : '';
            if ($sku === '' || !is_numeric($item['qty'] ?? null)) {
                throw new LocalizedException(__('Invalid data.'));
            }
            $rows[$sku] = ($rows[$sku] ?? 0.0) + (float) $item['qty'];
        }

        return $rows;
    }

    /**
     * Magento's CSRF check skips ajax posts, and a JSON body has no form_key param: check it here.
     *
     * @throws LocalizedException
     */
    private function validateFormKey(): void
    {
        $formKey = $this->getPayload()['form_key'] ?? $this->request->getHeader('X-Form-Key');
        $this->request->setParam('form_key', is_string($formKey) ? $formKey : '');
        if (!$this->formKeyValidator->validate($this->request)) {
            throw new LocalizedException(__('Your session has expired. Please reload the page.'));
        }
    }
}
