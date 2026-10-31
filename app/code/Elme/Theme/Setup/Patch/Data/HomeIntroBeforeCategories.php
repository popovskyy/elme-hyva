<?php
/**
 * Home "Calm gallery" order: hero, then the welcome intro with the trade facts, then the category grid
 * (docs/design/homepage-review-options.html). The page was seeded with the categories before the welcome
 * block, which suited the old "Statement" look but not the intro. Moves the home-welcome widget in front
 * of the home-category-tiles widget; does nothing if either is missing or already in that order.
 */

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class HomeIntroBeforeCategories implements DataPatchInterface
{
    private const WIDGET = '#<div data-content-type="content_studio"[^>]*>\{\{widget [^}]*identifier="%s"[^}]*\}\}</div>#';

    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function apply(): self
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }

        $content = (string)$page->getContent();
        $reordered = self::moveBefore($content, 'home-welcome', 'home-category-tiles');

        if ($reordered !== $content) {
            $page->setContent($reordered);
            $this->pageRepository->save($page);
        }

        return $this;
    }

    /**
     * Puts the widget $moving right before the widget $anchor. Returns the content unchanged when either
     * widget is missing or $moving already comes first.
     */
    public static function moveBefore(string $content, string $moving, string $anchor): string
    {
        if (!preg_match(sprintf(self::WIDGET, preg_quote($moving, '#')), $content, $move, PREG_OFFSET_CAPTURE)
            || !preg_match(sprintf(self::WIDGET, preg_quote($anchor, '#')), $content, $target, PREG_OFFSET_CAPTURE)
            || $move[0][1] < $target[0][1]
        ) {
            return $content;
        }

        $widget = $move[0][0];
        $without = substr_replace($content, '', $move[0][1], strlen($widget));

        return substr_replace($without, $widget, $target[0][1], 0);
    }

    public static function getDependencies(): array
    {
        return [HomeCalmGallery::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
