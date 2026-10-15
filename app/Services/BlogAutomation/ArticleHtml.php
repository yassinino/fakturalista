<?php

namespace App\Services\BlogAutomation;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitizer for AI-written article HTML (rendered unescaped by
 * blog/show.blade.php). Keeps a small set of structural tags, drops every
 * attribute except a safe href, turns <h1> into <h2>, and reports the links
 * it found so the validator can check them.
 */
class ArticleHtml
{
    private const ALLOWED = ['p', 'h2', 'h3', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'a', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br'];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'noscript', 'template', 'head', 'title', 'meta', 'link', 'img', 'video', 'audio'];

    /**
     * @param  callable(string $href): ?array  $linkPolicy returns the attributes to keep for a link, null to unwrap it
     * @return array{html: string, links: array<int, array{href: string, text: string, kept: bool}>}
     */
    public static function sanitize(string $html, callable $linkPolicy): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return ['html' => '', 'links' => []];
        }

        $links = [];
        self::clean($root, $doc, $linkPolicy, $links);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // Tidy empty paragraphs left by removed elements.
        $out = preg_replace('/<p>\s*<\/p>/', '', $out);

        return ['html' => trim($out), 'links' => $links];
    }

    private static function clean(DOMNode $node, DOMDocument $doc, callable $linkPolicy, array &$links): void
    {
        // Copy: the child list changes while we edit it.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            if ($tag === 'h1' || $tag === 'h4' || $tag === 'h5' || $tag === 'h6') {
                $child = self::rename($child, $doc, $tag === 'h1' ? 'h2' : 'h3');
                $tag = $child->tagName;
            }

            self::clean($child, $doc, $linkPolicy, $links);

            if (! in_array($tag, self::ALLOWED, true)) {
                self::unwrap($child);
                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : null;
            while ($child->attributes->length) {
                $child->removeAttribute($child->attributes->item(0)->nodeName);
            }

            if ($tag === 'a') {
                $attributes = $href !== '' ? $linkPolicy($href) : null;
                $links[] = ['href' => (string) $href, 'text' => trim($child->textContent), 'kept' => $attributes !== null];
                if ($attributes === null) {
                    self::unwrap($child);
                    continue;
                }
                foreach ($attributes as $name => $value) {
                    $child->setAttribute($name, $value);
                }
            }
        }
    }

    private static function rename(DOMElement $el, DOMDocument $doc, string $tag): DOMElement
    {
        $new = $doc->createElement($tag);
        while ($el->firstChild) {
            $new->appendChild($el->firstChild);
        }
        $el->parentNode->replaceChild($new, $el);

        return $new;
    }

    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    /** @return array<int, array{level: int, text: string}> h2/h3 outline in order. */
    public static function headings(string $html): array
    {
        preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($h) => ['level' => (int) $h[1], 'text' => trim(html_entity_decode(strip_tags($h[2]), ENT_QUOTES, 'UTF-8'))], $m);
    }

    /** @return string[] paragraph texts. */
    public static function paragraphs(string $html): array
    {
        preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $m);

        return array_values(array_filter(array_map(
            fn ($p) => trim(html_entity_decode(strip_tags($p), ENT_QUOTES, 'UTF-8')),
            $m[1]
        )));
    }
}
