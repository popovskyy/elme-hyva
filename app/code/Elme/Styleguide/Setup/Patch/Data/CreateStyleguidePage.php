<?php

declare(strict_types=1);

namespace Elme\Styleguide\Setup\Patch\Data;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Creates the /styleguide CMS page. Its content comes from the theme layout cms_page_view_id_styleguide.
 */
class CreateStyleguidePage implements DataPatchInterface
{
    private const IDENTIFIER = 'styleguide';

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        try {
            $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return $this;
        } catch (NoSuchEntityException) {
            // not created yet
        }

        /** @var PageInterface $page */
        $page = $this->pageFactory->create();
        $page->setIdentifier(self::IDENTIFIER)
            ->setTitle('Style guide')
            ->setPageLayout('1column')
            ->setContent('')
            ->setIsActive(true);
        $page->setData('stores', [Store::DEFAULT_STORE_ID]);
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
