<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Snowdog\Menu\Api\Data\MenuInterfaceFactory;
use Snowdog\Menu\Api\Data\NodeInterfaceFactory;
use Snowdog\Menu\Api\MenuRepositoryInterface;
use Snowdog\Menu\Api\NodeRepositoryInterface;

/**
 * FE-08: seed Snowdog desktop mega menu (production tree) + promo CMS blocks.
 */
class CreateDesktopMegaMenu implements DataPatchInterface
{
    private const MENU_IDENTIFIER = 'hyva-topmenu-desktop';

    /** @var array<string, array{title:string,heading:string,cta:string,href:string,tone:string,image:string,alt:string}> */
    public const PROMO_BLOCKS = [
        'menu_promo_flowers' => [
            'title' => 'Menu promo — Flowers & Plants',
            'heading' => 'New season stems',
            'cta' => 'Shop new',
            'href' => '{{store direct_url="new.html"}}',
            'tone' => 'cool',
            'image' => 'elme/menu/flowers.jpg',
            'alt' => 'Artificial stems and florals',
        ],
        'menu_promo_homewares' => [
            'title' => 'Menu promo — Homewares',
            'heading' => 'Styled for trade',
            'cta' => 'Explore homewares',
            'href' => '{{store direct_url="homewares.html"}}',
            'tone' => 'sand',
            'image' => 'elme/menu/homewares.jpg',
            'alt' => 'Elme homewares styled room',
        ],
        'menu_promo_furniture' => [
            'title' => 'Menu promo — Furniture',
            'heading' => 'Made to order sofas',
            'cta' => 'View furniture',
            'href' => '{{store direct_url="furniture.html"}}',
            'tone' => 'cool',
            'image' => 'elme/menu/furniture.jpg',
            'alt' => 'Made to order furniture',
        ],
        'menu_promo_outdoor' => [
            'title' => 'Menu promo — Outdoor',
            'heading' => 'Outdoor planters',
            'cta' => 'Shop outdoor',
            'href' => '{{store direct_url="outdoor.html"}}',
            'tone' => 'sand',
            'image' => 'elme/menu/outdoor.jpg',
            'alt' => 'Elme showroom and outdoor pieces',
        ],
    ];

    public function __construct(
        private readonly MenuInterfaceFactory $menuFactory,
        private readonly MenuRepositoryInterface $menuRepository,
        private readonly NodeInterfaceFactory $nodeFactory,
        private readonly NodeRepositoryInterface $nodeRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier
    ) {
    }

    public function apply(): self
    {
        self::syncPromoImagesToMedia();
        foreach (self::PROMO_BLOCKS as $identifier => $meta) {
            $this->ensurePromoBlock($identifier, $meta);
        }

        $existing = $this->menuRepository->get(self::MENU_IDENTIFIER, Store::DEFAULT_STORE_ID);
        if ($existing->getMenuId()) {
            return $this;
        }

        $treePath = __DIR__ . '/files/hyva-topmenu-desktop.json';
        $payload = json_decode((string) file_get_contents($treePath), true, 512, JSON_THROW_ON_ERROR);

        $menu = $this->menuFactory->create();
        $menu->setTitle((string) $payload['title'])
            ->setIdentifier(self::MENU_IDENTIFIER)
            ->setCssClass((string) ($payload['css_class'] ?? 'main-menu'))
            ->setIsActive(1);
        $this->menuRepository->save($menu);
        $menu->saveStores([Store::DEFAULT_STORE_ID]);

        $this->createNodes((array) ($payload['nodes'] ?? []), (int) $menu->getId());

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $nodes
     */
    private function createNodes(array $nodes, int $menuId, int $level = 0, ?int $parentId = null): void
    {
        $position = 0;
        foreach ($nodes as $nodeData) {
            if (!is_array($nodeData)) {
                continue;
            }
            $children = $nodeData['nodes'] ?? [];
            unset($nodeData['nodes']);

            $node = $this->nodeFactory->create();
            $node->setMenuId($menuId)
                ->setType((string) ($nodeData['type'] ?? 'custom_url'))
                ->setContent((string) ($nodeData['content'] ?? ''))
                ->setClasses((string) ($nodeData['classes'] ?? ''))
                ->setTitle((string) ($nodeData['title'] ?? ''))
                ->setParentId($parentId)
                ->setLevel($level)
                ->setPosition($position++)
                ->setTarget(0)
                ->setIsActive(1);
            $this->nodeRepository->save($node);

            if (is_array($children) && $children !== []) {
                $this->createNodes($children, $menuId, $level + 1, (int) $node->getId());
            }
        }
    }

    /**
     * @param array{title:string,heading:string,cta:string,href:string,tone:string,image:string,alt:string} $meta
     */
    public static function promoHtml(array $meta): string
    {
        $toneClass = $meta['tone'] === 'sand' ? 'menu-promo--sand' : 'menu-promo--cool';
        $image = htmlspecialchars($meta['image'], ENT_QUOTES);
        $alt = htmlspecialchars($meta['alt'], ENT_QUOTES);
        $heading = htmlspecialchars($meta['heading'], ENT_QUOTES);
        $cta = htmlspecialchars($meta['cta'], ENT_QUOTES);

        // Magento CMS directives need double quotes inside {{…}}; href already carries them.
        return '<div class="menu-promo ' . $toneClass . '">'
            . '<a class="menu-promo__link" href="' . $meta['href'] . '">'
            . '<span class="menu-promo__media">'
            . '<img src="{{media url="' . $image . '"}}" alt="' . $alt . '" width="640" height="480" loading="lazy" />'
            . '</span>'
            . '<span class="menu-promo__body">'
            . '<span class="menu-promo__heading">' . $heading . '</span>'
            . '<span class="menu-promo__cta">' . $cta . '</span>'
            . '</span>'
            . '</a></div>';
    }

    /**
     * Theme-tracked originals → pub/media (CMS {{media}} needs the media tree).
     */
    public static function syncPromoImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/menu';
        $targetDir = BP . '/pub/media/elme/menu';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }
        foreach (['flowers.jpg', 'homewares.jpg', 'furniture.jpg', 'outdoor.jpg'] as $file) {
            $from = $sourceDir . '/' . $file;
            $to = $targetDir . '/' . $file;
            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    /**
     * @param array{title:string,heading:string,cta:string,href:string,tone:string,image:string,alt:string} $meta
     */
    private function ensurePromoBlock(string $identifier, array $meta): void
    {
        try {
            $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $block = $this->blockFactory->create();
        $block->setIdentifier($identifier)
            ->setTitle($meta['title'])
            ->setContent(self::promoHtml($meta))
            ->setIsActive(true);
        $block->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->blockRepository->save($block);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
