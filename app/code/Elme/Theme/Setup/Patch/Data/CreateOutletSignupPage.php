<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-91: seed /elme-outlet-signup. The Klaviyo embed mounts from the theme layout template
 * (cms_page_view_id_elme-outlet-signup.xml); CMS body stays empty so admin can add copy later.
 */
class CreateOutletSignupPage implements DataPatchInterface
{
    private const IDENTIFIER = 'elme-outlet-signup';

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        try {
            $page = $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::IDENTIFIER);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $page->setTitle('Elme Outlet Signup')
            ->setContentHeading('Elme Outlet Signup')
            ->setMetaTitle('Elme Outlet Signup | Elme Living')
            ->setMetaDescription(
                'Sign up for Elme Living outlet updates: new ex-display, sample and end-of-line stock'
                . ' at the Melbourne Outlet & Clearance Store.'
            )
            ->setPageLayout('1column')
            ->setContent('')
            ->setIsActive(true);

        $this->pageRepository->save($page);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            CreateOutletClearanceStorePage::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
