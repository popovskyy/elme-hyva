<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\List;

use Elme\QuickOrder\Controller\AbstractJson;
use Elme\QuickOrder\Model\SavedLists;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Psr\Log\LoggerInterface;

/**
 * POST /quick-order/list/save — add SKUs to a named shopping list (not cart_list).
 *
 * JSON: {form_key, name, mode?: "merge"|"replace", items: [{sku, qty}]}
 * Or legacy form: list[name], list[0][sku], list[0][qty] (as on Bart add-to-list modal).
 */
class Save extends AbstractJson implements HttpPostActionInterface
{
    private const MAX_ROWS = 100;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        CustomerSession $customerSession,
        FormKeyValidator $formKeyValidator,
        JsonSerializer $json,
        LoggerInterface $logger,
        private readonly SavedLists $savedLists
    ) {
        parent::__construct($request, $jsonFactory, $customerSession, $formKeyValidator, $json, $logger);
    }

    protected function run(int $customerId, int $customerGroupId): array
    {
        [$name, $rows, $replace] = $this->parseRequest();
        $this->savedLists->save($customerId, $name, $rows, $replace);

        return $this->savedLists->getPayload($customerId);
    }

    /**
     * @return array{0: string, 1: array<string, float>, 2: bool}
     * @throws LocalizedException
     */
    private function parseRequest(): array
    {
        $payload = $this->getPayload();
        $list = $payload['list'] ?? null;

        if (is_array($list)) {
            $name = trim((string) ($list['name'] ?? ''));
            $replace = !empty($list['replace']);
            $rows = [];
            foreach ($list as $key => $item) {
                if ($key === 'name' || $key === 'replace' || !is_array($item)) {
                    continue;
                }
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $qty = (float) ($item['qty'] ?? 1);
                $rows[$sku] = ($rows[$sku] ?? 0.0) + ($qty > 0 ? $qty : 1.0);
            }
            $this->assertName($name, $rows, $replace);

            return [$name, $rows, $replace];
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $replace = ($payload['mode'] ?? '') === 'replace';
        $rows = $this->getRows(self::MAX_ROWS);
        $this->assertName($name, $rows, $replace);

        return [$name, $rows, $replace];
    }

    /**
     * A replace with no rows clears the list (the editor removed the last product, or a new list has none yet).
     * A merge still needs rows.
     *
     * @param array<string, float> $rows
     * @throws LocalizedException
     */
    private function assertName(string $name, array $rows, bool $replace): void
    {
        if ($name === '' || mb_strlen($name) > 255 || count($rows) > self::MAX_ROWS || ($rows === [] && !$replace)) {
            throw new LocalizedException(__('Invalid data.'));
        }
    }
}
