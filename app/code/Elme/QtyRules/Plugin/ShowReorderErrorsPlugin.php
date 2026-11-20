<?php

declare(strict_types=1);

namespace Elme\QtyRules\Plugin;

use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Reorder\Data\ReorderOutput;
use Magento\Sales\Model\Reorder\Reorder;

/**
 * Core's reorder controller ignores the errors of the reorder: a refused line would just be missing from the cart.
 * Storefront only (etc/frontend): GraphQL answers with the same errors itself.
 */
class ShowReorderErrorsPlugin
{
    public function __construct(
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * @param Reorder $subject
     * @param ReorderOutput $result
     * @return ReorderOutput
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(Reorder $subject, ReorderOutput $result): ReorderOutput
    {
        foreach ($result->getErrors() as $error) {
            $this->messageManager->addErrorMessage($error->getMessage());
        }

        return $result;
    }
}
