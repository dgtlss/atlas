<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests\Fixtures;

use Dgtlss\Atlas\Contracts\AtlasPresenter;
use Dgtlss\Atlas\Support\AtlasContext;

class CustomPresenter implements AtlasPresenter
{
    public function toMarkdown(AtlasContext $context): string
    {
        return "# Presented\n\nCustom markdown for {$context->url()}";
    }

    public function toJson(AtlasContext $context): array
    {
        return [
            'title' => 'Presented title',
            'url' => $context->url(),
            'content' => 'Structured presenter content',
            'summary' => 'Presenter summary',
            'headings' => [
                ['level' => 1, 'text' => 'Presented', 'id' => 'presented'],
            ],
            'metadata' => ['presenter' => 'custom'],
        ];
    }
}
