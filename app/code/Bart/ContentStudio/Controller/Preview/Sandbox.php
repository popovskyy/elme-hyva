<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Preview;

use Bart\ContentStudio\Model\Preview\DraftContext;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * A storefront page that holds nothing but the entry being previewed.
 *
 * For entries with nowhere else to be seen: one that was created a minute ago
 * and is not placed on any page yet. It is a real frontend page - the store's
 * own theme, header, footer, fonts and container widths - so what it shows is
 * what a customer would see, minus the neighbours.
 *
 * Everything it needs comes from the preview token; there is no identifier or
 * store parameter to tamper with, and without a live token the route does not
 * exist.
 */
class Sandbox implements ActionInterface, HttpGetActionInterface
{
    /**
     * @param DraftContext $draftContext
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     */
    public function __construct(
        private readonly DraftContext $draftContext,
        private readonly PageFactory $resultPageFactory,
        private readonly ForwardFactory $resultForwardFactory
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->draftContext->isPreview()) {
            return $this->resultForwardFactory->create()->forward('noroute');
        }

        return $this->resultPageFactory->create();
    }
}
