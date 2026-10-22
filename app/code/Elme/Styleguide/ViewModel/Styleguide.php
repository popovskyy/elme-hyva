<?php

declare(strict_types=1);

namespace Elme\Styleguide\ViewModel;

use Magento\Framework\Data\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;
use Magento\Theme\Block\Html\Breadcrumbs;
use Magento\Theme\Block\Html\Pager;

class Styleguide implements ArgumentInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * The real pager template over a fake 200-item list, 10 per page; its links work (?p=N)
     */
    public function getPagerHtml(LayoutInterface $layout): string
    {
        $collection = $this->collectionFactory->create();
        for ($i = 1; $i <= 200; $i++) {
            $collection->addItem(new DataObject(['id' => $i]));
        }

        /** @var Pager $pager */
        $pager = $layout->createBlock(Pager::class, 'elme.styleguide.pager');
        $pager->setAvailableLimit([10 => 10])
            ->setShowPerPage(false)
            ->setShowAmounts(false)
            ->setCollection($collection);

        return $pager->toHtml();
    }

    /**
     * The real breadcrumbs template with three sample crumbs (no BreadcrumbList JSON-LD)
     */
    public function getBreadcrumbsHtml(LayoutInterface $layout): string
    {
        /** @var Breadcrumbs $breadcrumbs */
        $breadcrumbs = $layout->createBlock(Breadcrumbs::class, '', ['data' => ['skip_ld_json_schema' => true]]);
        $breadcrumbs->setTemplate('Magento_Theme::html/breadcrumbs.phtml')
            ->addCrumb('home', ['label' => __('Home'), 'title' => __('Go to Home Page'), 'link' => '/'])
            ->addCrumb('category', ['label' => __('Furniture'), 'title' => __('Furniture'), 'link' => '/styleguide'])
            ->addCrumb('product', ['label' => __('Rattan Armchair')]);

        return $breadcrumbs->toHtml();
    }

    /**
     * @param array<string, mixed> $data see Magento_Theme::elme/qty-stepper.phtml
     */
    public function getQtyStepperHtml(LayoutInterface $layout, array $data): string
    {
        return $layout->createBlock(Template::class, '', ['data' => $data])
            ->setTemplate('Magento_Theme::elme/qty-stepper.phtml')
            ->toHtml();
    }

    /**
     * @param array<string, mixed> $data see Magento_Theme::elme/empty-state.phtml
     */
    public function getEmptyStateHtml(LayoutInterface $layout, array $data): string
    {
        return $layout->createBlock(Template::class, '', ['data' => $data])
            ->setTemplate('Magento_Theme::elme/empty-state.phtml')
            ->toHtml();
    }

    public function getFeedbackDemoHtml(LayoutInterface $layout): string
    {
        return $layout->createBlock(Template::class)
            ->setTemplate('Magento_Theme::elme/feedback-demo.phtml')
            ->toHtml();
    }
}
