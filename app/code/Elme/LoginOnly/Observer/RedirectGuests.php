<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Observer;

use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\UrlInterface;

/**
 * Sends guests who open a login-only page (cart, checkout, wishlist, Quick Order, Virtual Catalogues...) to the
 * login page with "Please login to access this page." and brings them back to that page after login.
 *
 * Redirects are 302 and never stored by the full page cache.
 */
class RedirectGuests implements ObserverInterface
{
    /**
     * @param string[] $gatedActions Full action names or their prefixes: "checkout" gates checkout_*,
     *                               "catalog_product_compare_index" gates one page
     * @param string[] $gatedCmsPages CMS page identifiers
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CustomerUrl $customerUrl,
        private readonly ActionFlag $actionFlag,
        private readonly HttpResponse $response,
        private readonly RedirectInterface $redirect,
        private readonly UrlInterface $urlBuilder,
        private readonly MessageManager $messageManager,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly array $gatedActions = [],
        private readonly array $gatedCmsPages = []
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var HttpRequest $request */
        $request = $observer->getEvent()->getData('request');
        if (!$request instanceof HttpRequest || $this->customerSession->isLoggedIn() || !$this->isGated($request)) {
            return;
        }

        // A browser prefetch (hover, speculation rules) is not a visit: redirect it, but leave the return
        // URL and the message alone, or the guest sees "Please login" on a page they did open.
        if ($this->isPrefetch($request)) {
            $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
            $this->response->setRedirect($this->customerUrl->getLoginUrl());
            return;
        }

        // A GET page comes back to itself; a form post or ajax call comes back to the page it was sent from
        $returnUrl = $request->isGet() && !$request->isAjax()
            ? $this->urlBuilder->getCurrentUrl()
            : $this->redirect->getRefererUrl();
        $this->customerSession->setBeforeAuthUrl($returnUrl);

        // Core plugins (wishlist) may already have redirected with their own message
        if (!$this->messageManager->getMessages(false)->getCount()) {
            $this->messageManager->addErrorMessage(__('Please login to access this page.'));
        }

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->setRedirect($this->customerUrl->getLoginUrl());
    }

    private function isPrefetch(HttpRequest $request): bool
    {
        $purpose = strtolower((string)$request->getHeader('Sec-Purpose') . ' ' . (string)$request->getHeader('Purpose'));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }

    private function isGated(HttpRequest $request): bool
    {
        $fullActionName = $request->getFullActionName();
        foreach ($this->gatedActions as $gated) {
            if ($fullActionName === $gated || str_starts_with($fullActionName, $gated . '_')) {
                return true;
            }
        }

        if ($fullActionName === 'cms_page_view' && $this->gatedCmsPages) {
            try {
                $identifier = $this->pageRepository->getById((int) $request->getParam('page_id'))->getIdentifier();
            } catch (LocalizedException) {
                return false;
            }
            return in_array($identifier, $this->gatedCmsPages, true);
        }

        return false;
    }
}
