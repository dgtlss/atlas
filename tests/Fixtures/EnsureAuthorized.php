<?php

declare(strict_types=1);

namespace Dgtlss\Atlas\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->headers->get('X-Authorized') === 'yes', 403);

        return $next($request);
    }
}
