<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Preview;

use Bart\ContentStudio\Block\Entry as EntryBlock;
use Bart\ContentStudio\Model\Preview\DraftContext;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Renders the previewed draft on the sandbox page.
 *
 * The identifier comes from the token rather than the URL, and the content
 * itself is never touched here: this creates an ordinary `Block\Entry`, which
 * resolves through the repository and so picks up the draft from
 * {@see \Bart\ContentStudio\Plugin\Preview\DraftEntryPlugin}. The sandbox
 * therefore renders through exactly the same path a product page does.
 */
class Sandbox extends Template
{
    /**
     * @param Context $context
     * @param DraftContext $draftContext
     * @param SchemaConfig $schemaConfig
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly DraftContext $draftContext,
        private readonly SchemaConfig $schemaConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return (string)($this->draftContext->getDraft()['identifier'] ?? '');
    }

    /**
     * Unix timestamp the preview token stops working at, for the countdown in
     * the banner. Zero when the draft predates the stamp being written.
     *
     * @return int
     */
    public function getExpiresAt(): int
    {
        return (int)($this->draftContext->getDraft()['expires_at'] ?? 0);
    }

    /**
     * @return string
     */
    public function getTypeCode(): string
    {
        return (string)($this->draftContext->getDraft()['type'] ?? '');
    }

    /**
     * @return string
     */
    public function getTypeLabel(): string
    {
        $draft = $this->draftContext->getDraft();
        $typeCode = (string)($draft['type'] ?? '');
        $type = $this->schemaConfig->getType($typeCode);

        return (string)($type['label'] ?? $typeCode);
    }

    /**
     * @return string
     */
    public function getEntryHtml(): string
    {
        $identifier = $this->getIdentifier();

        if ($identifier === '') {
            return '';
        }

        /** @var EntryBlock $block */
        $block = $this->getLayout()->createBlock(EntryBlock::class);
        $block->setData('identifier', $identifier);

        return $block->toHtml();
    }
}
