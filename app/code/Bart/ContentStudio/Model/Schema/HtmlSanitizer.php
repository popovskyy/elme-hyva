<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

/**
 * Reduces a `wysiwyg` field's HTML to a fixed allow-list of tags and attributes.
 *
 * Plain `text`/`textarea` fields are cleaned by strip_tags in
 * {@see SchemaValidator::normalizeField()}, which is the right answer for a
 * field that has no business carrying markup at all. Body copy does, so it
 * needs the opposite treatment: keep the structural tags an author legitimately
 * uses, drop everything else, and never trust the client to have done it.
 *
 * This is a normalizer, not an output escaper. `Block\Entry::getContent()`
 * hands values over unescaped by design and a `wysiwyg` value is printed as
 * markup at the render site, so this class is the *only* place standing between
 * an editor's paste buffer and a stored `<script>`. Everything not on the list
 * is unwrapped rather than deleted - a pasted `<span style="...">word</span>`
 * should lose the span and keep the word.
 */
class HtmlSanitizer
{
    /**
     * Tag => allowed attributes. A tag absent from this map is unwrapped.
     *
     * @var array<string, string[]>
     */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title', 'rel', 'target'],
        'sup' => [],
        'sub' => [],
    ];

    /**
     * Tags whose *content* is discarded along with the tag. Unwrapping these
     * would turn a script body into visible page text, which is worse than
     * losing it: the author would see the code appear and have no idea why.
     */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'select', 'textarea', 'button', 'noscript', 'template', 'svg', 'math',
    ];

    // Same rule SchemaValidator applies to `url` fields, plus mailto: and
    // tel:, which body copy links to and a button field does not.
    // The (?![/\\]) is what stops "//evil.com" and "/\evil.com" - both are
    // protocol-relative and leave the site while reading like an internal path.
    private const SAFE_HREF_PATTERN = '~^(#|/(?![/\\\\])|https?://|mailto:|tel:)~i';

    /**
     * @param string $html
     * @return string
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = $this->parse($html);
        $root = $this->rootOf($document);

        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        return trim($this->serializeChildren($root));
    }

    /**
     * The `<?xml encoding>` prologue is what makes libxml read the fragment as
     * UTF-8; without it a non-ASCII character is mangled into mojibake. It is
     * preferred over mb_convert_encoding('HTML-ENTITIES'), which is deprecated
     * as of PHP 8.2.
     *
     * @param string $html
     * @return \DOMDocument
     */
    private function parse(string $html): \DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="cs-sanitizer-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * The wrapper div, found by walking the document's own children rather than
     * by getElementById(): that method needs a DTD to know which attribute is
     * an ID, and loadHTML() does not give it one, so it returns null here.
     *
     * @param \DOMDocument $document
     * @return \DOMElement|null
     */
    private function rootOf(\DOMDocument $document): ?\DOMElement
    {
        foreach ($document->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Iterates over a static copy of the child list: cleanNode() replaces and
     * removes nodes as it goes, and a live DOMNodeList would skip siblings.
     *
     * @param \DOMNode $parent
     * @return void
     */
    private function cleanChildren(\DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            $this->cleanNode($child);
        }
    }

    /**
     * @param \DOMNode $node
     * @return void
     */
    private function cleanNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (!$node instanceof \DOMElement) {
            return;
        }

        $tag = strtolower($node->nodeName);

        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        // Depth-first: a disallowed wrapper is unwrapped only after its own
        // children have been cleaned, so nesting cannot smuggle anything past.
        $this->cleanChildren($node);

        if (!array_key_exists($tag, self::ALLOWED)) {
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node, $tag);
    }

    /**
     * @param \DOMElement $node
     * @param string $tag
     * @return void
     */
    private function cleanAttributes(\DOMElement $node, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($node->attributes) as $attribute) {
            if (!in_array(strtolower($attribute->nodeName), $allowed, true)) {
                $node->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a') {
            $this->cleanLink($node);
        }
    }

    /**
     * @param \DOMElement $node
     * @return void
     */
    private function cleanLink(\DOMElement $node): void
    {
        $href = trim($node->getAttribute('href'));

        if ($href === '' || !preg_match(self::SAFE_HREF_PATTERN, $href)) {
            $node->removeAttribute('href');
            $node->removeAttribute('target');
            $node->removeAttribute('rel');

            return;
        }

        $node->setAttribute('href', $href);

        // A target="_blank" link hands the opened tab a window.opener handle
        // back to this page unless rel says otherwise. Modern browsers imply
        // noopener, older ones do not, and the attribute costs nothing.
        if ($node->getAttribute('target') === '_blank') {
            $rel = trim($node->getAttribute('rel') . ' noopener noreferrer');
            $node->setAttribute('rel', implode(' ', array_unique(preg_split('/\s+/', $rel) ?: [])));
        }
    }

    /**
     * Replaces an element with its own children, in place.
     *
     * @param \DOMElement $node
     * @return void
     */
    private function unwrap(\DOMElement $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $parent->insertBefore($child, $node);
        }

        $parent->removeChild($node);
    }

    /**
     * @param \DOMNode $parent
     * @return string
     */
    private function serializeChildren(\DOMNode $parent): string
    {
        $html = '';

        foreach ($parent->childNodes as $child) {
            $html .= $parent->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
