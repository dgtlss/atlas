<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests\Fixtures;

use Dgtlss\Atlas\Contracts\MarkdownTransformer;
use Dgtlss\Atlas\Support\AtlasContext;

class CountingMarkdownTransformer implements MarkdownTransformer
{
    public static int $count = 0;

    public function transform(string $html, AtlasContext $context): string
    {
        self::$count++;

        return "# Cached markdown\n\n" . $context->document()->summary();
    }
}
