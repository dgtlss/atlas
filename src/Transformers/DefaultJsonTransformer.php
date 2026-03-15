<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Transformers;

use Dgtlss\Atlas\Contracts\JsonTransformer;
use Dgtlss\Atlas\Support\AtlasContext;
use League\HTMLToMarkdown\HtmlConverter;

class DefaultJsonTransformer implements JsonTransformer
{
    public function transform(string $html, AtlasContext $context): array
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
            'remove_nodes' => 'script style noscript template svg',
            'header_style' => 'atx',
        ]);

        $content = trim($converter->convert($context->document()->primaryHtml()));
        $content = (string) preg_replace("/\n{3,}/", "\n\n", $content);

        return [
            'title' => $context->title(),
            'url' => $context->url(),
            'content' => $content,
            'summary' => $context->document()->summary(),
            'headings' => $context->document()->headings(),
            'metadata' => $context->metadata(),
        ];
    }
}
