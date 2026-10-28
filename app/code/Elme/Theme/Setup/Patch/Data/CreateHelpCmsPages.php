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
 * FE-45: seed the six help CMS pages (layout, meta, body) for the help shell.
 */
class CreateHelpCmsPages implements DataPatchInterface
{
    /**
     * @var list<array{
     *     identifier: string,
     *     title: string,
     *     content_heading: string,
     *     meta_title: string,
     *     meta_description: string,
     *     file: string
     * }>
     */
    private const PAGES = [
        [
            'identifier' => 'contact-us',
            'title' => 'Contact Us',
            'content_heading' => 'Contact Us',
            'meta_title' => 'Contact Us | Elme Living - Australia’s Homewares Wholesaler',
            'meta_description' => 'Get in touch with Elme Living. Contact us for inquiries about wholesale artificial flowers, plants, and homewares. We\'re here to support you!',
            'file' => 'contact-us.html',
        ],
        [
            'identifier' => 'shipping-and-returns',
            'title' => 'Shipping & Returns',
            'content_heading' => 'Shipping & Returns',
            'meta_title' => 'Shipping & Returns | Elme Living Delivery & Return Policy',
            'meta_description' => 'Learn about Elme Living\'s shipping options, delivery times, and return policy. Find details on freight charges, international shipping, and how to return a product.',
            'file' => 'shipping-and-returns.html',
        ],
        [
            'identifier' => 'faqs',
            'title' => 'Frequently Asked Questions',
            'content_heading' => 'Frequently Asked Questions',
            'meta_title' => 'Frequently Asked Questions | Elme Living Customer Support',
            'meta_description' => 'Find answers to common questions about Elme Living. Learn about our products, ordering process, payments, shipping, returns, and more. Get the help you need now!',
            'file' => 'faqs.html',
        ],
        [
            'identifier' => 'become-a-stockist',
            'title' => 'Become a Stockist',
            'content_heading' => 'Become a Stockist',
            'meta_title' => 'Become a Stockist | Elme Living - Wholesale Decor Partner',
            'meta_description' => 'Become an Elme Living stockist and access exclusive decor, premium support, and thousands of products to boost your business. Join us today!',
            'file' => 'become-a-stockist.html',
        ],
        [
            'identifier' => 'website-terms-conditions',
            'title' => 'Website Terms & Conditions',
            'content_heading' => 'Terms & Conditions',
            'meta_title' => 'Website Terms & Conditions | Elme Living',
            'meta_description' => 'Review Elme Living\'s terms and conditions for website use. Learn about cookies, intellectual property rights, and user responsibilities. By accessing, you agree to these terms.',
            'file' => 'website-terms-conditions.html',
        ],
        [
            'identifier' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'content_heading' => 'Privacy Policy',
            'meta_title' => 'Privacy Policy | Elme Living - Your Data, Protected',
            'meta_description' => 'Learn how Elme Living protects your personal information under the Privacy Act. View our practices on data collection, usage, security, and your access rights.',
            'file' => 'privacy-policy.html',
        ],
    ];

    public function __construct(
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        foreach (self::PAGES as $definition) {
            $this->upsertPage($definition);
        }

        return $this;
    }

    /**
     * @param array{
     *     identifier: string,
     *     title: string,
     *     content_heading: string,
     *     meta_title: string,
     *     meta_description: string,
     *     file: string
     * } $definition
     */
    private function upsertPage(array $definition): void
    {
        $content = $this->loadContent($definition['file']);

        try {
            $page = $this->getPageByIdentifier->execute($definition['identifier'], Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier($definition['identifier']);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $page->setTitle($definition['title'])
            ->setContentHeading($definition['content_heading'])
            ->setMetaTitle($definition['meta_title'])
            ->setMetaDescription($definition['meta_description'])
            ->setPageLayout('2columns-left')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);
    }

    private function loadContent(string $file): string
    {
        $path = __DIR__ . '/files/help/' . $file;
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Missing help CMS seed file: ' . $path);
        }

        return trim($html);
    }

    public static function getDependencies(): array
    {
        return [CreateSharedCmsBlocks::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
