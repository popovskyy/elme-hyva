<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\Render;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\View\Element\Template;

/**
 * Every Magento price box (product lists, sliders, widgets, wishlist, compare) goes through Pricing\Render.
 *
 * Guests: the final price becomes "Sign in to see pricing", every other price (tier, MSRP...) renders nothing.
 * Logged in: the final price becomes a placeholder that loads the customer's own price after render, so the
 * cached HTML (shared by the whole customer group) never holds a per-customer price.
 */
class PriceRenderPlugin
{
    private const GUEST_TEMPLATE = 'Elme_LoginOnly::price/sign-in.phtml';
    private const CUSTOMER_TEMPLATE = 'Elme_LoginOnly::price/customer-price.phtml';

    /** @var string[] Price codes that show the customer's own price when logged in */
    private const CUSTOMER_PRICE_CODES = ['final_price', 'wishlist_configured_price'];

    private ?Template $guestBlock = null;
    private ?Template $customerBlock = null;

    public function __construct(
        private readonly LoginState $loginState
    ) {
    }

    public function aroundRender(
        Render $subject,
        callable $proceed,
        $priceCode,
        SaleableInterface $saleableItem,
        array $arguments = []
    ): string {
        if ($this->loginState->isGuest()) {
            if ($priceCode !== 'final_price') {
                return '';
            }
            $this->guestBlock ??= $this->createBlock($subject, self::GUEST_TEMPLATE);
            return $this->guestBlock->setData('product_id', (int) $saleableItem->getId())->toHtml();
        }

        if (!in_array($priceCode, self::CUSTOMER_PRICE_CODES, true)) {
            return (string) $proceed($priceCode, $saleableItem, $arguments);
        }

        $this->customerBlock ??= $this->createBlock($subject, self::CUSTOMER_TEMPLATE);
        return $this->customerBlock
            ->setData('product_id', (int) $saleableItem->getId())
            ->setData('show_tax_label', ($arguments['zone'] ?? '') === Render::ZONE_ITEM_VIEW)
            ->toHtml();
    }

    /**
     * Templates may call renderAmount() directly (bundle options, custom option prices): nothing for guests.
     */
    public function aroundRenderAmount(
        Render $subject,
        callable $proceed,
        AmountInterface $amount,
        PriceInterface $price,
        ?SaleableInterface $saleableItem = null,
        array $arguments = []
    ): string {
        if ($this->loginState->isGuest()) {
            return '';
        }

        return (string) $proceed($amount, $price, $saleableItem, $arguments);
    }

    private function createBlock(Render $subject, string $template): Template
    {
        /** @var Template $block */
        $block = $subject->getLayout()->createBlock(Template::class);
        return $block->setTemplate($template);
    }
}
