<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Snowdog\Menu\Api\Data\MenuInterfaceFactory;
use Snowdog\Menu\Api\Data\NodeInterfaceFactory;
use Snowdog\Menu\Api\MenuRepositoryInterface;
use Snowdog\Menu\Api\NodeRepositoryInterface;

/**
 * FE-09: seed Snowdog mobile menu by cloning hyva-topmenu-desktop (same tree).
 * Identifier hyva-topmenu-mobile so templates resolve under that folder; cms_block
 * promo tiles are desktop-only and are skipped.
 */
class CreateMobileMenuFromDesktop implements DataPatchInterface
{
    private const MENU_IDENTIFIER = 'hyva-topmenu-mobile';

    private const SOURCE_IDENTIFIER = 'hyva-topmenu-desktop';

    public function __construct(
        private readonly MenuInterfaceFactory $menuFactory,
        private readonly MenuRepositoryInterface $menuRepository,
        private readonly NodeInterfaceFactory $nodeFactory,
        private readonly NodeRepositoryInterface $nodeRepository
    ) {
    }

    public function apply(): self
    {
        $existing = $this->menuRepository->get(self::MENU_IDENTIFIER, Store::DEFAULT_STORE_ID);
        if ($existing->getMenuId()) {
            return $this;
        }

        $desktop = $this->menuRepository->get(self::SOURCE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        if (!$desktop->getMenuId()) {
            // Desktop mega menu (FE-08) must exist first — same tree for QA.
            return $this;
        }

        $this->cloneFromMenu((int) $desktop->getId());
        return $this;
    }

    private function cloneFromMenu(int $sourceMenuId): void
    {
        $menu = $this->menuFactory->create();
        $menu->setTitle('Top menu mobile')
            ->setIdentifier(self::MENU_IDENTIFIER)
            ->setCssClass('main-menu')
            ->setIsActive(1);
        $this->menuRepository->save($menu);
        $menu->saveStores([Store::DEFAULT_STORE_ID]);
        $targetMenuId = (int) $menu->getId();

        $nodes = $this->nodeRepository->getByMenu($sourceMenuId);
        $byParent = [];
        foreach ($nodes as $node) {
            if (!$node->getIsActive()) {
                continue;
            }
            if ($node->getType() === 'cms_block') {
                continue;
            }
            $parentId = (int) ($node->getParentId() ?: 0);
            $byParent[$parentId][] = $node;
        }

        $this->cloneLevel($byParent, $targetMenuId, 0, 0, null);
    }

    /**
     * @param array<int, array<int, \Snowdog\Menu\Api\Data\NodeInterface>> $byParent
     */
    private function cloneLevel(
        array $byParent,
        int $targetMenuId,
        int $sourceParentId,
        int $level,
        ?int $newParentId
    ): void {
        if (!isset($byParent[$sourceParentId])) {
            return;
        }

        $position = 0;
        foreach ($byParent[$sourceParentId] as $source) {
            $node = $this->nodeFactory->create();
            $node->setMenuId($targetMenuId)
                ->setType((string) $source->getType())
                ->setContent((string) $source->getContent())
                ->setClasses((string) $source->getClasses())
                ->setTitle((string) $source->getTitle())
                ->setParentId($newParentId)
                ->setLevel($level)
                ->setPosition($position++)
                ->setTarget((int) $source->getTarget())
                ->setIsActive(1);
            $this->nodeRepository->save($node);

            $this->cloneLevel(
                $byParent,
                $targetMenuId,
                (int) $source->getId(),
                $level + 1,
                (int) $node->getId()
            );
        }
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
