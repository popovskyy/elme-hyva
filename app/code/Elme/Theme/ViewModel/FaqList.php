<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Content Studio type `faq_list` (etc/content_types.xml, FE-63): a short FAQ of plain-text questions and
 * answers. The template and the page JSON-LD (getSchemaQuestions) read the same items, so the FAQPage
 * answers are the visible words.
 */
class FaqList implements ArgumentInterface
{
    public const MAX_ITEMS = 10;

    /**
     * @param array<string, mixed> $content Block\Entry::getContent() or the entry data
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * Items with a question and an answer, in editor order. `id` is the link anchor (unique on the
     * list); `paragraphs` is the answer split on blank lines.
     *
     * @param array<string, mixed> $content
     * @return list<array{id: string, question: string, paragraphs: list<string>, answer_text: string}>
     */
    public function getItems(array $content): array
    {
        $items = [];
        $used = [];

        foreach ((array)($content['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $question = $this->text($row, 'question');
            $answer = str_replace("\r\n", "\n", $this->text($row, 'answer'));

            if ($question === '' || $answer === '') {
                continue;
            }

            $paragraphs = array_values(array_filter(
                array_map('trim', preg_split('/\n\s*\n/', $answer) ?: []),
                static fn(string $paragraph): bool => $paragraph !== ''
            ));

            $id = $this->slug($this->text($row, 'anchor')) ?: $this->slug($question) ?: 'question';
            $base = $id;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = $base . '-' . $n;
            }
            $used[$id] = true;

            $items[] = [
                'id' => $id,
                'question' => $question,
                'paragraphs' => $paragraphs,
                'answer_text' => (string)preg_replace('/\s+/', ' ', implode(' ', $paragraphs)),
            ];

            if (count($items) === self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * schema.org Question nodes for FAQPage `mainEntity`, in the visible order.
     *
     * @param array<string, mixed> $content
     * @return list<array<string, mixed>>
     */
    public function getSchemaQuestions(array $content): array
    {
        $questions = [];

        foreach ($this->getItems($content) as $item) {
            $questions[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer_text']],
            ];
        }

        return $questions;
    }

    private function slug(string $value): string
    {
        $slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $value));

        return trim(substr(trim($slug, '-'), 0, 60), '-');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_scalar($value) ? trim((string)$value) : '';
    }
}
