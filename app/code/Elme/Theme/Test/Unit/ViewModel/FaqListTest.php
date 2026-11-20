<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\FaqList;
use PHPUnit\Framework\TestCase;

/**
 * @see FaqList
 */
class FaqListTest extends TestCase
{
    public function testItemsAnchorsParagraphsAndSchemaText(): void
    {
        $faq = new FaqList();
        $content = ['items' => [
            ['question' => 'Is the outlet open to the public?', 'answer' => 'Yes.', 'anchor' => 'open-to-the-public'],
            ['question' => 'Can I hold an item?', 'answer' => "Call us.\r\n\r\nPieces sell\nquickly."],
            ['question' => 'Can I hold an item?', 'answer' => 'Twice.'],
            ['question' => 'No answer', 'answer' => '  '],
        ]];

        $items = $faq->getItems($content);

        self::assertCount(3, $items);
        self::assertSame('open-to-the-public', $items[0]['id']);
        self::assertSame('can-i-hold-an-item', $items[1]['id']);
        self::assertSame('can-i-hold-an-item-2', $items[2]['id']);
        self::assertSame(['Call us.', "Pieces sell\nquickly."], $items[1]['paragraphs']);

        $questions = $faq->getSchemaQuestions($content);
        self::assertSame('Question', $questions[1]['@type']);
        self::assertSame('Call us. Pieces sell quickly.', $questions[1]['acceptedAnswer']['text']);
    }
}
