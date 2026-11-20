<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Elme\LoginOnly\Model\LoginState;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Dusty-blue top bar (FE-05): USP + one login-state link from CMS block header_promo.
 *
 * CMS contract (identifier header_promo) — three slots; theme shows only one link:
 * - [data-header-promo="usp"] — plain text / p / span (never a heading)
 * - [data-header-promo="stockist"] — <a> for guests → /become-a-stockist
 * - [data-header-promo="inspiration"] — <a> for logged-in → /shop-the-looks
 * Visibility is login/FPC-driven; do not paste both links expecting both to show.
 */
class HeaderPromo implements ArgumentInterface
{
    private const BLOCK_ID = 'header_promo';

    private const DEFAULT_USP = "Australia's leading wholesale supplier of artificial flora and home decor";

    private const DEFAULT_STOCKIST_LABEL = 'Become a Stockist';

    private const DEFAULT_STOCKIST_PATH = 'become-a-stockist';

    private const DEFAULT_INSPIRATION_LABEL = 'Inspiration';

    private const DEFAULT_INSPIRATION_PATH = 'shop-the-looks';

    private ?array $slots = null;

    private ?bool $blockActive = null;

    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly FilterProvider $filterProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoginState $loginState,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Hide the strip only when the CMS block exists and is disabled.
     * Missing block (pre-patch) still renders production defaults.
     */
    public function shouldRender(): bool
    {
        $this->slots();
        return $this->blockActive !== false;
    }

    public function isLoggedIn(): bool
    {
        return $this->loginState->isLoggedIn();
    }

    public function getUspText(): string
    {
        $usp = trim($this->slots()['usp']);
        return $usp !== '' ? $usp : self::DEFAULT_USP;
    }

    public function getLinkLabel(): string
    {
        if ($this->isLoggedIn()) {
            $label = trim($this->slots()['inspiration_label']);
            return $label !== '' ? $label : self::DEFAULT_INSPIRATION_LABEL;
        }

        $label = trim($this->slots()['stockist_label']);
        return $label !== '' ? $label : self::DEFAULT_STOCKIST_LABEL;
    }

    public function getLinkUrl(): string
    {
        if ($this->isLoggedIn()) {
            $href = trim($this->slots()['inspiration_href']);
            return $href !== '' ? $href : $this->directUrl(self::DEFAULT_INSPIRATION_PATH);
        }

        $href = trim($this->slots()['stockist_href']);
        return $href !== '' ? $href : $this->directUrl(self::DEFAULT_STOCKIST_PATH);
    }

    private function directUrl(string $path): string
    {
        return $this->urlBuilder->getUrl('', ['_direct' => $path]);
    }

    /**
     * @return array{
     *     usp: string,
     *     stockist_label: string,
     *     stockist_href: string,
     *     inspiration_label: string,
     *     inspiration_href: string
     * }
     */
    private function slots(): array
    {
        if ($this->slots !== null) {
            return $this->slots;
        }

        $this->slots = [
            'usp' => '',
            'stockist_label' => '',
            'stockist_href' => '',
            'inspiration_label' => '',
            'inspiration_href' => '',
        ];

        $storeId = (int) $this->storeManager->getStore()->getId();
        try {
            // Core loader only returns active blocks for a store.
            $block = $this->getBlockByIdentifier->execute(self::BLOCK_ID, $storeId);
        } catch (NoSuchEntityException) {
            $this->blockActive = $this->blockExists($storeId) ? false : null;
            return $this->slots;
        }

        $this->blockActive = true;

        $html = (string) $block->getContent();
        if ($html === '') {
            return $this->slots;
        }

        try {
            $html = $this->filterProvider->getBlockFilter()->filter($html);
        } catch (\Throwable) {
            // keep raw content if directives fail
        }

        $this->slots = $this->parseSlots($html);
        return $this->slots;
    }

    /**
     * Whether header_promo exists for the store (or all stores), active or not.
     */
    private function blockExists(int $storeId): bool
    {
        $collection = $this->blockCollectionFactory->create();
        $collection->addFieldToFilter('identifier', self::BLOCK_ID);
        $collection->addStoreFilter($storeId);
        return $collection->getSize() > 0;
    }

    /**
     * @return array{
     *     usp: string,
     *     stockist_label: string,
     *     stockist_href: string,
     *     inspiration_label: string,
     *     inspiration_href: string
     * }
     */
    private function parseSlots(string $html): array
    {
        $empty = [
            'usp' => '',
            'stockist_label' => '',
            'stockist_href' => '',
            'inspiration_label' => '',
            'inspiration_href' => '',
        ];

        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="elme-header-promo-root">' . $html . '</div>';
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $empty;
        }

        $xpath = new DOMXPath($dom);
        $uspNode = $xpath->query('//*[@data-header-promo="usp"]')->item(0);
        $stockist = $xpath->query('//*[@data-header-promo="stockist"]')->item(0);
        $inspiration = $xpath->query('//*[@data-header-promo="inspiration"]')->item(0);

        return [
            'usp' => $uspNode instanceof DOMElement ? trim($uspNode->textContent ?? '') : '',
            'stockist_label' => $stockist instanceof DOMElement ? trim($stockist->textContent ?? '') : '',
            'stockist_href' => $stockist instanceof DOMElement ? trim($stockist->getAttribute('href')) : '',
            'inspiration_label' => $inspiration instanceof DOMElement
                ? trim($inspiration->textContent ?? '')
                : '',
            'inspiration_href' => $inspiration instanceof DOMElement
                ? trim($inspiration->getAttribute('href'))
                : '',
        ];
    }
}
