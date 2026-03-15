<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Contracts;

use Dgtlss\Atlas\Support\AtlasContext;

interface AtlasPresenter
{
    public function toMarkdown(AtlasContext $context): string;

    /**
     * @return array<string, mixed>
     */
    public function toJson(AtlasContext $context): array;
}
