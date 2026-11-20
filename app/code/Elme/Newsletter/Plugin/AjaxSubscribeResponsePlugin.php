<?php

declare(strict_types=1);

namespace Elme\Newsletter\Plugin;

use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;
use Magento\Newsletter\Controller\Subscriber\NewAction;

/**
 * The footer form posts to the core newsletter/subscriber/new with X-Requested-With. Core still does all
 * the work (validation, "already subscribed", confirmation, reCAPTCHA observer); only the answer changes:
 * JSON {success, message} instead of a redirect plus a flash message, so the form shows the result inline.
 * Without JS the form posts normally and gets the core redirect.
 */
class AjaxSubscribeResponsePlugin
{
    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly JsonFactory $jsonFactory,
        private readonly InterpretationStrategyInterface $interpretationStrategy
    ) {
    }

    public function aroundExecute(NewAction $subject, callable $proceed): mixed
    {
        if (!$subject->getRequest()->isAjax()) {
            return $proceed();
        }

        // Messages queued by other requests stay in the session for the next page render
        $queued = $this->messageManager->getMessages(true)->getItems();
        $proceed();
        $added = $this->messageManager->getMessages(true)->getLastAddedMessage();
        if ($queued !== []) {
            $this->messageManager->addMessages($queued);
        }

        return $this->createResult($added);
    }

    private function createResult(?MessageInterface $message): Json
    {
        $text = $message
            ? trim(html_entity_decode(strip_tags($this->interpretationStrategy->interpret($message)), ENT_QUOTES))
            : '';

        return $this->jsonFactory->create()->setData([
            'success' => $message !== null && $message->getType() === MessageInterface::TYPE_SUCCESS,
            'message' => $text !== '' ? $text : (string) __('Something went wrong with the subscription.'),
        ]);
    }
}
