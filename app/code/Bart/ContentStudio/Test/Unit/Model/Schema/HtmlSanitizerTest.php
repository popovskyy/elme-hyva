<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Schema;

use Bart\ContentStudio\Model\Schema\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The security contract for `wysiwyg` fields, in the same spirit as
 * SafeFilterTest: a `wysiwyg` value is printed as markup at the render site,
 * and `Block\Entry::getContent()` hands values over unescaped by design, so
 * this class is the only thing standing between an author's paste buffer and a
 * stored `<script>`.
 *
 * Every plain `text` field is cleaned by strip_tags instead - the assertions
 * here are specifically about the tags that must *survive*, because a
 * sanitizer that keeps nothing is safe and useless.
 *
 * @see HtmlSanitizer
 */
class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[DataProvider('sanitizeProvider')]
    public function testSanitize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sanitizeProvider(): array
    {
        return [
            // What must survive - body copy an author legitimately writes.
            'paragraph with emphasis' => [
                '<p>Hello <strong>world</strong> and <em>friends</em></p>',
                '<p>Hello <strong>world</strong> and <em>friends</em></p>',
            ],
            'headings and lists' => [
                '<h2>Why</h2><ul><li>one</li><li>two</li></ul>',
                '<h2>Why</h2><ul><li>one</li><li>two</li></ul>',
            ],
            'blockquote' => ['<blockquote>Quoted</blockquote>', '<blockquote>Quoted</blockquote>'],
            'internal link' => [
                '<a href="/journal/post" title="Read">Read</a>',
                '<a href="/journal/post" title="Read">Read</a>',
            ],
            'non-ascii is not mangled' => [
                "<p>\u{041F}\u{043B}\u{043E}\u{0441}\u{043A}\u{0438}\u{0439} \u{0442}\u{0435}\u{043A}\u{0441}\u{0442} \u{2014} \u{0442}\u{0438}\u{0440}\u{0435}</p>",
                "<p>\u{041F}\u{043B}\u{043E}\u{0441}\u{043A}\u{0438}\u{0439} \u{0442}\u{0435}\u{043A}\u{0441}\u{0442} \u{2014} \u{0442}\u{0438}\u{0440}\u{0435}</p>",
            ],
            'libxml closes what the author left open' => [
                '<p>unclosed <strong>bold',
                '<p>unclosed <strong>bold</strong></p>',
            ],

            // What must not.
            'script is dropped with its body' => ['<p>x</p><script>alert(1)</script>', '<p>x</p>'],
            'style is dropped with its body' => ['<style>body{}</style><p>x</p>', '<p>x</p>'],
            'iframe is dropped with its body' => ['<iframe src="//evil"></iframe>ok', 'ok'],
            'unknown tag is unwrapped, text kept' => ['<span style="color:red">word</span>', 'word'],
            'div is unwrapped, children kept' => [
                '<div><h2>Heading</h2></div>',
                '<h2>Heading</h2>',
            ],
            'event handler attribute is stripped' => ['<p onclick="alert(1)">handler</p>', '<p>handler</p>'],
            'style attribute is stripped' => ['<p style="color:red">x</p>', '<p>x</p>'],
            'javascript href is stripped, text kept' => ['<a href="javascript:alert(1)">bad</a>', '<a>bad</a>'],
            'protocol-relative href is stripped' => ['<a href="//evil.com">go</a>', '<a>go</a>'],
            'backslash-escaped href is stripped' => ['<a href="/\evil.com">go</a>', '<a>go</a>'],
            'img is not on the list at all' => ['<img src=x onerror=alert(1)>', ''],
            // table/tr/td are not on the list. Unwrapping keeps the cell text
            // and welds neighbouring cells together - that is why a comparison
            // table is its own component, not a paste into wysiwyg.
            'table is unwrapped, cell text kept' => [
                '<table><tr><td>Firm</td><td>Plush</td></tr></table>',
                'FirmPlush',
            ],
            'comment is removed' => ['<!-- note --><p>after</p>', '<p>after</p>'],
            'empty stays empty' => ['', ''],
            'whitespace only collapses to empty' => ['   ', ''],
        ];
    }

    /**
     * A nested payload must not survive its wrapper being unwrapped: the walk
     * is depth-first for exactly this reason, so cleaning happens before the
     * disallowed parent dissolves and re-parents its children.
     */
    public function testNestingDoesNotSmuggleScriptPastAnUnwrappedParent(): void
    {
        $this->assertSame(
            'keep',
            $this->sanitizer->sanitize('<div><span><script>alert(1)</script>keep</span></div>')
        );
    }

    /**
     * A tab opened with target="_blank" gets a window.opener handle back to the
     * page that opened it unless rel says otherwise. Modern browsers imply
     * noopener; older ones do not, and the attribute costs nothing.
     */
    public function testTargetBlankGetsNoopener(): void
    {
        $result = $this->sanitizer->sanitize('<a href="https://example.com" target="_blank">out</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $result);
    }

    /**
     * mailto/tel are how an author links a contact detail. They are not in the
     * `url` field's own pattern - that one guards navigation targets - but a
     * body-copy link to an address is ordinary and safe.
     */
    public function testMailtoAndTelSurvive(): void
    {
        $this->assertStringContainsString(
            'href="mailto:hi@example.com"',
            $this->sanitizer->sanitize('<a href="mailto:hi@example.com">mail</a>')
        );
        $this->assertStringContainsString(
            'href="tel:+61000"',
            $this->sanitizer->sanitize('<a href="tel:+61000">call</a>')
        );
    }
}
