<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Elme\QuickOrder\Model\CartEditor;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Controller\Cart\Add;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filter\LocalizedToNormalized;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Every add to cart (tile, product page, sticky bar) posts to /checkout/cart/add; here it goes into cart_list
 * instead of the quote, so no totals are collected. The answer keeps core's shape, which FE-70's button reads:
 * an ajax post gets JSON and the result is in the messages section ("You added ..." on success).
 * MOQ, stock and price are checked on the new total qty of the row (CartLines).
 */
class AddToCartListPlugin
{
    public function __construct(
        private readonly HttpRequest $request,
        private readonly CustomerSession $customerSession,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly QtyRules $qtyRules,
        private readonly CartEditor $cartEditor,
        private readonly MessageManager $messageManager,
        private readonly ResolverInterface $localeResolver,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $urlBuilder,
        private readonly RedirectInterface $redirect,
        private readonly JsonFactory $jsonFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Guests never get here with a cart: Elme_LoginOnly sends them to login first. Should one slip through, core runs.
     */
    public function aroundExecute(Add $subject, callable $proceed): mixed
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $proceed();
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            $this->messageManager->addErrorMessage(__('Your session has expired. Please reload the page.'));

            return $this->answer(false);
        }

        try {
            $product = $this->getProduct();
            $row = $this->getRowProduct($product);
            $refusal = $this->cartEditor->add(
                (int) $this->customerSession->getCustomerId(),
                (int) $this->customerSession->getCustomerGroupId(),
                (string) $row->getSku(),
                $this->getQty($row)
            );
            if ($refusal !== null) {
                $this->messageManager->addErrorMessage($refusal);

                return $this->answer(false);
            }

            $this->messageManager->addSuccessMessage(
                __('You added %1 to your cart.', $this->escaper->escapeHtml((string) $product->getName()))
            );

            return $this->answer(true);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Elme_QuickOrder: add to cart failed.', ['exception' => $e]);
            $this->messageManager->addErrorMessage(__('We can\'t add this item to your cart right now.'));
        }

        return $this->answer(false);
    }

    /**
     * @throws LocalizedException
     */
    private function getProduct(): Product
    {
        $productId = (int) $this->request->getParam('product');
        try {
            /** @var Product $product */
            $product = $this->productRepository->getById(
                $productId,
                false,
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException) {
            throw new LocalizedException(__('This product is no longer available.'));
        }

        return $product;
    }

    /**
     * The product a cart row holds: for a configurable, the simple product of the chosen options.
     *
     * @throws LocalizedException
     */
    private function getRowProduct(Product $product): Product
    {
        if ($product->getTypeId() !== Configurable::TYPE_CODE) {
            return $product;
        }

        $options = $this->request->getParam('super_attribute');
        /** @var Configurable $type */
        $type = $product->getTypeInstance();
        $child = is_array($options) && $options ? $type->getProductByAttributes($options, $product) : null;
        if (!$child) {
            throw new LocalizedException(__('Please choose the product options.'));
        }

        /** @var Product $simple */
        $simple = $this->productRepository->get(
            (string) $child->getSku(),
            false,
            (int) $this->storeManager->getStore()->getId()
        );

        return $simple;
    }

    /**
     * The posted qty in the shop's number format; none posted is the MOQ, as core's "1" would be refused.
     */
    private function getQty(Product $product): float
    {
        $qty = $this->request->getParam('qty');
        if ($qty === null || $qty === '') {
            return $this->qtyRules->getMinAndStep($product)['min'];
        }

        $filter = new LocalizedToNormalized(['locale' => $this->localeResolver->getLocale()]);

        return (float) $filter->filter((string) $qty);
    }

    /**
     * Core's answer: ajax gets JSON ({backUrl} when the admin wants a redirect to the cart), a form post goes back.
     */
    private function answer(bool $added): ResultInterface
    {
        $toCart = $added && $this->scopeConfig->isSetFlag('checkout/cart/redirect_to_cart', ScopeInterface::SCOPE_STORE);
        $cartUrl = $this->urlBuilder->getUrl('quick_order/cart');

        if ($this->request->isAjax()) {
            return $this->jsonFactory->create()->setData($toCart ? ['backUrl' => $cartUrl] : []);
        }

        return $this->redirectFactory->create()->setUrl($toCart ? $cartUrl : $this->redirect->getRefererUrl());
    }
}
