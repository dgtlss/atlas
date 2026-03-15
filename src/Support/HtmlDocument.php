<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;

class HtmlDocument
{
    protected DOMDocument $document;

    protected DOMXPath $xpath;

    public function __construct(protected string $html)
    {
        $this->document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $this->document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->xpath = new DOMXPath($this->document);
    }

    public function title(): ?string
    {
        $title = $this->xpath->evaluate('string(//title[1])');

        if (is_string($title) && trim($title) !== '') {
            return trim($title);
        }

        $heading = $this->xpath->evaluate('string((//main//h1 | //article//h1 | //body//h1)[1])');

        return is_string($heading) && trim($heading) !== '' ? trim($heading) : null;
    }

    public function primaryHtml(): string
    {
        $node = $this->primaryNode();

        if ($node === null) {
            return $this->html;
        }

        return $this->innerHtml($node);
    }

    public function primaryText(): string
    {
        $node = $this->primaryNode();

        if ($node === null) {
            return trim(strip_tags($this->html));
        }

        return $this->cleanText($node->textContent ?? '');
    }

    /**
     * @return array<int, array{level:int,text:string,id:?string}>
     */
    public function headings(): array
    {
        $nodes = $this->xpath->query('//main//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6] | //article//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6] | //body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]');

        if ($nodes === false) {
            return [];
        }

        $headings = [];

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $text = $this->cleanText($node->textContent ?? '');

            if ($text === '') {
                continue;
            }

            $headings[] = [
                'level' => (int) Str::after($node->tagName, 'h'),
                'text' => $text,
                'id' => $node->getAttribute('id') ?: Str::slug($text),
            ];
        }

        return $headings;
    }

    public function summary(): string
    {
        $paragraph = $this->xpath->evaluate('string((//main//p | //article//p | //body//p)[1])');

        if (is_string($paragraph) && trim($paragraph) !== '') {
            return Str::limit($this->cleanText($paragraph), 220, '...');
        }

        return Str::limit($this->primaryText(), 220, '...');
    }

    protected function primaryNode(): ?DOMNode
    {
        $this->removeIgnoredNodes();

        $node = $this->xpath->query('//main[1] | //article[1] | //body[1]');

        if ($node === false || $node->length === 0) {
            return null;
        }

        return $node->item(0);
    }

    protected function removeIgnoredNodes(): void
    {
        $nodes = $this->xpath->query('//script | //style | //noscript | //template | //svg');

        if ($nodes === false) {
            return;
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    protected function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $childNode) {
            $html .= $this->document->saveHTML($childNode);
        }

        return trim($html);
    }

    protected function cleanText(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
