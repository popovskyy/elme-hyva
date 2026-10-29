<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-46: refresh Contact Us CMS intro now that the form mounts via layout.
 */
class UpdateContactUsCmsIntro implements DataPatchInterface
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        $path = __DIR__ . '/files/help/contact-us.html';
        $content = is_readable($path) ? (string) file_get_contents($path) : '';
        if ($content === '') {
            return $this;
        }

        try {
            $page = $this->getPageByIdentifier->execute('contact-us', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }

        $page->setContent($content);
        $this->pageRepository->save($page);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateHelpCmsPages::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
