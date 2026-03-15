<?php

declare(strict_types=1);

return [
    'enabled' => env('ATLAS_ENABLED', true),

    'allowed_environments' => null,

    'default_formats' => ['markdown', 'json'],

    'negotiation' => [
        'accept_header' => true,
        'allow_query_parameter' => true,
        'query_parameter' => 'atlas',
    ],

    'metadata' => [
        'include_frontmatter' => true,
        'defaults' => [],
    ],

    'headers' => [
        'canonical' => true,
        'source_url' => true,
    ],

    'cache' => [
        'enabled' => false,
        'store' => null,
        'ttl' => 600,
        'vary' => ['locale'],
    ],

    'transformers' => [
        'markdown' => Dgtlss\Atlas\Transformers\DefaultMarkdownTransformer::class,
        'json' => Dgtlss\Atlas\Transformers\DefaultJsonTransformer::class,
    ],
];
