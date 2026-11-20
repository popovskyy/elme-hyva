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
 * FE-93: seed /terms-conditions-of-trade (help shell, trade Ts&Cs body).
 */
class CreateTradeTermsCmsPage implements DataPatchInterface
{
    private const IDENTIFIER = 'terms-conditions-of-trade';

    private const FILE = 'terms-conditions-of-trade.html';

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        $content = $this->loadContent();

        try {
            $page = $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::IDENTIFIER);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $page->setTitle('Trade Terms & Conditions')
            ->setContentHeading('Trade Terms & Conditions')
            ->setMetaTitle('Trade Terms & Conditions | Elme Living')
            ->setMetaDescription(
                'Elme Living terms and conditions of trade for wholesale orders: pricing, credit,'
                . ' delivery, returns, title and PPSA.'
            )
            ->setPageLayout('2columns-left')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);

        return $this;
    }

    private function loadContent(): string
    {
        $path = __DIR__ . '/files/help/' . self::FILE;
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Missing help CMS seed file: ' . $path);
        }

        return trim($html);
    }

    public static function getDependencies(): array
    {
        return [
            CreateHelpCmsPages::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
