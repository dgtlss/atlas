# Atlas

Expose selected Laravel routes as agent-readable Markdown and JSON without rewriting the controller or Blade view that already powers the browser experience.

## Install

```bash
composer require dgtlss/atlas
```

Publish the config if you want to customize negotiation, headers, metadata, or cache behavior:

```bash
php artisan vendor:publish --tag=atlas-config
```

## Quick Start

```php
use Illuminate\Support\Facades\Route;

Route::get('/docs/getting-started', fn () => view('docs.getting-started'))
    ->name('docs.show')
    ->atlas();
```

HTML stays the default response for browsers. Atlas only transforms the response when the route is explicitly opted in and the request asks for a supported machine-readable format:

```bash
curl -H "Accept: text/markdown" https://example.com/docs/getting-started
curl -H "Accept: application/json" https://example.com/docs/getting-started
curl "https://example.com/docs/getting-started?atlas=markdown"
```

## Route Options

```php
Route::get('/knowledge-base/{article}', ShowArticleController::class)->atlas([
    'formats' => ['markdown', 'json'],
    'presenter' => App\Atlas\ArticlePresenter::class,
    'cache' => ['ttl' => 900, 'vary' => ['locale']],
    'metadata' => ['section' => 'knowledge-base'],
    'query_parameter' => 'format',
    'markdown_transformer' => App\Atlas\CustomMarkdownTransformer::class,
    'json_transformer' => App\Atlas\CustomJsonTransformer::class,
]);
```

## Presenter Contract

Presenters take precedence over the generic HTML transformers when you need precise structured output.

```php
namespace App\Atlas;

use Dgtlss\Atlas\Contracts\AtlasPresenter;
use Dgtlss\Atlas\Support\AtlasContext;

class ArticlePresenter implements AtlasPresenter
{
    public function toMarkdown(AtlasContext $context): string
    {
        return "# {$context->title()}\n\nCustom article body";
    }

    public function toJson(AtlasContext $context): array
    {
        return [
            'title' => $context->title(),
            'url' => $context->url(),
            'content' => 'Custom article body',
            'summary' => 'Custom summary',
            'headings' => [],
            'metadata' => $context->metadata(),
        ];
    }
}
```

## Response Shape

Markdown responses can include frontmatter-style metadata, followed by the transformed page body.

JSON responses use these stable top-level keys:

```json
{
  "title": "Page title",
  "url": "https://example.com/docs/getting-started",
  "content": "Markdown-friendly readable content",
  "summary": "Short summary",
  "headings": [
    {
      "level": 1,
      "text": "Getting started",
      "id": "getting-started"
    }
  ],
  "metadata": {
    "section": "docs"
  }
}
```

## Notes

- Atlas only transforms routes that explicitly call `->atlas()`.
- Existing auth and authorization still run before transformation.
- Redirects, downloads, binary responses, and streamed responses fall back to their original response in v1.
- Atlas is designed for server-rendered HTML. Complex client-rendered interfaces are intentionally out of scope for the first release.
