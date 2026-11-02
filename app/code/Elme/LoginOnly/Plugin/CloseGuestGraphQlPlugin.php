<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GraphQl\Controller\GraphQl;

/**
 * GraphQL answers a guest with prices (products.price_range) and a cart. The storefront does not use it, so a guest gets
 * a refusal before any query runs. This is the whole endpoint and not the price fields: a field-by-field list would leave
 * open whatever a module adds later.
 */
class CloseGuestGraphQlPlugin
{
    public function __construct(
        private readonly UserContextInterface $userContext,
        private readonly HttpResponse $response,
        private readonly Json $json
    ) {
    }

    public function aroundDispatch(GraphQl $subject, callable $proceed, RequestInterface $request): ResponseInterface
    {
        if ($this->userContext->getUserId()) {
            return $proceed($request);
        }

        $this->response->setHttpResponseCode(401);
        $this->response->setHeader('Content-Type', 'application/json', true);
        $this->response->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0', true);
        $this->response->setBody((string) $this->json->serialize([
            'errors' => [['message' => 'Please login to access this page.']],
        ]));

        return $this->response;
    }
}
