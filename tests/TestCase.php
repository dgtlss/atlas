<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests;

use Dgtlss\Atlas\AtlasServiceProvider;
use Dgtlss\Atlas\Tests\Fixtures\EnsureAuthorized;
use Illuminate\Cookie\CookieJar;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AtlasServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY=');
        $app['config']->set('atlas.enabled', true);
        $app['config']->set('atlas.metadata.defaults', ['surface' => 'atlas']);
        $app['config']->set('auth.defaults.guard', 'web');

        $app['router']->aliasMiddleware('ensure-authorized', EnsureAuthorized::class);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/page', fn () => response($this->sampleHtml()))
            ->name('page')
            ->atlas(['metadata' => ['section' => 'docs']]);

        $router->get('/plain', fn () => response($this->sampleHtml()))
            ->name('plain');

        $router->get('/query-format', fn () => response($this->sampleHtml()))
            ->name('query-format')
            ->atlas(['query_parameter' => 'format']);

        $router->get('/markdown-only', fn () => response($this->sampleHtml()))
            ->name('markdown-only')
            ->atlas(['formats' => ['markdown']]);

        $router->get('/query-disabled', fn () => response($this->sampleHtml()))
            ->name('query-disabled')
            ->atlas(['query_parameter' => false]);

        $router->get('/presented', fn () => response($this->sampleHtml()))
            ->name('presented')
            ->atlas(['presenter' => Fixtures\CustomPresenter::class]);

        $router->get('/cached', fn () => response($this->sampleHtml()))
            ->name('cached')
            ->atlas([
                'cache' => true,
                'markdown_transformer' => Fixtures\CountingMarkdownTransformer::class,
                'json_transformer' => Fixtures\CountingJsonTransformer::class,
            ]);

        $router->get('/created', fn () => response($this->sampleHtml(), 201))
            ->name('created')
            ->atlas();

        $router->get('/headers-cookies', function () {
            $cookie = app(CookieJar::class)->make('atlas_session', 'abc123', 0);

            return response($this->sampleHtml())
                ->header('X-Atlas-Test', 'preserved')
                ->cookie($cookie);
        })
            ->name('headers-cookies')
            ->atlas();

        $router->get('/non-html', fn () => response()->json(['hello' => 'world']))
            ->name('non-html')
            ->atlas();

        $router->get('/protected', fn () => response($this->sampleHtml()))
            ->middleware('ensure-authorized')
            ->name('protected')
            ->atlas();

        $router->get('/redirecting', fn () => redirect('/page'))
            ->name('redirecting')
            ->atlas();

        $router->get('/streamed', fn () => response()->streamDownload(function (): void {
            echo 'atlas export';
        }, 'atlas.txt'))
            ->name('streamed')
            ->atlas();
    }

    protected function sampleHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Atlas Docs</title>
</head>
<body>
    <main>
        <h1 id="atlas-docs">Atlas Docs</h1>
        <p>Expose your Laravel pages as agent-readable content.</p>
        <h2>Why Atlas?</h2>
        <p>It preserves the browser experience and adds Markdown and JSON for tools.</p>
    </main>
</body>
</html>
HTML;
    }
}
