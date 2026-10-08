<?php

namespace App\Services;

class HtmlContentSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'a', 'span', 'div', 'hr', 'sup', 'sub'];

    public function clean(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8"><div id="content-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $root = $xpath->query('//*[@id="content-root"]')->item(0);
        if (! $root instanceof \DOMElement) return '';

        $this->cleanNode($root);
        $result = '';
        foreach ($root->childNodes as $child) $result .= $document->saveHTML($child);
        return $result;
    }

    private function cleanNode(\DOMNode $node): void
    {
        for ($child = $node->firstChild; $child;) {
            $next = $child->nextSibling;
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math'], true)) {
                        $node->removeChild($child);
                    } else {
                        while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                        $node->removeChild($child);
                    }
                } else {
                    $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
                    while ($child->attributes->length > 0) $child->removeAttributeNode($child->attributes->item(0));
                    if ($href !== '' && preg_match('/^(https?:|mailto:)/i', $href)) {
                        $child->setAttribute('href', $href);
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                    $this->cleanNode($child);
                }
            }
            $child = $next;
        }
    }
}
