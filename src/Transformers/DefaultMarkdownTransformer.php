<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Transformers;

use Dgtlss\Atlas\Contracts\MarkdownTransformer;
use Dgtlss\Atlas\Support\AtlasContext;
use League\HTMLToMarkdown\HtmlConverter;

class DefaultMarkdownTransformer implements MarkdownTransformer
{
    public function transform(string $html, AtlasContext $context): string
    {
        $converter = new HtmlConverter([
            'strip_tags' => true,
            'hard_break' => true,
            'remove_nodes' => 'script style noscript template svg',
            'header_style' => 'atx',
        ]);

        $markdown = trim($converter->convert($context->document()->primaryHtml()));

        return (string) preg_replace("/\n{3,}/", "\n\n", $markdown);
    }
}
