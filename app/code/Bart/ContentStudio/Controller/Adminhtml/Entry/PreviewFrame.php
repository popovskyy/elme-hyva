<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Block\Entry as EntryBlock;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Locale\Resolver as LocaleResolver;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\LayoutFactory;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The <iframe> document behind the grid's eye-button preview and the Page
 * Builder "Content Studio" stage: one entry, rendered by its storefront
 * template under a store's own theme, with that theme's compiled CSS (Hyva:
 * `css/styles.css`).
 *
 * Served from the admin, not from the store's domain, and that is the point.
 * The storefront sends X-Frame-Options SAMEORIGIN, and a store may live on
 * another domain than the admin; a document the admin serves itself is
 * same-origin, needs no header changes anywhere, and its height can be read
 * by the page that frames it.
 *
 * The entry is picked by `entry_id` (grid) or by `identifier` (Page Builder,
 * which stores the identifier in its widget directive, so the content stays
 * valid after an export/import that renumbers entries). `store_id` defaults to
 * the default store view. `bare=1` drops the frame's own vertical padding,
 * for the Page Builder stage. The container gutters stay: a section that
 * bleeds into them on the storefront (promo_split) must bleed in the frame
 * too, or the frame would clip it.
 *
 * Rendering happens inside store + area emulation - the same mechanism
 * transactional emails use - so templates, view models, `{{media}}` and asset
 * URLs resolve exactly as on the storefront. Afterwards the store's static and
 * media base URLs are made root-relative: `pub/static` and `pub/media` are the
 * same files on every domain this Magento serves, and fetching them from the
 * admin's own host keeps the theme's web fonts (no CORS headers on them) and
 * the blocks' CSS-mask icons working.
 */
class PreviewFrame extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry';

    /**
     * The Hyva theme's one compiled stylesheet (web/css/styles.css).
     */
    private const THEME_CSS = 'css/styles.css';

    /**
     * @param Context $context
     * @param EntryRepositoryInterface $entryRepository
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     * @param AppState $appState
     * @param LayoutFactory $layoutFactory
     * @param AssetRepository $assetRepository
     * @param LocaleResolver $localeResolver
     * @param RawFactory $rawFactory
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     * @param array<string, string> $scripts Storefront scripts the blocks need, as asset ids. Extended per
     *                                       module in di.xml, like Block\Entry's typeViewModels.
     */
    public function __construct(
        Context $context,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation,
        private readonly AppState $appState,
        private readonly LayoutFactory $layoutFactory,
        private readonly AssetRepository $assetRepository,
        private readonly LocaleResolver $localeResolver,
        private readonly RawFactory $rawFactory,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger,
        private readonly array $scripts = []
    ) {
        parent::__construct($context);
    }

    /**
     * @return Raw
     */
    public function execute(): Raw
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);

        $bare = (bool)$this->getRequest()->getParam('bare');

        try {
            $store = $this->resolveStore();
            $entry = $this->resolveEntry((int)$store->getId());
        } catch (NoSuchEntityException $e) {
            return $result->setContents($this->messageDocument((string)__('This entry or store no longer exists.')));
        }

        try {
            $page = $this->render($entry, (int)$store->getId(), $bare);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $page = null;
        }

        if ($page === null) {
            return $result->setContents($this->messageDocument((string)__('The preview could not be rendered.')));
        }

        return $result->setContents($page);
    }

    /**
     * @return Store
     * @throws NoSuchEntityException
     */
    private function resolveStore(): Store
    {
        $storeId = (int)$this->getRequest()->getParam('store_id');

        /** @var Store $store */
        $store = $storeId > 0
            ? $this->storeManager->getStore($storeId)
            : $this->storeManager->getDefaultStoreView();

        if (!$store) {
            throw new NoSuchEntityException(__('No store view.'));
        }

        return $store;
    }

    /**
     * By id when the caller has one; otherwise by identifier, resolved for the
     * store the way the storefront resolves it (store-specific first, then All
     * Store Views).
     *
     * @param int $storeId
     * @return EntryInterface
     * @throws NoSuchEntityException
     */
    private function resolveEntry(int $storeId): EntryInterface
    {
        $entryId = (int)$this->getRequest()->getParam('entry_id');

        if ($entryId > 0) {
            return $this->entryRepository->getById($entryId);
        }

        $identifier = trim((string)$this->getRequest()->getParam('identifier'));

        if ($identifier === '') {
            throw new NoSuchEntityException(__('No entry given.'));
        }

        return $this->entryRepository->getByIdentifier($identifier, $storeId);
    }

    /**
     * @param EntryInterface $entry
     * @param int $storeId
     * @param bool $bare
     * @return string|null
     */
    private function render(EntryInterface $entry, int $storeId, bool $bare): ?string
    {
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);

        try {
            $parts = $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use ($entry): array {
                /** @var Store $store */
                $store = $this->storeManager->getStore();

                return [
                    'html' => $this->renderEntry($entry),
                    'css' => $this->assetUrl(self::THEME_CSS),
                    'scripts' => array_map([$this, 'assetUrl'], array_values($this->scripts)),
                    'bases' => [
                        $store->getBaseUrl(UrlInterface::URL_TYPE_STATIC, true),
                        $store->getBaseUrl(UrlInterface::URL_TYPE_STATIC, false),
                        $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, true),
                        $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, false),
                    ],
                    'locale' => $this->localeResolver->getLocale(),
                ];
            });
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }

        return $this->document($this->relativize($parts), $bare);
    }

    /**
     * @param EntryInterface $entry
     * @return string
     */
    private function renderEntry(EntryInterface $entry): string
    {
        /** @var EntryBlock $block */
        $block = $this->layoutFactory->create()->createBlock(EntryBlock::class, '', [
            'data' => ['area' => Area::AREA_FRONTEND, 'identifier' => $entry->getIdentifier()],
        ]);

        return (string)$block->setPreviewEntry($entry)->toHtml();
    }

    /**
     * @param string $fileId
     * @return string
     */
    private function assetUrl(string $fileId): string
    {
        return $this->assetRepository->getUrlWithParams($fileId, ['_secure' => true]);
    }

    /**
     * `https://www.example.com/static/version1/` -> `/static/version1/`,
     * everywhere in the rendered parts. A base with no path of its own (a bare
     * CDN host) stays absolute: there is no path to rebase onto the admin host.
     *
     * @param array<string, mixed> $parts
     * @return array<string, mixed>
     */
    private function relativize(array $parts): array
    {
        $map = [];

        foreach (array_unique($parts['bases']) as $base) {
            $path = (string)parse_url($base, PHP_URL_PATH);

            if ($path !== '' && $path !== '/') {
                $map[$base] = $path;
            }
        }

        // Longest first, so a base never gets half-replaced by a shorter one.
        uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $swap = static fn (string $value): string => strtr($value, $map);

        $parts['html'] = $swap($parts['html']);
        $parts['css'] = $swap($parts['css']);
        $parts['scripts'] = array_map($swap, $parts['scripts']);

        return $parts;
    }

    /**
     * The same shells a Hyva CMS page renders the widget in (read off a CMS
     * page of this theme): `body.cms-page-view.page-layout-1column` >
     * `.page-wrapper` > `main#maincontent.page-main` > `.columns` (the theme's
     * container) > `.column.main`, so container width, gutters and type apply.
     * Links open in a new tab: navigating the frame itself to the storefront
     * would be refused by its SAMEORIGIN header.
     *
     * @param array<string, mixed> $parts
     * @param bool $bare
     * @return string
     */
    private function document(array $parts, bool $bare): string
    {
        $html = trim((string)$parts['html']);
        $empty = __('This entry renders nothing in this store - a required field may be empty.');
        $body = $html !== ''
            ? $html
            : '<p class="py-12 text-center text-ink-muted">' . $this->escaper->escapeHtml($empty) . '</p>';
        $lang = strtok((string)$parts['locale'], '_');

        $scripts = implode('', array_map(
            fn (string $url): string => '<script src="' . $this->escaper->escapeUrl($url) . '" defer></script>',
            $parts['scripts']
        ));

        // overflow: hidden - the framing page sizes the frame to `.page-main`,
        // so the frame never needs scrollbars of its own. The theme's
        // min-height (100vh on `.page-wrapper`) and the vertical margin on
        // `.page-main` go: measured against a frame whose height is itself the
        // last measurement, a min-height lets it grow but never shrink, and a
        // margin is outside the measured box and would be cut off.
        $frameCss = 'html,body{margin:0;overflow:hidden}'
            . '.page-wrapper,.page-main,.column.main{min-height:0!important}'
            . '.page-main{margin:0!important;' . ($bare ? 'padding:0' : 'padding:24px 0') . '}';

        return '<!doctype html><html lang="' . $this->escaper->escapeHtmlAttr((string)$lang) . '">'
            . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><base target="_blank">'
            . '<link rel="stylesheet" href="' . $this->escaper->escapeUrl($parts['css']) . '">'
            . '<style>' . $frameCss . '</style></head>'
            . '<body class="cms-page-view page-layout-1column cs-preview-frame">'
            . '<div class="page-wrapper"><main id="maincontent" class="page-main"><div class="columns">'
            . '<div class="column main">' . $body . '</div></div></main></div>'
            . $scripts . '</body></html>';
    }

    /**
     * @param string $message
     * @return string
     */
    private function messageDocument(string $message): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head>'
            . '<body style="margin:0;padding:48px 24px;text-align:center;color:#6b6b6b;font:14px/1.5 sans-serif">'
            . $this->escaper->escapeHtml($message) . '</body></html>';
    }
}
