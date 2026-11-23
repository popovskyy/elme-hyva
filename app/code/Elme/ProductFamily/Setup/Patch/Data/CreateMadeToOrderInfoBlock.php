<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-85: the text behind the info icon next to "Made To Order:" above the PDP fabric swatches. Created
 * once; after that the content is edited in Content > Blocks (identifier pdp_make_to_order_info_tooltip).
 */
class CreateMadeToOrderInfoBlock implements DataPatchInterface
{
    public const IDENTIFIER = 'pdp_make_to_order_info_tooltip';

    public function __construct(
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier
    ) {
    }

    public function apply(): self
    {
        try {
            $this->getBlockByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return $this;
        } catch (NoSuchEntityException) {
            // create below
        }

        $block = $this->blockFactory->create();
        $block->setIdentifier(self::IDENTIFIER)
            ->setTitle('PDP made to order info tooltip')
            ->setContent(
                '<p><strong>Quick Ship:</strong> Please check your delivery estimate below.</p>'
                . '<p><strong>Made To Order:</strong> Custom-made just for you. Please allow up to 14 weeks for delivery.</p>'
            )
            ->setIsActive(true);
        $block->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->blockRepository->save($block);

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
