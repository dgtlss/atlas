<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Contracts;

use Dgtlss\Atlas\Support\AtlasContext;

interface MarkdownTransformer
{
    public function transform(string $html, AtlasContext $context): string;
}
