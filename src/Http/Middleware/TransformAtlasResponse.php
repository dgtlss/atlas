<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Http\Middleware;

use Closure;
use Dgtlss\Atlas\AtlasManager;
use Illuminate\Http\Request;

class TransformAtlasResponse
{
    public function __construct(protected AtlasManager $atlas)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $options = $this->atlas->optionsFor($route);

        if (! $this->atlas->shouldRun($request, $options)) {
            return $next($request);
        }

        $format = $this->atlas->negotiateFormat($request, $options);

        if ($format === null) {
            return $next($request);
        }

        $response = $next($request);

        if (! $this->atlas->transformable($response, $options)) {
            return $response;
        }

        return $this->atlas->makeResponse($request, $response, $format, $options);
    }
}
