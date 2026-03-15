<?php

declare(strict_types=1);

namespace Dgtlss\Atlas;

use Dgtlss\Atlas\Http\Middleware\TransformAtlasResponse;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/atlas.php', 'atlas');

        $this->app->singleton(AtlasManager::class, function ($app): AtlasManager {
            return new AtlasManager($app, $app['config'], $app['cache']);
        });
    }

    public function boot(Router $router): void
    {
        $this->publishes([
            __DIR__ . '/../config/atlas.php' => config_path('atlas.php'),
        ], 'atlas-config');

        $router->aliasMiddleware('atlas', TransformAtlasResponse::class);

        LaravelRoute::macro('atlas', function (array $options = []) {
            $existing = (array) ($this->defaults['_atlas'] ?? []);
            $merged = array_replace_recursive($existing, $options);

            $this->defaults('_atlas', $merged);
            $this->middleware('atlas');

            return $this;
        });
    }
}
