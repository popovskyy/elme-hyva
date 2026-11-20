<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Plugin;

use Elme\QuickOrder\Model\QuoteBuilder;
use Magento\Checkout\Controller\Index\Index;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Psr\Log\LoggerInterface;

/**
 * /checkout always starts from cart_list: the quote is rebuilt before core checks it (QuoteBuilder). A row that
 * cannot be ordered sends the customer back to the cart page with its reason; the cart answer shows the same
 * reason on the row. Elme_Checkout then works on the quote as before.
 */
class RebuildQuoteOnCheckoutPlugin
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly QuoteBuilder $quoteBuilder,
        private readonly MessageManager $messageManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function aroundExecute(Index $subject, callable $proceed): mixed
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $proceed();
        }

        try {
            $refused = $this->quoteBuilder->rebuild(
                (int) $this->customerSession->getCustomerId(),
                (int) $this->customerSession->getCustomerGroupId()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Elme_QuickOrder: the quote could not be rebuilt from the cart.', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('We can\'t open checkout right now. Please try again.'));

            return $this->redirectFactory->create()->setPath('quick_order/cart');
        }

        if (!$refused) {
            return $proceed();
        }

        $this->messageManager->addErrorMessage(
            __('Some products in your cart cannot be ordered right now. Change or remove them to continue.')
        );
        foreach ($refused as $sku => $reason) {
            $this->messageManager->addErrorMessage(__('%1: %2', $sku, $reason));
        }

        return $this->redirectFactory->create()->setPath('quick_order/cart');
    }
}
