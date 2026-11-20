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
 * POST /quick-order/list/save - add SKUs to a named shopping list (not cart_list).
 *
 * JSON: {form_key, name, mode?: "merge"|"replace"|"add"|"create", items: [{sku, qty}]}
 *   merge   add the rows to the list (created when missing): Add to list modal
 *   replace the rows are the whole list: qty edits in the list editor
 *   add     merge into an existing list, every SKU checked against the catalogue: Add a SKU in the editor
 *   create  a new empty list; a taken name is refused ("A list with this name already exists."), items ignored
 *   remove  take the SKUs of items off an existing list (qty ignored): a ticked row in the heart's list picker
 * Or legacy form: list[name], list[0][sku], list[0][qty] (as on Bart add-to-list modal).
 */
class Save extends AbstractJson implements HttpPostActionInterface
{
    private const MAX_ROWS = 100;
    private const MODE_MERGE = 'merge';
    private const MODE_REPLACE = 'replace';
    private const MODE_ADD = 'add';
    private const MODE_CREATE = 'create';
    private const MODE_REMOVE = 'remove';

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
        [$name, $rows, $mode] = $this->parseRequest();
        if ($mode === self::MODE_CREATE) {
            $name = $this->savedLists->create($customerId, $name);
        } elseif ($mode === self::MODE_ADD) {
            $this->savedLists->add($customerId, $name, $rows);
        } elseif ($mode === self::MODE_REMOVE) {
            $this->savedLists->remove($customerId, $name, array_map('strval', array_keys($rows)));
        } else {
            $this->savedLists->save($customerId, $name, $rows, $mode === self::MODE_REPLACE);
        }

        return ['name' => $name] + $this->savedLists->getPayload($customerId);
    }

    /**
     * @return array{0: string, 1: array<string, float>, 2: string}
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
            $mode = $replace ? self::MODE_REPLACE : self::MODE_MERGE;
            $this->assertName($name, $rows, $mode);

            return [$name, $rows, $mode];
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $mode = (string) ($payload['mode'] ?? self::MODE_MERGE);
        if (!in_array($mode, [self::MODE_MERGE, self::MODE_REPLACE, self::MODE_ADD, self::MODE_CREATE, self::MODE_REMOVE], true)) {
            throw new LocalizedException(__('Invalid data.'));
        }
        if ($mode === self::MODE_CREATE) {
            // SavedLists::create() answers an empty or too long name with its own message
            return [$name, [], $mode];
        }
        $rows = $this->getRows(self::MAX_ROWS);
        $this->assertName($name, $rows, $mode);

        return [$name, $rows, $mode];
    }

    /**
     * A replace with no rows clears the list (the editor removed the last product). merge, add and remove need rows.
     *
     * @param array<string, float> $rows
     * @throws LocalizedException
     */
    private function assertName(string $name, array $rows, string $mode): void
    {
        if ($name === ''
            || mb_strlen($name) > 255
            || count($rows) > self::MAX_ROWS
            || ($rows === [] && $mode !== self::MODE_REPLACE)
        ) {
            throw new LocalizedException(__('Invalid data.'));
        }
    }
}
