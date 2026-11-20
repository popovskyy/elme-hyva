<?php

declare(strict_types=1);

namespace Elme\RecentlyViewed\ViewModel;

use Elme\Theme\ViewModel\CategoryBanner;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Payload written to browser storage when a category PLP is viewed.
 */
class CategoryRecorder implements ArgumentInterface
{
    public function __construct(
        private readonly CategoryBanner $categoryBanner,
        private readonly Config $config
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->getCategoryId() > 0;
    }

    public function getCategoryId(): int
    {
        $category = $this->categoryBanner->getCategory();

        return $category ? (int) $category->getId() : 0;
    }

    /**
     * @return array{id: int, name: string, url: string, image: string}
     */
    public function getCategoryData(): array
    {
        $category = $this->categoryBanner->getCategory();
        if (!$category) {
            return ['id' => 0, 'name' => '', 'url' => '', 'image' => ''];
        }

        return [
            'id' => (int) $category->getId(),
            'name' => (string) $category->getName(),
            'url' => (string) $category->getUrl(),
            'image' => $this->categoryBanner->getDesktopImageUrl(),
        ];
    }
}
