<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests\Fixtures;

use Dgtlss\Atlas\Contracts\JsonTransformer;
use Dgtlss\Atlas\Support\AtlasContext;

class CountingJsonTransformer implements JsonTransformer
{
    public static int $count = 0;

    public function transform(string $html, AtlasContext $context): array
    {
        self::$count++;

        return [
            'title' => 'Cached title',
            'url' => $context->url(),
            'content' => 'Cached json content',
            'summary' => 'Cached json summary',
            'headings' => [],
            'metadata' => ['cached' => true],
        ];
    }
}
