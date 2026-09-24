<?php

declare(strict_types=1);

namespace MagIRC\Security;

final class HtmlSanitizer
{
    private const array TAGS = ['p', 'br', 'strong', 'em', 'u', 's', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'a', 'code', 'pre', 'hr', 'span', 'div', 'img'];
    private const array ATTRIBUTES = ['href', 'title', 'target', 'rel', 'src', 'alt', 'class'];

    public static function sanitize(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><body><div id="magirc-sanitize-root">' . $html . '</div></body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('magirc-sanitize-root');
        if (!$root) {
            return '';
        }
        self::walk($root);
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }
        return trim($result);
    }

    private static function walk(\DOMNode $node): void
    {
        for ($child = $node->firstChild; $child;) {
            $next = $child->nextSibling;
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, self::TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                        $node->removeChild($child);
                        $child = $next;
                        continue;
                    }
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    $child = $next;
                    continue;
                }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    $value = trim($attribute->value);
                    if (!in_array($name, self::ATTRIBUTES, true) || str_starts_with($name, 'on') || ($name === 'href' || $name === 'src') && !self::safeUrl($value)) {
                        $child->removeAttributeNode($attribute);
                    }
                }
                if ($tag === 'a' && $child->hasAttribute('target')) {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                self::walk($child);
            }
            $child = $next;
        }
    }

    private static function safeUrl(string $url): bool
    {
        $normalized = preg_replace('/\s+/', '', $url);
        if (!is_string($normalized) || $normalized === '') {
            return false;
        }
        $normalized = str_replace('\\', '/', $normalized);
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalized, $matches)) {
            return in_array(strtolower($matches[1]), ['http', 'https', 'mailto'], true);
        }
        // Relative links and same-origin fragments are safe; protocol-relative
        // URLs would bypass the configured origin and are therefore rejected.
        return !str_starts_with($normalized, '//');
    }
}
