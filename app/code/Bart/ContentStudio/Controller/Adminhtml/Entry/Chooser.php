<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Block\Adminhtml\Widget\Chooser as ChooserBlock;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\LayoutFactory;

/**
 * Grid source for the entry chooser, called by the widget insertion dialog and
 * again on every sort, filter and page click inside it.
 *
 * Returns raw grid HTML rather than a page result - that is the contract
 * `Magento\Widget\Block\Adminhtml\Widget\Chooser` expects from a `sourceUrl`.
 *
 * Guarded by `Bart_ContentStudio::entry` rather than
 * `Magento_Widget::widget_instance` as the core choosers are. Somebody who may
 * not see Content Studio entries in the grid should not be able to enumerate
 * them through the widget dialog either.
 *
 * It does not extend Controller\Adminhtml\Entry: that base class exists to
 * build a full admin page (it initialises the menu and the page title), and
 * this action renders one block.
 *
 * GET and POST: the dialog's first load and every grid reload go through
 * Prototype's Ajax.Request, which POSTs. Declared GET-only, Magento answered
 * each of them with a 404 and "Select Entry..." opened nothing.
 */
class Chooser extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry';

    /**
     * @param Context $context
     * @param LayoutFactory $layoutFactory
     * @param RawFactory $resultRawFactory
     */
    public function __construct(
        Context $context,
        private readonly LayoutFactory $layoutFactory,
        private readonly RawFactory $resultRawFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $grid = $this->layoutFactory->create()->createBlock(
            ChooserBlock::class,
            '',
            ['data' => ['id' => $this->getRequest()->getParam('uniq_id')]]
        );

        return $this->resultRawFactory->create()->setContents($grid->toHtml());
    }
}
