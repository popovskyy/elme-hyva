<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-52: configure CMS no-route for recovery meta + 1column; body comes from the theme.
 */
class ConfigureNoRoutePage implements DataPatchInterface
{
    private const IDENTIFIER = 'no-route';

    private const META_TITLE = 'Page not found | Elme Living';

    private const META_DESCRIPTION = 'Sorry — that page isn’t on Elme Living. Go home, contact our wholesale team, or search artificial flora, homewares and furniture.';

    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        try {
            $page = $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }

        $page->setTitle('404 Not Found')
            ->setMetaTitle(self::META_TITLE)
            ->setMetaDescription(self::META_DESCRIPTION)
            ->setPageLayout('1column')
            ->setContent('')
            ->setContentHeading('');

        $this->pageRepository->save($page);

        return $this;
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
