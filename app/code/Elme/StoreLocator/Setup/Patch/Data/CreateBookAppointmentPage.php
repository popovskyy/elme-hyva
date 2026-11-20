<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Setup\Patch\Data;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-90: seed /book-appointment. The form mounts from layout cms_page_view_id_book-appointment.xml;
 * the CMS body stays empty so admin can add copy above it later.
 */
class CreateBookAppointmentPage implements DataPatchInterface
{
    private const IDENTIFIER = 'book-appointment';

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

        $page->setTitle('Book a Showroom Appointment')
            ->setContentHeading('Book a Showroom Appointment')
            ->setMetaTitle('Book a Showroom Appointment | Elme Living')
            ->setMetaDescription(
                'Book a visit to the Elme Living trade showroom in Blackburn, Melbourne, to see our furniture,'
                . ' artificial flowers and plants, and homewares in person. For registered trade customers.'
            )
            ->setPageLayout('1column')
            ->setContent('')
            ->setIsActive(true);

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
