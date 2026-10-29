<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Elme\Theme\ViewModel\Faq;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see Faq
 */
class FaqTest extends TestCase
{
    public function testSkipsIncompleteRowsAndGroupsTopics(): void
    {
        $faq = $this->viewModel(false);
        $items = $faq->getItems([
            'items' => [
                ['topic' => 'Orders', 'question' => 'Do you have a minimum order?', 'answer' => ''],
                [
                    'topic' => 'Orders',
                    'question' => 'Do you have a minimum order?',
                    'anchor' => 'do-you-have-a-minimum-order',
                    'answer' => '<p>Depending on your location, our minimum order is either $500 plus GST or $1000 plus GST.</p>',
                    'next_step' => 'contact',
                ],
                [
                    'topic' => 'Location',
                    'question' => 'Where can I see Elme products?',
                    'answer' => '<p>Blackburn showroom.</p>',
                ],
            ],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame('do-you-have-a-minimum-order', $items[0]['anchor']);
        $this->assertStringContainsString('Contact Us', $items[0]['answer_html']);
        $this->assertStringContainsString('Contact Us', $items[0]['answer_text']);
        $this->assertStringContainsString('moq', $items[0]['search_text']);
        $this->assertStringContainsString('minimum order', $items[0]['search_text']);

        $groups = $faq->groupByTopic($items);
        $this->assertSame(['Location', 'Orders'], array_column($groups, 'label'));
        $this->assertSame('location', $groups[0]['slug']);
    }

    public function testDerivesUniqueAnchorsAndNormalisesDeliveryToShipping(): void
    {
        $faq = $this->viewModel(true);
        $items = $faq->getItems([
            'items' => [
                [
                    'topic' => 'Delivery',
                    'question' => 'What are your freight charges?',
                    'answer' => '<p>Freight is a percentage of the order.</p>',
                    'next_step' => 'become_stockist',
                ],
                [
                    'topic' => 'Shipping',
                    'question' => 'What are your freight charges?',
                    'answer' => '<p>Second freight question.</p>',
                ],
            ],
        ]);

        $this->assertSame('Shipping', $items[0]['topic']);
        $this->assertSame('what-are-your-freight-charges', $items[0]['anchor']);
        $this->assertSame('what-are-your-freight-charges-2', $items[1]['anchor']);
        $this->assertNull($items[0]['next_step']);
    }

    public function testQuickOrderNextStepIsSignInForGuests(): void
    {
        $faq = $this->viewModel(false);
        $items = $faq->getItems([
            'items' => [
                [
                    'topic' => 'Orders',
                    'question' => 'How do I place an order?',
                    'answer' => '<p>Order online after you have an account.</p>',
                    'next_step' => 'quick_order',
                ],
            ],
        ]);

        $this->assertSame('Sign in', $items[0]['next_step']['label']);
        $this->assertStringContainsString('customer/account/login', $items[0]['next_step']['href']);
    }

    private function viewModel(bool $loggedIn): Faq
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getBaseUrl')->willReturn('https://elme.test/');

        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        $urls = $this->createMock(UrlInterface::class);
        $urls->method('getUrl')->willReturnCallback(
            static fn (string $path): string => 'https://elme.test/' . ltrim($path, '/')
        );

        $login = $this->createMock(LoginState::class);
        $login->method('isLoggedIn')->willReturn($loggedIn);

        $entries = $this->createMock(EntryRepositoryInterface::class);

        return new Faq($entries, $stores, $urls, $login);
    }
}
