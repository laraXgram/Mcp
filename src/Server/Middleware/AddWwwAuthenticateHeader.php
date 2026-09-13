<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Middleware;

use Closure;
use LaraGram\Http\Request;
use LaraGram\Routing\Route;
use LaraGram\Http\BaseResponse as Response;

class AddWwwAuthenticateHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (\LaraGram\Http\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() !== 401) {
            return $response;
        }

        $route = $request->route();
        if (! $route instanceof Route || ! in_array(self::class, app('router')->gatherRouteMiddleware($route), true)) {
            return $response;
        }

        $isOauth = app('router')->has('mcp.oauth.protected-resource.nested');
        if ($isOauth) {
            $response->header(
                'WWW-Authenticate',
                'Bearer realm="mcp", resource_metadata="'.route('mcp.oauth.protected-resource.nested', ['path' => $request->path()]).'"'
            );

            return $response;
        }

        // Citadel, can't share discover URL
        $response->header(
            'WWW-Authenticate',
            'Bearer realm="mcp", error="invalid_token"'
        );

        return $response;
    }
}
